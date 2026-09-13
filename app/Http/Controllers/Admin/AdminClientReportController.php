<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
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
}
