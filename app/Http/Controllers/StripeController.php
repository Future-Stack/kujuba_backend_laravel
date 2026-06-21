<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Webhook;

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

        // prevent duplicate account creation
        if ($user->profile?->stripe_account_id) {
            return response()->json([
                'success' => true,
                'account_id' => $user->profile->stripe_account_id,
                'stripe_onboarding_completed' => (int) $user->profile->stripe_onboarding_completed,
                'message' => 'Already exists'
            ]);
        }

        $account = $this->stripe->accounts->create([
            'type' => 'express',
            'country' => 'US',
            'email' => $user->email,
            'capabilities' => [
                'transfers' => ['requested' => true],
            ],
        ]);

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'stripe_account_id' => $account->id,
                'stripe_onboarding_completed' => 0,
            ]
        );

        return response()->json([
            'success' => true,
            'account_id' => $account->id,
            'stripe_onboarding_completed' => 0,
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
     * SUCCESS
     * =========================
     */
    public function success()
    {
        return response()->json([
            'success' => true,
            'message' => 'Returned from onboarding. Waiting for Stripe verification.'
        ]);
    }

    /**
     * =========================
     * STRIPE WEBHOOK (FINAL FIXED)
     * =========================
     */
    public function handleWebhook(Request $request)
{
    $payload   = $request->getContent();
    $sigHeader = $request->header('Stripe-Signature');
    $secret    = config('services.stripe.payout_webhook_secret');

    Log::info("🔥 Stripe Webhook HIT");

    try {
        $event = \Stripe\Webhook::constructEvent(
            $payload,
            $sigHeader,
            $secret
        );
    } catch (\Exception $e) {
        Log::error("❌ Stripe Signature Error: " . $e->getMessage());
        return response()->json(['error' => 'Invalid signature'], 400);
    }

    Log::info("📩 Event Type: " . $event->type);

    // Only needed events
    if (!in_array($event->type, ['account.updated', 'capability.updated'])) {
        return response()->json(['ok' => true]);
    }

    $object = $event->data->object;

    // SAFE ACCOUNT ID
    $accountId = $object->id ?? null;

    if (!$accountId) {
        Log::warning("⚠️ Missing account ID");
        return response()->json(['ok' => true]);
    }

    // Find profile
    $profile = \App\Models\Profile::where('stripe_account_id', $accountId)->first();

    if (!$profile) {
        Log::warning("❌ Profile not found: {$accountId}");
        return response()->json(['ok' => true]);
    }

    // =========================
    // 🔥 IMPORTANT FIX: REFRESH ACCOUNT FROM STRIPE
    // =========================
    try {
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        $account = $stripe->accounts->retrieve($accountId, []);

    } catch (\Exception $e) {
        Log::error("❌ Stripe retrieve error: " . $e->getMessage());
        return response()->json(['ok' => true]);
    }

    // REAL VALUES FROM STRIPE
    $chargesEnabled = (bool) $account->charges_enabled;
    $payoutsEnabled = (bool) $account->payouts_enabled;

    Log::info("charges_enabled: " . json_encode($chargesEnabled));
    Log::info("payouts_enabled: " . json_encode($payoutsEnabled));

    // =========================
    // FINAL ONBOARDING LOGIC
    // =========================
    if ($chargesEnabled && $payoutsEnabled) {

        if ((int) $profile->stripe_onboarding_completed !== 1) {

            $profile->update([
                'stripe_onboarding_completed' => 1
            ]);

            Log::info("🎉 ONBOARDING COMPLETED: Profile ID {$profile->id}");
        }

    } else {

        // keep safe state
        if ($profile->stripe_onboarding_completed != 0) {
            $profile->update([
                'stripe_onboarding_completed' => 0
            ]);
        }

        Log::info("⏳ Stripe onboarding NOT completed yet");
    }

    return response()->json(['success' => true]);
}
}