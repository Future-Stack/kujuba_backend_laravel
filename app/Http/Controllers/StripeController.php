<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Profile;
use Illuminate\Http\Request;
use Stripe\StripeClient;
use Stripe\Webhook;
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

        if (!$user->profile?->stripe_account_id) {
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

    /**
     * =========================
     * WEBHOOK HANDLER
     * =========================
     */
    public function handleWebhook(Request $request)
{
    $payload = $request->getContent();
    $sigHeader = $request->header('Stripe-Signature');
    $secret = config('services.stripe.webhook_secret');

    // =========================
    // DEBUG LOG 1: webhook hit
    // =========================
    \Log::info('🔥 Stripe Webhook HIT');

    try {
        $event = \Stripe\Webhook::constructEvent(
            $payload,
            $sigHeader,
            $secret
        );
    } catch (\Exception $e) {

        \Log::error('❌ Stripe Signature Error: ' . $e->getMessage());

        return response('Invalid signature', 400);
    }

    // =========================
    // DEBUG LOG 2: event type
    // =========================
    \Log::info('📩 Event Type: ' . $event->type);

    /**
     * =========================
     * ACCOUNT UPDATED EVENT
     * =========================
     */
    if ($event->type === 'account.updated') {

        $account = $event->data->object;

        \Log::info('📦 Account ID: ' . $account->id);
        \Log::info('⚡ Charges Enabled: ' . ($account->charges_enabled ? 'true' : 'false'));
        \Log::info('⚡ Payouts Enabled: ' . ($account->payouts_enabled ? 'true' : 'false'));

        if (!empty($account->charges_enabled) && !empty($account->payouts_enabled)) {

            \Log::info('✅ Updating profile onboarding status');

            Profile::where('stripe_account_id', $account->id)
                ->update([
                    'stripe_onboarding_completed' => 1
                ]);

            \Log::info('🎉 Profile updated successfully');
        } else {
            \Log::info('⏳ Account not fully ready yet');
        }
    }

    return response()->json([
        'success' => true,
        'message' => 'Webhook processed'
    ]);
}
}