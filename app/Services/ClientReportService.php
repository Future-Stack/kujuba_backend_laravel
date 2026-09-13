<?php

namespace App\Services;

use App\Models\User;
use App\Models\InspectionBooking;
use App\Models\InspectionReport;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class ClientReportService
{
    /**
     * Build report data for a specific client or all clients
     *
     * @param int|null $clientId
     * @param string $frequency (daily, yesterday, weekly, monthly, custom)
     * @param string|null $startDate
     * @param string|null $endDate
     * @param string|null $status
     * @return array
     */
    public function getClientReportData(?int $clientId = null, string $frequency = 'weekly', ?string $startDate = null, ?string $endDate = null, ?string $status = null): array
    {
        $dateRange = $this->resolveDateRange($frequency, $startDate, $endDate);

        $clientQuery = User::where('user_type', 'client')->with('profile');
        if ($clientId) {
            $clientQuery->where('id', $clientId);
        }

        $clients = $clientQuery->get();

        $clientReports = [];

        foreach ($clients as $client) {
            $bookingsQuery = InspectionBooking::where('client_id', $client->id)
                ->whereBetween('booking_date', [$dateRange['start'], $dateRange['end']])
                ->with([
                    'homeowner.profile',
                    'inspectionTypes',
                    'assignment.inspector.profile',
                    'assignment.inspectionReport'
                ]);

            if ($status && $status !== 'all') {
                $bookingsQuery->where('status', $status);
            }

            $bookings = $bookingsQuery->latest('booking_date')->get();

            // Calculate Metrics
            $totalCount = $bookings->count();
            $completedCount = $bookings->where('status', 'completed')->count();
            $pendingCount = $bookings->where('status', 'pending')->count();
            $inProgressCount = $bookings->filter(fn($b) => in_array($b->status, ['assigned', 'started', 'reports']))->count();
            $cancelledCount = $bookings->where('status', 'cancelled')->count();

            // Transform Inspections List
            $inspectionsList = $bookings->map(function ($booking) {
                $report = $booking->assignment?->inspectionReport;

                return [
                    'booking_id'        => $booking->id,
                    'reference_no'      => 'INS-' . str_pad($booking->id, 6, '0', STR_PAD_LEFT),
                    'homeowner_name'    => trim(($booking->homeowner?->first_name ?? '') . ' ' . ($booking->homeowner?->last_name ?? '')) ?: 'N/A',
                    'homeowner_email'   => $booking->homeowner?->email ?? 'N/A',
                    'homeowner_phone'   => $booking->homeowner?->profile?->phone ?? 'N/A',
                    'property_address'  => $booking->property_address,
                    'property_type'     => $booking->property_type,
                    'inspection_types'  => $booking->inspectionTypes->pluck('title')->implode(', ') ?: 'Standard Inspection',
                    'inspector_name'    => trim(($booking->assignment?->inspector?->first_name ?? '') . ' ' . ($booking->assignment?->inspector?->last_name ?? '')) ?: 'Unassigned',
                    'inspector_email'   => $booking->assignment?->inspector?->email ?? 'N/A',
                    'scheduled_date'    => $booking->scheduled_date ? $booking->scheduled_date->format('Y-m-d') : ($booking->booking_date ? $booking->booking_date->format('Y-m-d') : 'Pending'),
                    'scheduled_time'    => $booking->scheduled_time ?: 'Pending',
                    'status'            => ucfirst($booking->status),
                    'report_available'  => $report && $report->status === 'completed' ? true : false,
                    'report_file_url'   => ($report && $report->report_file) ? asset('storage/' . $report->report_file) : null,
                    'report_completed_at' => $report?->created_at?->format('Y-m-d H:i') ?? null,
                ];
            });

            $clientReports[] = [
                'client' => [
                    'id'           => $client->id,
                    'name'         => trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')),
                    'company_name' => $client->profile?->company_name ?: 'N/A',
                    'client_type'  => $client->profile?->client_type ?: 'Corporate Client',
                    'email'        => $client->email,
                    'phone'        => $client->profile?->phone ?: 'N/A',
                    'address'      => $client->profile?->address ?: 'N/A',
                ],
                'period' => [
                    'frequency'  => ucfirst($frequency),
                    'start_date' => $dateRange['start']->format('Y-m-d'),
                    'end_date'   => $dateRange['end']->format('Y-m-d'),
                    'generated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                ],
                'summary' => [
                    'total_inspections'     => $totalCount,
                    'completed_inspections' => $completedCount,
                    'in_progress_inspections' => $inProgressCount,
                    'pending_inspections'   => $pendingCount,
                    'cancelled_inspections' => $cancelledCount,
                    'completion_rate'       => $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 1) . '%' : '0%',
                ],
                'inspections' => $inspectionsList,
            ];
        }

        return [
            'frequency'    => $frequency,
            'period_label' => $dateRange['label'],
            'total_clients'=> count($clientReports),
            'reports'      => $clientId && count($clientReports) > 0 ? $clientReports[0] : $clientReports,
        ];
    }

    /**
     * Generate PDF binary stream for a client report
     */
    public function generatePdf(array $clientReportData)
    {
        // If single client report structure is nested
        $reportData = isset($clientReportData['client']) ? $clientReportData : ($clientReportData['reports'] ?? $clientReportData);

        $pdf = Pdf::loadView('reports.client_inspection_summary', [
            'report' => $reportData,
        ])->setPaper('a4', 'landscape');

        return $pdf;
    }

    /**
     * Resolve Date Range from Frequency string
     */
    private function resolveDateRange(string $frequency, ?string $startDate, ?string $endDate): array
    {
        $now = Carbon::now();

        if ($frequency === 'custom' && $startDate && $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();
            return [
                'start' => $start,
                'end'   => $end,
                'label' => $start->format('M d, Y') . ' - ' . $end->format('M d, Y'),
            ];
        }

        switch ($frequency) {
            case 'daily':
            case 'today':
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                $label = 'Daily Report - ' . $now->format('M d, Y');
                break;

            case 'yesterday':
                $start = $now->copy()->subDay()->startOfDay();
                $end = $now->copy()->subDay()->endOfDay();
                $label = 'Daily Report - ' . $start->format('M d, Y');
                break;

            case 'monthly':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                $label = 'Monthly Report - ' . $now->format('F Y');
                break;

            case 'weekly':
            default:
                $start = $now->copy()->subDays(6)->startOfDay();
                $end = $now->copy()->endOfDay();
                $label = 'Weekly Report (' . $start->format('M d') . ' - ' . $end->format('M d, Y') . ')';
                break;
        }

        return [
            'start' => $start,
            'end'   => $end,
            'label' => $label,
        ];
    }
}
