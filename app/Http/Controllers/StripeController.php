<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Profile;
use App\Models\InspectionBooking;
use App\Models\InspectionPayment;
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

        // Duplicate prevent Check
        if ($user->profile?->stripe_account_id) {
            return response()->json([
                'success'                     => true,
                'account_id'                  => $user->profile->stripe_account_id,
                'stripe_onboarding_completed' => (int) $user->profile->stripe_onboarding_completed,
                'message'                     => 'Account already exists.'
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
                'refresh_url' => url('/api/v1/stripe/refresh/' . $userId),
                'return_url'  => url('/api/v1/stripe/success/' . $userId),
                'type'        => 'account_onboarding',
            ]);

            

            return response()->json([
                'success'                     => true,
                'url'                         => $accountLink->url,
                'stripe_onboarding_completed' => (int) $user->profile->stripe_onboarding_completed,
            ]);

        } catch (\Exception $e) {
            Log::error('Stripe Onboarding Error: ' . $e->getMessage());
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

            $user->profile->update([
                'stripe_onboarding_completed' => $isComplete ? 1 : 0
            ]);

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
                'refresh_url' => url('/api/v1/stripe/refresh/' . $userId),
                'return_url'  => url('/api/v1/stripe/success/' . $userId),
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

            // Sync Database State
            Profile::where('user_id', $userId)
                ->update(['stripe_onboarding_completed' => $isComplete ? 1 : 0]);

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

    public function handleWebhook(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.payout_webhook_secret') ?? env('PAYOUT_WEBHOOK_SECRET');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\Exception $e) {
            Log::error("Stripe Signature Error: " . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if (!in_array($event->type, ['account.updated', 'capability.updated', 'payment_intent.succeeded'])) {
            return response()->json(['ok' => true]);
        }

        $object = $event->data->object;

        // Payment Intent Success
        if ($event->type === 'payment_intent.succeeded') {
            $bookingId = $object->metadata->booking_id ?? null;
            $paymentId = $object->metadata->payment_id ?? null;

            if ($bookingId) {
                InspectionBooking::where('id', $bookingId)->update(['status' => 'paid']);
            }
            if ($paymentId) {
                InspectionPayment::where('id', $paymentId)->update(['status' => 'paid']);
            }

            return response()->json(['success' => true]);
        }

        // Connect Account Onboarding Check
        $accountId = $event->type === 'capability.updated'
            ? ($object->account ?? null)
            : ($object->id ?? null);

        if (!$accountId) {
            return response()->json(['ok' => true]);
        }

        $profile = Profile::where('stripe_account_id', $accountId)->first();

        if (!$profile) {
            return response()->json(['ok' => true]);
        }

        try {
            $account = $this->stripe->accounts->retrieve($accountId);
            $detailsSubmitted = (bool) $account->details_submitted;
            $payoutsEnabled   = (bool) $account->payouts_enabled;

            $isComplete = $detailsSubmitted && $payoutsEnabled;

            $profile->update([
                'stripe_onboarding_completed' => $isComplete ? 1 : 0
            ]);

            Log::info("Webhook Stripe Status Synced for Account: {$accountId}, Completed: {$isComplete}");

        } catch (\Exception $e) {
            Log::error("Stripe retrieve error in webhook: " . $e->getMessage());
        }

        return response()->json(['success' => true]);
    }


    public function getAccountDetails($userId)
    {
        $user = User::with('profile')->find($userId);

        if (!$user || !$user->profile?->stripe_account_id) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe account not found for this user.'
            ], 404);
        }

        try {
            $stripe = new StripeClient(config('services.stripe.secret'));
            
            $accountId = $user->profile->stripe_account_id;
            
            $account = $stripe->accounts->retrieve($accountId, []);

            return response()->json([
                'success' => true,
                'data' => [
                    'user_id'             => $user->id,
                    'stripe_account_id'   => $account->id,
                    'email'               => $account->email,
                    'details_submitted'   => (bool) $account->details_submitted,
                    'payouts_enabled'     => (bool) $account->payouts_enabled,
                    'charges_enabled'     => (bool) $account->charges_enabled,
                    'external_accounts'   => $account->external_accounts?->data ?? [],
                    'requirements'        => $account->requirements?->currently_due ?? [],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe execution failed: ' . $e->getMessage()
            ], 500);
        }
    }
}