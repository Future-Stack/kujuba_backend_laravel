<?php

namespace App\Observers;

use App\Models\InspectionReport;

class InspectionReportObserver
{
    public function updated(InspectionReport $report): void
    {
        // শুধু status change হলে কাজ করবে
        if (!$report->wasChanged('status')) {
            return;
        }

        $assign = $report->inspectionAssign;

        if (!$assign) {
            return;
        }

        // → assign status mapping
        // $map = [
        //     'started'   => 'inspection',
        //     'completed' => 'completed',
        //     'cancelled' => 'cancelled',
        // ];

        if (isset($map[$report->status])) {
            $assign->update([
                'status' => $map[$report->status]
            ]);
        }
    }
}