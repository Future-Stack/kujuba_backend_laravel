<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\ClientReportService;
use App\Mail\ClientInspectionSummaryMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendClientSummaryReportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:send-client-summary {--frequency=daily : daily or weekly} {--client_id= : specific client ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send automated daily or weekly inspection summary reports to corporate clients (Insurance companies, Realtors)';

    /**
     * Execute the console command.
     */
    public function handle(ClientReportService $reportService): int
    {
        $frequency = $this->option('frequency') ?: 'daily';
        $specificClientId = $this->option('client_id');

        $this->info("Starting {$frequency} client inspection summary report dispatch...");

        $clientsQuery = User::where('user_type', 'client')
            ->where('status', 'active')
            ->with('profile');

        if ($specificClientId) {
            $clientsQuery->where('id', $specificClientId);
        }

        $clients = $clientsQuery->get();

        if ($clients->isEmpty()) {
            $this->info('No active clients found for report dispatch.');
            return Command::SUCCESS;
        }

        $sentCount = 0;
        $failedCount = 0;

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

        $this->info("Report dispatch completed. Sent: {$sentCount}, Failed: {$failedCount}.");

        return Command::SUCCESS;
    }
}
