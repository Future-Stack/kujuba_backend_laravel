<?php

namespace App\Jobs;

use App\Models\InspectionAssign;
use Stripe\StripeClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessInspectorPayout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $assignId;

    public function __construct($assignId)
    {
        $this->assignId = $assignId;
    }

    public function handle(): void
    {
        $assign = InspectionAssign::with([
            'inspector.profile',
            'inspectionBooking.payment'
        ])->find($this->assignId);

        if (!$assign) {
            Log::warning("Assign not found: {$this->assignId}");
            return;
        }

        $payment = $assign->inspectionBooking?->payment;

        if (!$payment) {
            Log::warning("Payment not found: {$assign->id}");
            return;
        }

        // already done
        if ($payment->status === 'paid') return;

        // must be processing only
        if ($payment->status !== 'processing') {
            Log::info("Payment not processing: {$payment->id}");
            return;
        }

        $inspector = $assign->inspector;

        if (
            !$inspector ||
            !$inspector->profile ||
            !$inspector->profile->stripe_account_id
        ) {
            Log::warning("Missing Stripe account: {$assign->inspector_id}");

            $payment->update([
                'status' => 'pending'
            ]);

            return;
        }

        try {
            $stripe = new StripeClient(config('services.stripe.secret'));

            // inspector gets ONLY inspector_share
            $amount = (int) round($payment->inspector_share * 100);

            if ($amount <= 0) {
                Log::warning("Invalid amount: {$payment->id}");
                return;
            }

            $transfer = $stripe->transfers->create([
                'amount'      => $amount,
                'currency'    => 'usd',
                'destination' => $inspector->profile->stripe_account_id,
                'description' => 'Inspection Payout #' . $assign->id,
            ]);

            // SUCCESS
            $payment->update([
                'status'       => 'paid',
                'stripe_id'    => $transfer->id,
                'trx_id'       => $transfer->id,
            ]);

            Log::info("Payout success: {$payment->id}");

        } catch (\Exception $e) {

            Log::error("Payout failed: " . $e->getMessage());

            $payment->update([
                'status' => 'failed'
            ]);
        }
    }
}