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

        if (!$assign) return;

        $payment = $assign->inspectionBooking?->payment;

        if (!$payment) return;

        // refresh latest DB state
        $payment->refresh();

        // already processed guard
        if ($payment->payout_status !== 'processing') {
            return;
        }

        if (!empty($payment->stripe_id)) {
            return;
        }

        $inspector = $assign->inspector;

        if (
            !$inspector ||
            !$inspector->profile ||
            !$inspector->profile->stripe_onboarding_completed
        ) {
            $payment->update([
                'payout_status' => 'failed'
            ]);
            return;
        }

        try {
            $stripe = new StripeClient(config('services.stripe.secret'));

            $amount = (int) ($payment->total * 100);
            $inspectorAmount = (int) ($amount * 0.80);

            $transfer = $stripe->transfers->create([
                'amount'      => $inspectorAmount,
                'currency'    => 'usd',
                'destination' => $inspector->profile->stripe_account_id,
                'description' => 'Inspection #' . $assign->id,
            ]);

            $payment->update([
                'payout_status' => 'paid',
                'stripe_id'     => $transfer->id,
                'is_disbursed'  => true,
            ]);

        } catch (\Exception $e) {

            $payment->update([
                'payout_status' => 'failed'
            ]);

            Log::error('Payout failed: ' . $e->getMessage());
        }
    }
}