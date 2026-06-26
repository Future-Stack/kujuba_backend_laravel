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

        // prevent duplicate account creation
        if ($user->profile?->stripe_account_id) {
            return response()->json([
                'success' => true,
                'account_id' => $user->profile->stripe_account_id,
                'stripe_onboarding_completed' => (int) $user->profile->stripe_onboarding_completed,
                'message' => 'Already exists'
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
                'type' => 'express',
                'country' => 'US',
                'email' => $user->email,
                'capabilities' => [
                    'transfers' => ['requested' => true],
                ],
            ]);

            restore_error_handler();

        } catch (\Exception $e) {
            restore_error_handler(); 
            
            \Log::error('Stripe Account Creation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Stripe execution failed: ' . $e->getMessage()
            ], 500);
        }

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
                'refresh_url' => url('/stripe/refresh'),
                'return_url'  => url('/stripe/success'),
                'type'        => 'account_onboarding',
            ]);

            return response()->json([
                'success' => true,
                'url'     => $accountLink->url,
            ]);

        } catch (\Stripe\Exception\InvalidRequestException $e) {
            \Log::error('Stripe Onboarding Invalid Account: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'The stored Stripe account does not exist or belongs to another environment. Please recreate the account.',
                'error_code' => 'stripe_account_mismatch'
            ], 400);

        } catch (\Exception $e) {
            \Log::error('Stripe Onboarding General Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong with Stripe onboarding.'
            ], 500);
        }
    }

    // SUCCESS
  
    public function success()
    {
        return response()->json([
            'success' => true,
            'message' => 'Returned from onboarding. Waiting for Stripe verification.'
        ]);
    }

    // STRIPE WEBHOOK (FINAL FIXED)
    

    public function handleWebhook(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.payout_webhook_secret');

        Log::info("Stripe Webhook HIT");

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                $secret
            );
        } catch (\Exception $e) {
            Log::error("Stripe Signature Error: " . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        Log::info("Event Type: " . $event->type);

        if (!in_array($event->type, ['account.updated', 'capability.updated', 'payment_intent.succeeded'])) {
            return response()->json(['ok' => true]);
        }

        $object = $event->data->object;

        // Handle Payment Success
        if ($event->type === 'payment_intent.succeeded') {
            $bookingId = $object->metadata->booking_id ?? null;
            $paymentId = $object->metadata->payment_id ?? null;

            Log::info("Payment Succeeded for Booking ID: {$bookingId}, Payment ID: {$paymentId}");

            if ($bookingId) {
                \App\Models\InspectionBooking::where('id', $bookingId)->update(['status' => 'paid']);
            }

            if ($paymentId) {
                \App\Models\InspectionPayment::where('id', $paymentId)->update(['status' => 'paid']);
            }

            return response()->json(['success' => true]);
        }

        // Handle Connect Account Onboarding
        $accountId = $event->type === 'capability.updated' ? ($object->account ?? null) : ($object->id ?? null);

        if (!$accountId) {
            Log::warning("Missing account ID");
            return response()->json(['ok' => true]);
        }

        $profile = \App\Models\Profile::where('stripe_account_id', $accountId)->first();

        if (!$profile) {
            Log::warning("Profile not found: {$accountId}");
            return response()->json(['ok' => true]);
        }

        try {
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
            $account = $stripe->accounts->retrieve($accountId, []);
        } catch (\Exception $e) {
            Log::error("Stripe retrieve error: " . $e->getMessage());
            return response()->json(['ok' => true]);
        }

        $detailsSubmitted = (bool) $account->details_submitted;
        $payoutsEnabled   = (bool) $account->payouts_enabled;

        Log::info("details_submitted: " . json_encode($detailsSubmitted));
        Log::info("payouts_enabled: " . json_encode($payoutsEnabled));

        if ($detailsSubmitted && $payoutsEnabled) {
            if ((int) $profile->stripe_onboarding_completed !== 1) {
                $profile->update([
                    'stripe_onboarding_completed' => 1
                ]);
                Log::info("ONBOARDING COMPLETED: Profile ID {$profile->id}");
            }
        } else {
            if ($profile->stripe_onboarding_completed != 0) {
                $profile->update([
                    'stripe_onboarding_completed' => 0
                ]);
            }
            Log::info("Stripe onboarding NOT completed yet");
        }

        return response()->json(['success' => true]);
    }
}