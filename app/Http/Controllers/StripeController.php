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

    
    public function createConnectAccount($userId)
    {
        $user = User::with('profile')->findOrFail($userId);

        // Duplicate prevent
        if ($user->profile?->stripe_account_id) {
            return response()->json([
                'success'                     => true,
                'account_id'                  => $user->profile->stripe_account_id,
                'stripe_onboarding_completed' => (int) $user->profile->stripe_onboarding_completed,
                'message'                     => 'Already exists'
            ]);
        }

        try {
            set_error_handler(function ($errno, $errstr) {
                if ($errno === E_USER_WARNING && str_contains($errstr, 'Accounts v2')) {
                    return true;
                }
                return false;
            });

            $account = $this->stripe->accounts->create([
                'type'         => 'express',
                'country'      => 'US',
                'email'        => $user->email,
                'capabilities' => [
                    'transfers' => ['requested' => true],
                ],
            ]);

            restore_error_handler();

        } catch (\Exception $e) {
            restore_error_handler();
            Log::error('Stripe Account Creation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Stripe execution failed: ' . $e->getMessage()
            ], 500);
        }

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'stripe_account_id'           => $account->id,
                'stripe_onboarding_completed' => 0,
            ]
        );

        return response()->json([
            'success'                     => true,
            'account_id'                  => $account->id,
            'stripe_onboarding_completed' => 0,
        ]);
    }

    // ONBOARDING LINK

    public function onboarding($userId)
    {
        $user = User::with('profile')->findOrFail($userId);

        if (!$user->profile?->stripe_account_id) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe account not found in database.'
            ], 404);
        }

        try {
            $accountLink = $this->stripe->accountLinks->create([
                'account'     => $user->profile->stripe_account_id,
                'refresh_url' => url('/v1/stripe/refresh/' . $userId),
                'return_url'  => url('/v1/stripe/success/' . $userId),
                'type'        => 'account_onboarding',
            ]);

            return response()->json([
                'success' => true,
                'url'     => $accountLink->url,
            ]);

        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Log::error('Stripe Onboarding Invalid Account: ' . $e->getMessage());
            return response()->json([
                'success'    => false,
                'message'    => 'Stripe account mismatch. Please recreate the account.',
                'error_code' => 'stripe_account_mismatch'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Stripe Onboarding General Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong with Stripe onboarding.'
            ], 500);
        }
    }

    
    public function success($userId)
    {
        $user = User::with('profile')->findOrFail($userId);

        if (!$user->profile?->stripe_account_id) {
            return response()->json([
                'success'                     => false,
                'message'                     => 'No Stripe account found.',
                'stripe_onboarding_completed' => 0,
            ], 404);
        }

        try {
            $account = $this->stripe->accounts->retrieve(
                $user->profile->stripe_account_id
            );

            $detailsSubmitted = (bool) $account->details_submitted;
            $payoutsEnabled   = (bool) $account->payouts_enabled;
            $isComplete       = $detailsSubmitted && $payoutsEnabled;

            Log::info("Stripe success hit for user #{$userId}", [
                'details_submitted' => $detailsSubmitted,
                'payouts_enabled'   => $payoutsEnabled,
            ]);

            // DB update
            if ($isComplete && (int) $user->profile->stripe_onboarding_completed !== 1) {
                $user->profile->update(['stripe_onboarding_completed' => 1]);
                Log::info("Onboarding COMPLETED via success URL: user #{$userId}");
            }

            return response()->json([
                'success'                     => true,
                'message'                     => $isComplete
                    ? 'Stripe onboarding completed successfully.'
                    : 'Returned from Stripe. Onboarding not yet complete.',
                'stripe_onboarding_completed' => $isComplete ? 1 : 0,
                'details_submitted'           => $detailsSubmitted,
                'payouts_enabled'             => $payoutsEnabled,
            ]);

        } catch (\Exception $e) {
            Log::error("Stripe success verify error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Could not verify Stripe status.',
            ], 500);
        }
    }

    public function refresh($userId)
    {
        $user = User::with('profile')->findOrFail($userId);

        if (!$user->profile?->stripe_account_id) {
            return response()->json([
                'success' => false,
                'message' => 'No Stripe account found.',
            ], 404);
        }

        try {
            $accountLink = $this->stripe->accountLinks->create([
                'account'     => $user->profile->stripe_account_id,
                'refresh_url' => url('/v1/stripe/refresh/' . $userId),
                'return_url'  => url('/v1/stripe/success/' . $userId),
                'type'        => 'account_onboarding',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'New onboarding link generated.',
                'url'     => $accountLink->url,
            ]);

        } catch (\Exception $e) {
            Log::error("Stripe refresh error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Could not generate new onboarding link.',
            ], 500);
        }
    }

    public function onboardingStatus($userId)
    {
        $user = User::with('profile')->findOrFail($userId);

        if (!$user->profile?->stripe_account_id) {
            return response()->json([
                'success'                     => false,
                'message'                     => 'No Stripe account found.',
                'stripe_onboarding_completed' => 0,
            ], 404);
        }

        try {
            $account = $this->stripe->accounts->retrieve(
                $user->profile->stripe_account_id
            );

            $detailsSubmitted = (bool) $account->details_submitted;
            $payoutsEnabled   = (bool) $account->payouts_enabled;
            $isComplete       = $detailsSubmitted && $payoutsEnabled;

            // DB sync 
            $user->profile->update([
                'stripe_onboarding_completed' => $isComplete ? 1 : 0,
            ]);

            return response()->json([
                'success'                     => true,
                'stripe_account_id'           => $account->id,
                'details_submitted'           => $detailsSubmitted,
                'payouts_enabled'             => $payoutsEnabled,
                'stripe_onboarding_completed' => $isComplete ? 1 : 0,
            ]);

        } catch (\Exception $e) {
            Log::error("Stripe onboarding status check error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Could not fetch Stripe account status.',
            ], 500);
        }
    }

    // WEBHOOK
    
    public function handleWebhook(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = env('PAYOUT_WEBHOOK_SECRET');

        Log::info("Stripe Webhook HIT");

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\Exception $e) {
            Log::error("Stripe Signature Error: " . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        Log::info("Event Type: " . $event->type);

        if (!in_array($event->type, ['account.updated', 'capability.updated', 'payment_intent.succeeded'])) {
            return response()->json(['ok' => true]);
        }

        $object = $event->data->object;

        // Payment intent
        if ($event->type === 'payment_intent.succeeded') {
            $bookingId = $object->metadata->booking_id ?? null;
            $paymentId = $object->metadata->payment_id ?? null;

            Log::info("Payment Succeeded — Booking: {$bookingId}, Payment: {$paymentId}");

            if ($bookingId) {
                \App\Models\InspectionBooking::where('id', $bookingId)->update(['status' => 'paid']);
            }
            if ($paymentId) {
                \App\Models\InspectionPayment::where('id', $paymentId)->update(['status' => 'paid']);
            }

            return response()->json(['success' => true]);
        }

        // Connect account onboarding
        $accountId = $event->type === 'capability.updated'
            ? ($object->account ?? null)
            : ($object->id ?? null);

        if (!$accountId) {
            Log::warning("Missing account ID in webhook");
            return response()->json(['ok' => true]);
        }

        $profile = \App\Models\Profile::where('stripe_account_id', $accountId)->first();

        if (!$profile) {
            Log::warning("Profile not found for account: {$accountId}");
            return response()->json(['ok' => true]);
        }

        try {
            $account = $this->stripe->accounts->retrieve($accountId);
        } catch (\Exception $e) {
            Log::error("Stripe retrieve error: " . $e->getMessage());
            return response()->json(['ok' => true]);
        }

        $detailsSubmitted = (bool) $account->details_submitted;
        $payoutsEnabled   = (bool) $account->payouts_enabled;

        Log::info("Webhook account status", [
            'account_id'        => $accountId,
            'details_submitted' => $detailsSubmitted,
            'payouts_enabled'   => $payoutsEnabled,
        ]);

        if ($detailsSubmitted && $payoutsEnabled) {
            if ((int) $profile->stripe_onboarding_completed !== 1) {
                $profile->update(['stripe_onboarding_completed' => 1]);
                Log::info("ONBOARDING COMPLETED via webhook: Profile #{$profile->id}");
            }
        } else {
            if ((int) $profile->stripe_onboarding_completed !== 0) {
                $profile->update(['stripe_onboarding_completed' => 0]);
            }
            Log::info("Onboarding not yet complete: {$accountId}");
        }

        return response()->json(['success' => true]);
    }
}