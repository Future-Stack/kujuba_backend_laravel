<?php

namespace App\Jobs;

use App\Models\InspectionAssign;
use App\Models\InspectorPayout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class ProcessInspectorPayout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $assignId) {}

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
            Log::warning("Payment not found for assign: {$assign->id}");
            return;
        }

        $inspector = $assign->inspector;

        if (!$inspector || !$inspector->profile?->stripe_account_id) {
            Log::warning("Stripe account missing for inspector: {$assign->id}");
            return;
        }

        // amount validation
        $payoutAmount = (float) $payment->inspector_share;

        if ($payoutAmount <= 0) {
            Log::warning("Invalid payout amount: {$payment->id}");
            return;
        }

        // duplicate protection (VERY IMPORTANT)
        $alreadyPaid = InspectorPayout::where('inspection_assign_id', $assign->id)
            ->where('status', 'paid')
            ->exists();

        if ($alreadyPaid || $payment->is_disbursed) {
            Log::info("Already paid payout: {$assign->id}");
            return;
        }

        try {
            // create payout record first
            $payout = InspectorPayout::create([
                'inspector_id' => $inspector->id,
                'inspection_assign_id' => $assign->id,
                'inspection_payment_id' => $payment->id,
                'amount' => $payoutAmount,
                'platform_fee' => $payment->platform_fee ?? 0,
                'currency' => $payment->currency ?? 'USD',
                'status' => 'processing',
                'payment_type' => 'disbursement',
                'method' => 'stripe',
                'calculated_at' => now(),
            ]);

            // Stripe init
            $stripe = new StripeClient(config('services.stripe.secret'));

            $amount = (int) round($payoutAmount * 100);

            if ($amount <= 0) {
                Log::warning("Stripe amount invalid after conversion: {$assign->id}");
                $payout->update(['status' => 'failed']);
                return;
            }

            // Stripe transfer
            $transfer = $stripe->transfers->create([
                'amount' => $amount,
                'currency' => strtolower($payout->currency),
                'destination' => $inspector->profile->stripe_account_id,
                'description' => 'Inspection payout #' . $assign->id,
            ]);

            // success update payout
            $payout->update([
                'status' => 'paid',
                'is_disbursed' => true,
                'transaction_id' => $transfer->id,
                'stripe_transfer_id' => $transfer->id,
                'paid_at' => now(),
            ]);

            // sync payment
            $payment->update([
                'payout_status' => 'paid',
                'is_disbursed' => true,
            ]);

            // notify inspector
            try {
                $inspector->notify(new \App\Notifications\PlatformNotification([
                    'type'          => 'payout_disbursed',
                    'title'         => 'Payment Disbursed',
                    'message'       => 'Your payout of $' . number_format($payoutAmount, 2) . ' for inspection #' . $assign->id . ' has been disbursed via Stripe. Please allow 2–5 business days for it to reflect in your bank account.',
                    'booking_id'    => $assign->inspection_booking_id,
                    'sent_to_label' => 'inspector',
                ]));
            } catch (\Throwable $notifEx) {
                Log::warning('Inspector payout notification failed: ' . $notifEx->getMessage());
            }

            Log::info("Payout success", [
                'assign_id' => $assign->id,
                'payout_id' => $payout->id,
            ]);

        } catch (\Exception $e) {

            Log::error("Stripe payout failed", [
                'message' => $e->getMessage(),
                'assign_id' => $assign->id,
                'payment_id' => $payment->id,
            ]);

            if (isset($payout)) {
                $payout->update([
                    'status' => 'failed',
                    'is_disbursed' => false,
                ]);
            }

            $payment->update([
                'payout_status' => 'failed',
                'is_disbursed' => false,
            ]);
        }
    }
}