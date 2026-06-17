<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\InspectionBooking;
use App\Models\InspectionPayment;
use App\Models\InspectionType;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\StripeClient;

class InspectionBookingRequestCotroller extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'inspection_type_ids' => 'required|array|min:1',
            'inspection_type_ids.*' => 'required|integer|exists:inspection_types,id',
            'property_address' => 'required|string|max:255',
            'property_type' => 'required|string|max:255',
            'property_size' => 'required|string|max:255',
            'note' => 'nullable|string',
            'property_img' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'scheduled_date' => 'required|date_format:Y-m-d',
            'scheduled_time' => 'required|date_format:H:i',
            'scheduled_shift' => 'required|string|max:255',
            'urgent_status' => 'nullable|boolean',
            'payment_method_id' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $inspectionTypes = InspectionType::whereIn('id', $request->inspection_type_ids)->get();
        $subtotal = $inspectionTypes->sum('price');

        $platformFee = Setting::first()->platform_commission ?? 20.00;
        $urgentFee = Setting::first()->urgent_inspection_fee ?? 50.00;

        if ($request->urgent_status) {
            $total = $subtotal + $platformFee + $urgentFee;
            $inspector_share = $subtotal + $urgentFee;
        } else {
            $total = $subtotal + $platformFee;
            $inspector_share = $subtotal;
        }


        DB::beginTransaction();

        try {
            $userId = auth()->id() ?? $request->homeowner_id;

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized user access context.'
                ], 401);
            }

            $stripeSecret = config('services.stripe.secret') ?? env('STRIPE_SECRET');

            if (!$stripeSecret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe API secret key is missing in your .env file.'
                ], 500);
            }

            $imagePath = null;
            if ($request->hasFile('property_img')) {
                $imagePath = $request->file('property_img')->store('inspections', 'public');
            }

            $booking = InspectionBooking::create([
                'homeowner_id' => $userId,
                'property_address' => $request->property_address,
                'property_type' => $request->property_type,
                'property_size' => $request->property_size,
                'note' => $request->note,
                'property_img' => $imagePath,
                'booking_date' => now()->toDateString(),
                'scheduled_date' => $request->scheduled_date,
                'scheduled_time' => $request->scheduled_time,
                'scheduled_shift' => $request->scheduled_shift,
                'urgent_status' => $request->urgent_status ? 1 : 0,
                'status' => 'pending',
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'isRescheduled' => 0
            ]);

            $booking->inspectionTypes()->attach($request->inspection_type_ids);

            $payment = InspectionPayment::create([
                'inspection_booking_id' => $booking->id,
                'subtotal' => $subtotal,
                'platform_fee' => $platformFee,
                'inspector_share' => $inspector_share,
                'admin_share' => $platformFee,
                'urgent_fee' => $request->urgent_status ? $urgentFee : 0.00,
                'total' => $total,
                'trx_id' => null,
                'status' => 'pending',
                'urgentStatus' => $request->urgent_status ? '1' : '0',
                'stripe_id' => null,
                'is_disbursed' => false,
                'penalty_amount' => 0.00,
                'refunded_amount' => 0.00
            ]);

            //Stripe Payment Flow

            $stripe = new StripeClient(config('services.stripe.secret'));

            $paymentIntent = $stripe->paymentIntents->create([
                'amount' => intval($total * 100), // cents
                'currency' => 'usd',

                'description' => 'Inspection Booking',

                'metadata' => [
                    'type' => 'booking',
                    'booking_amount' => $total,
                    'payment_id' => $payment->id,
                ],

                // Enables cards, wallets, etc automatically
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            $payment->update([
                'trx_id' => $paymentIntent->id,
                'stripe_id' => $paymentIntent->client_secret,

            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully.',
                'amount' => $total,
                'stripe' => $paymentIntent,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Booking Store Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create Booking.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function BookingSuccess(Request $request)
    {
        return response()->json([
            'success' => true,
            'session_id' => $request->query('session_id'),
            'message' => 'Booking payment successful. Final confirmation will be handled by webhook.',
        ]);
    }

    public function BookingCancel()
    {
        return response()->json([
            'success' => false,
            'message' => 'Payment cancelled by user.',
        ]);
    }

    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                config('services.stripe.webhook_secret')
            );

            Log::info('event: ' . $event->type);


            if ($event->type === 'payment_intent.succeeded') {
                $intent = $event->data->object;
                $paymentId = $intent->metadata->payment_id ?? null;

                Log::info("Payment ID: " . $paymentId);

                if ($paymentId) {
                    $payment = InspectionPayment::find($paymentId);

                    if ($payment && $payment->status !== 'paid') {
                        $payment->update([
                            'status' => 'paid',
                        ]);
                    }
                }
            }
            return response('OK', 200);

        } catch (\Exception $e) {
            return response('Webhook Error: ' . $e->getMessage(), 400);
        }
    }
}
