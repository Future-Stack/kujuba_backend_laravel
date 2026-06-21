<?php

namespace App\Observers;

use App\Models\InspectionReport;
use App\Jobs\ProcessInspectorPayout;

class InspectionReportObserver
{
    public function updated(InspectionReport $report): void
    {
        if (!$report->wasChanged('status')) {
            return;
        }

        $assign = $report->inspectionAssign;

        if (!$assign) return;

        // =========================
        // SYNC STATUS
        // =========================
        $assign->updateQuietly([
            'status' => $report->status
        ]);

        // =========================
        // ONLY ON COMPLETED
        // =========================
        if ($report->status !== 'completed') {
            return;
        }

        $payment = $assign->inspectionBooking?->payment;

        if (!$payment) return;

        // prevent duplicate payout
        if ($payment->payout_status !== 'pending') {
            return;
        }

        // mark processing BEFORE job
        $payment->updateQuietly([
            'payout_status' => 'processing'
        ]);

        // dispatch job
        ProcessInspectorPayout::dispatch($assign->id);
    }
}
