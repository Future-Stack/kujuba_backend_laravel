<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\InspectionReport;
use App\Observers\InspectionReportObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
{
    InspectionReport::observe(InspectionReportObserver::class);
}
}
