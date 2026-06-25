<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\InspectionAssign;
use App\Models\InspectionBooking;
use App\Models\InspectionPayment;
use App\Models\InspectionType;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AdminIconNotification;
use App\Notifications\PlatformNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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
                'stripe_id' => $paymentIntent->id,

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
                config('services.stripe.booking_webhook_secret')
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

            $admin = User::where('user_type', 'admin')->first();
            // Send notification to group or single user
            Notification::send($admin, new AdminIconNotification([
                'type'      => 'inspection_booking',
                'title'     => 'Inspection Booking',
                'message'   => 'A new Inspection Booking has been created.',
                'sender_id' => null,
            ]));
            return response('OK', 200);

        } catch (\Exception $e) {
            return response('Webhook Error: ' . $e->getMessage(), 400);
        }
    }

    public function statusBookingList(Request $request)
    {
        try {
            $filter = $request->query('filter');
            $user = Auth::user();


            // Base query with relationships
            $query = InspectionBooking::with(['payment', 'inspectionTypes','reschedule'])
                ->whereHas('payment', fn($q) => $q->where('status', 'paid'))
                ->whereDoesntHave('declines', function ($q) use ($user) {
                    $q->where('inspector_id', $user->id);
                })
                ->whereDoesntHave('inspectionAssign')
                ->latest();

            // Apply filter logic
            if ($filter === 'urgent') {
                $query->where('urgent_status', true);
            } elseif ($filter === 'rescheduled') {
                $query->where('isRescheduled', true);
            } elseif ($filter === 'new') {
                $query->limit(5);
            }

            $bookings = $query->get()->map(function ($booking) {
                $payment = $booking->payment;
                $reschedule = $booking->reschedule ?? null;
                $type = $booking->inspectionTypes->pluck('title')->toArray();
                $img =  $booking->inspectionTypes->pluck('img')->toArray();
                $price  = $booking->inspectionTypes->pluck('price')->toArray();

                return [
                    'id' => $booking->id,
                    'inspection_img' =>$img,
                    'inspection_type' => $type,
                    'inspection_price' => $price,
                    'property_address' => $booking->property_address,
                    'property_type' => $booking->property_type,
                    'property_size' => $booking->property_size,
                    'property_img' => $booking->property_img,
                    'scheduled_date' => $booking->scheduled_date->format('Y-m-d'),
                    'scheduled_time' => $booking->scheduled_time,
                    'urgent_status' => $booking->urgent_status,
                    'rescheduled_status' => $booking->isRescheduled,
                    'reschedule' => $reschedule,
                    'status' => $booking->status,
                    'note' => $booking->note,
                    'price' => $payment ? number_format($payment->subtotal, 2) : null,
                    'distance' => $booking->distance ?? null,
                    'estimate_time' => $booking->estimate_time ?? null,
                    'latitude' => $booking->latitude,
                    'longitude' => $booking->longitude,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $bookings,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Booking list fetch failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function rescheduleBookingList(Request $request)
    {
        try {
            $filter = $request->query('filter');
            $user = Auth::user();

            // Base query with relationships
            $query = InspectionAssign::with([
                'inspectionBooking:id,homeowner_id,property_address,property_type,property_img,scheduled_date,scheduled_time,urgent_status,status',
                'inspectionBooking.inspectionTypes:id,title',
                'inspector:id,first_name,last_name',
            ])
                ->where('status', $filter)
                ->latest();


            $inspections = $query->get()->map(function ($assign) {
                $booking = $assign->inspectionBooking;
                return [
                    'id'                => $assign->id,
                    'booking_id'        => $booking->id,
                    'inspection_type'   => $booking->inspectionTypes,
                    'property_address'  => $booking->property_address,
                    'property_type'     => $booking->property_type,
                    'property_img'      => $booking->property_img,
                    'scheduled_date'    => $booking->scheduled_date,
                    'scheduled_time'    => $booking->scheduled_time,
                    'urgent_status'     => $booking->urgent_status,
                    'status'            => $assign->status,
                    'inspector'         => $assign->inspector ? $assign->inspector->first_name.' '.$assign->inspector->last_name : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $inspections,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Upcoming inspections fetch failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve upcoming inspections.'
            ], 500);
        }
    }

    public function inspectionDetails(string $id)
    {
        try {
            $booking = InspectionBooking::with(['payment', 'inspectionTypes','inspectionAssign','reschedule'])
                ->findOrFail($id);

            $payment = $booking->payment;
            $assigned = $booking->inspectionAssign;
            $reschedule = $booking->reschedule;

            $response = [
                'id'                => $booking->id,
                'inspection_image'  => $booking->inspectionTypes->pluck('img')->toArray(),
                'inspection_types'  => $booking->inspectionTypes->pluck('title')->toArray(),
                'property_details'  => [
                    'address'       => $booking->property_address,
                    'type'          => $booking->property_type,
                    'size'          => $booking->property_size,
                    'latitude'      => $booking->latitude,
                    'longitude'     => $booking->longitude,
                ],
                'schedule'          => [
                    'date'          => optional($booking->scheduled_date)->format('Y-m-d'),
                    'time'          => $booking->scheduled_time,
                    'shift'         => $booking->scheduled_shift,
                ],
                'reschedule'         => [
                    'date'          => $reschedule->date ?? null,
                    'time'          => $reschedule->time ?? null,
                    'shift'         => $reschedule->shift ?? null,
                    'status'        => $reschedule->status ?? null,
                ],
                'Inspector Assigned' =>[
                    'id'  =>    $assigned->id ?? null,
                    'inspector_id' =>   $assigned->inspector_id ?? null,
                    'Inspector_name' =>   $assigned->name ?? null,
                ],
                'payment_breakdown' => [
                    'inspection_fee' => $payment ? number_format($payment->subtotal, 2) : null,
                    'urgent_fee'     => $booking->urgent_status ? number_format(50, 2) : null,
                    'total_payable'  => $payment ? number_format($payment->total, 2) : null,
                    'status'         => $payment ? $payment->status : 'unpaid',
                ],
                'note'              => $booking->note,
                'urgent_status'     => $booking->urgent_status,
                'status'            => $booking->status,
                'property_img'      => $booking->property_img,
            ];

            return response()->json([
                'success' => true,
                'data'    => $response,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Inspection details fetch failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function cancelBookingInspection($booking_id)
    {
        try {
            DB::beginTransaction();

            $booking = InspectionBooking::findOrFail($booking_id);
            $payment = InspectionPayment::where('inspection_booking_id', $booking_id)->firstOrFail();

            $hours = now()->diffInHours($booking->created_at);

            $cancellationFee = 0;
            $setting = Setting::first();
            if ($hours < 2) {
                $cancellationFee = $setting->last_minute_cancel_penalty ?? 75;
            } elseif ($hours < 24) {
                $cancellationFee = $setting->late_cancellation_penalty ?? 50;
            }

            $admin_share = $cancellationFee/2;
            $platform_fee = $admin_share;
            $inspector_share = $cancellationFee/2;

            // Calculate refund amount (total minus fee)
            $refundAmount = max(($payment->total - $cancellationFee), 0);

            // Trigger Stripe refund
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret') ?? env('STRIPE_SECRET'));

            $refund = $stripe->refunds->create([
                'payment_intent' => $payment->stripe_id,
                'amount'         => $refundAmount * 100, // cents
            ]);

            // Create New Payment Table
            InspectionPayment::create([
                'inspection_booking_id' => $booking->id,
                'subtotal'              => $refundAmount,
                'platform_fee'          => $platform_fee,
                'urgent_fee'            => 0,
                'inspector_share'       => $inspector_share,
                'admin_share'           => $admin_share,
                'total'                 => $refundAmount,
                'payment_type'          => 'refund',
                'trx_id'                => $refund->id, // Stripe refund ID
                'status'                => 'pending',
                'stripe_id'             => $payment->stripe_id, // original PaymentIntent ID
                'penalty_amount'        => $cancellationFee,
                'refunded_amount'       => $refundAmount,
                'is_disbursed'          => false,
            ]);


            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inspection cancelled. Refund initiated.',
                'refund'  => $refund,
                'cancellation_fee' => $cancellationFee,
                'refunded_amount' => $refundAmount
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel inspection.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function cancelHandleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                config('services.stripe.cancel_webhook_secret')
            );

            Log::info('event: ' . $event->type);


            if ($event->type === 'charge.refunded') {
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
            $admin = User::where('user_type', 'admin')->first();

            Notification::send($admin, new AdminIconNotification([
                'type'      => 'cancelled_booking',
                'title'     => 'Booking Inspection Cancelled',
                'message'   => 'A new Inspection Booking has been Cancelled.',
                'sender_id' => null,
            ]));

            return response('OK', 200);

        } catch (\Exception $e) {
            return response('Webhook Error: ' . $e->getMessage(), 400);
        }
    }
}
