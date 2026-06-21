<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Profile;
use Illuminate\Http\Request;
use Stripe\StripeClient;
use Illuminate\Support\Facades\Log;

class StripeController extends Controller
{
    protected $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * =========================
     * CREATE CONNECT ACCOUNT
     * =========================
     */
    public function createConnectAccount($userId)
    {
        $user = User::with('profile')->findOrFail($userId);

        $account = $this->stripe->accounts->create([
            'type' => 'express',
            'country' => 'US',
            'email' => $user->email,
            'capabilities' => [
                'transfers' => ['requested' => true],
            ],
        ]);

        $user->profile()->update([
            'stripe_account_id' => $account->id,
            'stripe_onboarding_completed' => 0,
        ]);

        return response()->json([
            'success' => true,
            'account_id' => $account->id,
        ]);
    }

    /**
     * =========================
     * ONBOARDING LINK
     * =========================
     */
    public function onboarding($userId)
    {
        $user = User::with('profile')->findOrFail($userId);

        if (!$user->profile || !$user->profile->stripe_account_id) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe account not found'
            ], 404);
        }

        $accountLink = $this->stripe->accountLinks->create([
            'account' => $user->profile->stripe_account_id,
            'refresh_url' => url('/stripe/refresh'),
            'return_url'  => url('/stripe/success'),
            'type' => 'account_onboarding',
        ]);

        return response()->json([
            'success' => true,
            'url' => $accountLink->url,
        ]);
    }

    /**
     * =========================
     * SUCCESS CALLBACK
     * =========================
     */
    public function success()
    {
        return response()->json([
            'success' => true,
            'message' => 'Onboarding completed. Waiting for Stripe verification.'
        ]);
    }

   public function handleWebhook(Request $request)
{
    $payload   = $request->getContent();
    $sigHeader = $request->header('Stripe-Signature');
    $secret    = config('services.stripe.payout_webhook_secret');

    Log::info('🔥 Stripe Webhook HIT');

    try {
        $event = Webhook::constructEvent(
            $payload,
            $sigHeader,
            $secret
        );
    } catch (\Exception $e) {
        Log::error('❌ Stripe Signature Error: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Invalid signature'
        ], 400);
    }

    Log::info('📩 Event Type: ' . $event->type);

    /**
     * ==================================
     * HANDLE ACCOUNT / CAPABILITY UPDATE
     * ==================================
     */
    if (in_array($event->type, ['account.updated', 'capability.updated'])) {

        $account = $event->data->object;

        Log::info('📦 Stripe Account Event', [
            'account_id' => $account->id,
            'charges_enabled' => $account->charges_enabled ?? null,
            'payouts_enabled' => $account->payouts_enabled ?? null,
        ]);

        $profile = Profile::where('stripe_account_id', $account->id)->first();

        if (!$profile) {
            Log::error('❌ Profile not found for Stripe account: ' . $account->id);

            return response()->json([
                'success' => false,
                'message' => 'Profile not found'
            ], 404);
        }

        /**
         * =========================
         * ONBOARDING SUCCESS CHECK
         * =========================
         */
        $chargesEnabled = (bool) ($account->charges_enabled ?? false);
        $payoutsEnabled = (bool) ($account->payouts_enabled ?? false);

        if ($chargesEnabled && $payoutsEnabled) {

            if (!$profile->stripe_onboarding_completed) {

                $profile->update([
                    'stripe_onboarding_completed' => 1
                ]);

                Log::info('🎉 ONBOARDING COMPLETED', [
                    'profile_id' => $profile->id
                ]);
            }

        } else {
            Log::info('⏳ Stripe account NOT fully ready yet');
        }
    }

    return response()->json([
        'success' => true,
        'message' => 'Webhook processed successfully'
    ]);
}
}