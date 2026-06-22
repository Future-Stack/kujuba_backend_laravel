<?php

namespace App\Observers;

use App\Models\InspectionReport;
use App\Jobs\ProcessInspectorPayout;
use Illuminate\Support\Facades\Log;

class InspectionReportObserver
{
    public function updated(InspectionReport $report): void
    {
        // only when status changes
        if (!$report->wasChanged('status')) {
            return;
        }

        // load relation
        $report->load('inspectionAssign.inspectionBooking.payment');

        $assign = $report->inspectionAssign;

        if (!$assign) {
            Log::warning("Assign not found for report ID: {$report->id}");
            return;
        }

        // 🟡 1. SYNC assign status (safe)
        $assign->updateQuietly([
            'status' => $report->status
        ]);

        // 🟢 only completed triggers payout
        if ($report->status !== 'completed') {
            return;
        }

        $payment = $assign->inspectionBooking?->payment;

        if (!$payment) {
            Log::warning("Payment not found for assign ID: {$assign->id}");
            return;
        }

        // 🚨 prevent duplicate payout
        if ($payment->is_disbursed || $payment->status === 'processing') {
            Log::info("Already processing/paid payment ID: {$payment->id}");
            return;
        }

        // 🔥 mark processing BEFORE queue
        $payment->updateQuietly([
            'status' => 'processing'
        ]);

        // 🚀 dispatch job
        ProcessInspectorPayout::dispatch($assign->id);

        Log::info("Payout job dispatched", [
            'assign_id' => $assign->id,
            'payment_id' => $payment->id
        ]);
    }
}