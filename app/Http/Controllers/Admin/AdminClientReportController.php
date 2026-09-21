<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ClientReportSchedule;
use App\Services\ClientReportService;
use App\Mail\ClientInspectionSummaryMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class AdminClientReportController extends Controller
{
    protected ClientReportService $reportService;

    public function __construct(ClientReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * 👥 GET CLIENTS DROPDOWN LIST (For report filter)
     */
    public function clientsList(Request $request)
    {
        $clients = User::where('user_type', 'client')
            ->with('profile')
            ->get()
            ->map(function ($client) {
                return [
                    'id'           => $client->id,
                    'name'         => trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')),
                    'company_name' => $client->profile?->company_name ?: 'N/A',
                    'client_type'  => $client->profile?->client_type ?: 'insurance_company',
                    'email'        => $client->email,
                    'status'       => $client->status,
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $clients
        ]);
    }

    /**
     * 📊 GENERATE REPORT (JSON for Dashboard Table / Chart view)
     */
    public function generate(Request $request)
    {
        $request->validate([
            'client_id'   => 'nullable|integer|exists:users,id',
            'frequency'   => 'nullable|string|in:daily,today,yesterday,weekly,monthly,custom',
            'start_date'  => 'nullable|required_if:frequency,custom|date',
            'end_date'    => 'nullable|required_if:frequency,custom|date|after_or_equal:start_date',
            'status'      => 'nullable|string|in:all,pending,assigned,started,reports,completed,cancelled',
        ]);

        try {
            $reportData = $this->reportService->getClientReportData(
                $request->client_id ? (int) $request->client_id : null,
                $request->get('frequency', 'weekly'),
                $request->start_date,
                $request->end_date,
                $request->status
            );

            return response()->json([
                'success' => true,
                'data'    => $reportData,
            ]);

        } catch (\Exception $e) {
            Log::error('Client Report Generation Failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 📥 DOWNLOAD REPORT AS PDF
     */
    public function downloadPdf(Request $request)
    {
        $request->validate([
            'client_id'   => 'required|integer|exists:users,id',
            'frequency'   => 'nullable|string|in:daily,today,yesterday,weekly,monthly,custom',
            'start_date'  => 'nullable|required_if:frequency,custom|date',
            'end_date'    => 'nullable|required_if:frequency,custom|date|after_or_equal:start_date',
            'status'      => 'nullable|string|in:all,pending,assigned,started,reports,completed,cancelled',
        ]);

        try {
            $reportData = $this->reportService->getClientReportData(
                (int) $request->client_id,
                $request->get('frequency', 'weekly'),
                $request->start_date,
                $request->end_date,
                $request->status
            );

            $pdf = $this->reportService->generatePdf($reportData);

            $clientName = str_replace(' ', '_', strtolower($reportData['reports']['client']['company_name'] ?? 'client'));
            $date = date('Y-m-d');
            $fileName = "inspection_summary_{$clientName}_{$date}.pdf";

            return $pdf->download($fileName);

        } catch (\Exception $e) {
            Log::error('Client Report PDF Download Failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to download report PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✉️ SEND REPORT VIA EMAIL TO CLIENT
     */
    public function sendEmail(Request $request)
    {
        $request->validate([
            'client_id'      => 'required|integer|exists:users,id',
            'email_override' => 'nullable|email',
            'frequency'      => 'nullable|string|in:daily,today,yesterday,weekly,monthly,custom',
            'start_date'     => 'nullable|required_if:frequency,custom|date',
            'end_date'       => 'nullable|required_if:frequency,custom|date|after_or_equal:start_date',
            'status'         => 'nullable|string|in:all,pending,assigned,started,reports,completed,cancelled',
        ]);

        try {
            $client = User::where('user_type', 'client')->findOrFail($request->client_id);
            $targetEmail = $request->email_override ?: $client->email;

            $reportData = $this->reportService->getClientReportData(
                $client->id,
                $request->get('frequency', 'weekly'),
                $request->start_date,
                $request->end_date,
                $request->status
            );

            Mail::to($targetEmail)->send(new ClientInspectionSummaryMail($reportData['reports']));

            return response()->json([
                'success' => true,
                'message' => "Inspection summary report successfully sent to {$targetEmail}.",
            ]);

        } catch (\Exception $e) {
            Log::error('Send Client Report Email Failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send report email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 📅 LIST ALL AUTOMATED REPORT SCHEDULES
     */
    public function listSchedules(Request $request)
    {
        try {
            $query = ClientReportSchedule::with(['client.profile'])->latest();

            if ($request->filled('frequency')) {
                $query->where('frequency', $request->frequency);
            }

            if ($request->filled('is_enabled')) {
                $query->where('is_enabled', filter_var($request->is_enabled, FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('recipient_email', 'like', "%{$search}%")
                      ->orWhereHas('client', function ($cq) use ($search) {
                          $cq->where('first_name', 'like', "%{$search}%")
                             ->orWhere('last_name', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            $schedules = $query->paginate((int) $request->get('per_page', 15));

            $schedules->getCollection()->transform(function ($schedule) {
                return [
                    'id'                 => $schedule->id,
                    'client_id'          => $schedule->client_id,
                    'client_name'        => $schedule->client ? trim(($schedule->client->first_name ?? '') . ' ' . ($schedule->client->last_name ?? '')) : 'All Corporate Clients',
                    'company_name'       => $schedule->client?->profile?->company_name ?: 'N/A',
                    'recipient_email'    => $schedule->recipient_email,
                    'frequency'          => $schedule->frequency, // daily, weekly, monthly
                    'send_time'          => $schedule->send_time, // e.g. 09:00 AM
                    'day_of_week'        => $schedule->day_of_week, // mon, tue, etc.
                    'day_of_month'       => $schedule->day_of_month,
                    'inspection_status'  => $schedule->inspection_status, // all, completed, in_progress, pending
                    'is_enabled'         => (bool) $schedule->is_enabled,
                    'timezone'           => $schedule->timezone,
                    'last_sent_at'       => optional($schedule->last_sent_at)->format('d M Y, h:i A'),
                    'created_at'         => optional($schedule->created_at)->format('d M Y'),
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $schedules
            ]);

        } catch (\Exception $e) {
            Log::error('List Report Schedules Failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to list report schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 💾 SAVE / CREATE REPORT SCHEDULE (From Modal in Images 1, 2, 3)
     */
    public function saveSchedule(Request $request)
    {
        $validated = $request->validate([
            'client_id'          => 'nullable|integer|exists:users,id',
            'recipient_email'    => 'required|email|max:255',
            'frequency'          => 'required|string|in:daily,weekly,monthly',
            'send_time'          => 'nullable|string|max:10', // e.g. "09:00"
            'day_of_week'        => 'nullable|required_if:frequency,weekly|string|in:mon,tue,wed,thu,fri,sat,sun,Mon,Tue,Wed,Thu,Fri,Sat,Sun',
            'day_of_month'       => 'nullable|required_if:frequency,monthly|integer|min:1|max:31',
            'inspection_status'  => 'nullable|string|in:all,completed,in_progress,pending',
            'is_enabled'         => 'nullable|boolean',
            'timezone'           => 'nullable|string|max:50',
        ]);

        try {
            $schedule = ClientReportSchedule::create([
                'client_id'          => $validated['client_id'] ?? null,
                'recipient_email'    => strtolower($validated['recipient_email']),
                'frequency'          => strtolower($validated['frequency']),
                'send_time'          => $validated['send_time'] ?? '09:00',
                'day_of_week'        => isset($validated['day_of_week']) ? strtolower($validated['day_of_week']) : null,
                'day_of_month'       => $validated['day_of_month'] ?? 1,
                'inspection_status'  => $validated['inspection_status'] ?? 'all',
                'is_enabled'         => $request->has('is_enabled') ? filter_var($request->is_enabled, FILTER_VALIDATE_BOOLEAN) : true,
                'timezone'           => $validated['timezone'] ?? 'America/New_York',
                'created_by'         => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Automated report schedule saved successfully.',
                'data'    => $schedule
            ], 201);

        } catch (\Exception $e) {
            Log::error('Save Report Schedule Failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save report schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🔍 SHOW SINGLE SCHEDULE
     */
    public function showSchedule($id)
    {
        $schedule = ClientReportSchedule::with(['client.profile'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $schedule
        ]);
    }

    /**
     * ✏️ UPDATE SCHEDULE
     */
    public function updateSchedule(Request $request, $id)
    {
        $schedule = ClientReportSchedule::findOrFail($id);

        $validated = $request->validate([
            'client_id'          => 'nullable|integer|exists:users,id',
            'recipient_email'    => 'sometimes|required|email|max:255',
            'frequency'          => 'sometimes|required|string|in:daily,weekly,monthly',
            'send_time'          => 'nullable|string|max:10',
            'day_of_week'        => 'nullable|string|in:mon,tue,wed,thu,fri,sat,sun,Mon,Tue,Wed,Thu,Fri,Sat,Sun',
            'day_of_month'       => 'nullable|integer|min:1|max:31',
            'inspection_status'  => 'nullable|string|in:all,completed,in_progress,pending',
            'is_enabled'         => 'nullable|boolean',
            'timezone'           => 'nullable|string|max:50',
        ]);

        try {
            if (isset($validated['day_of_week'])) {
                $validated['day_of_week'] = strtolower($validated['day_of_week']);
            }
            if (isset($validated['frequency'])) {
                $validated['frequency'] = strtolower($validated['frequency']);
            }
            if (isset($validated['recipient_email'])) {
                $validated['recipient_email'] = strtolower($validated['recipient_email']);
            }

            $schedule->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Report schedule updated successfully.',
                'data'    => $schedule
            ]);

        } catch (\Exception $e) {
            Log::error('Update Report Schedule Failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update report schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🔄 TOGGLE SCHEDULE STATUS (ENABLE / DISABLE)
     */
    public function toggleSchedule($id)
    {
        $schedule = ClientReportSchedule::findOrFail($id);
        $schedule->update(['is_enabled' => !$schedule->is_enabled]);

        return response()->json([
            'success' => true,
            'message' => 'Schedule status updated to ' . ($schedule->is_enabled ? 'Active' : 'Disabled'),
            'data'    => [
                'id'         => $schedule->id,
                'is_enabled' => $schedule->is_enabled
            ]
        ]);
    }

    /**
     * 🗑️ DELETE SCHEDULE
     */
    public function deleteSchedule($id)
    {
        $schedule = ClientReportSchedule::findOrFail($id);
        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Report schedule deleted successfully.'
        ]);
    }
}
