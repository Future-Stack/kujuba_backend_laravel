<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automated Client Inspection Summary Reports
Schedule::command('reports:send-client-summary --frequency=daily')
    ->dailyAt('08:00')
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/client_daily_reports.log'));

Schedule::command('reports:send-client-summary --frequency=weekly')
    ->weeklyOn(1, '08:00') // Every Monday at 8 AM EST
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/client_weekly_reports.log'));

Schedule::command('reports:send-client-summary --frequency=monthly')
    ->monthlyOn(1, '08:00') // 1st of every month at 8 AM EST
    ->timezone('America/New_York')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/client_monthly_reports.log'));


