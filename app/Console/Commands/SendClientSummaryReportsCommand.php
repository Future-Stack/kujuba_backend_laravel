<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\ClientReportSchedule;
use App\Services\ClientReportService;
use App\Mail\ClientInspectionSummaryMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendClientSummaryReportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:send-client-summary {--frequency=daily : daily, weekly, or monthly} {--client_id= : specific client ID} {--schedule_id= : specific schedule ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send automated daily, weekly, or monthly inspection summary reports to corporate clients (Insurance companies, Realtors)';

    /**
     * Execute the console command.
     */
    public function handle(ClientReportService $reportService): int
    {
        $frequency = strtolower($this->option('frequency') ?: 'daily');
        $specificClientId = $this->option('client_id');
        $specificScheduleId = $this->option('schedule_id');

        $this->info("Starting {$frequency} client inspection summary report dispatch...");

        // 1. Process custom scheduled configurations from database
        $schedulesQuery = ClientReportSchedule::with(['client.profile'])
            ->where('is_enabled', true)
            ->where('frequency', $frequency);

        if ($specificScheduleId) {
            $schedulesQuery->where('id', $specificScheduleId);
        }

        if ($specificClientId) {
            $schedulesQuery->where('client_id', $specificClientId);
        }

        $schedules = $schedulesQuery->get();

        $sentCount = 0;
        $failedCount = 0;

        if ($schedules->isNotEmpty()) {
            foreach ($schedules as $schedule) {
                try {
                    $this->line("Processing schedule #{$schedule->id} for {$schedule->recipient_email} (Frequency: {$frequency})...");

                    $reportResult = $reportService->getClientReportData(
                        $schedule->client_id,
                        $frequency,
                        null,
                        null,
                        $schedule->inspection_status === 'all' ? null : $schedule->inspection_status
                    );

                    $reportData = $reportResult['reports'] ?? $reportResult;

                    if (!$reportData) {
                        $this->warn("No report data generated for schedule #{$schedule->id}.");
                        continue;
                    }

                    Mail::to($schedule->recipient_email)->send(new ClientInspectionSummaryMail($reportData));

                    $schedule->update(['last_sent_at' => now()]);

                    $this->info("Successfully sent {$frequency} report to {$schedule->recipient_email}.");
                    $sentCount++;

                } catch (\Exception $e) {
                    $this->error("Failed to send scheduled report to {$schedule->recipient_email}: " . $e->getMessage());
                    Log::error("Automated Client Report Schedule Error (#{$schedule->id}): " . $e->getMessage());
                    $failedCount++;
                }
            }
        } else {
            // Fallback: Dispatch to active clients directly if no custom schedule was set
            $clientsQuery = User::where('user_type', 'client')
                ->where('status', 'active')
                ->with('profile');

            if ($specificClientId) {
                $clientsQuery->where('id', $specificClientId);
            }

            $clients = $clientsQuery->get();

            foreach ($clients as $client) {
                try {
                    $this->line("Processing report for client: {$client->email} ({$client->profile?->company_name})...");

                    $reportResult = $reportService->getClientReportData(
                        $client->id,
                        $frequency
                    );

                    $reportData = $reportResult['reports'] ?? null;

                    if (!$reportData) {
                        $this->warn("No report data generated for client ID {$client->id}.");
                        continue;
                    }

                    Mail::to($client->email)->send(new ClientInspectionSummaryMail($reportData));

                    $this->info("Successfully sent {$frequency} report to {$client->email}.");
                    $sentCount++;

                } catch (\Exception $e) {
                    $this->error("Failed to send report to {$client->email}: " . $e->getMessage());
                    Log::error("Automated Client Report Error ({$client->email}): " . $e->getMessage());
                    $failedCount++;
                }
            }
        }

        $this->info("Report dispatch completed. Sent: {$sentCount}, Failed: {$failedCount}.");

        return Command::SUCCESS;
    }
}
