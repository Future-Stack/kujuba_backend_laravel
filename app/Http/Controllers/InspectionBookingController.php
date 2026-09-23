<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\InspectionBooking;
use App\Models\InspectionPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use App\Models\InspectionType;
use Stripe\Transfer;
use App\Models\Profile;
use App\Models\User;
use App\Notifications\PlatformNotification;


class InspectionBookingController extends Controller
{

    public function index(Request $request)
    {
        try {
            $userId = auth()->id() ?? $request->homeowner_id;

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized user contextual verification failed.'
                ], 401);
            }

            $tabStatus = $request->query('tab', 'active');

            $query = InspectionBooking::with(['inspectionTypes', 'payment'])
                                    ->where('homeowner_id', $userId);

            if ($tabStatus === 'active') {
                $query->whereIn('status', ['pending', 'assigned', 'in_progress']);
            } elseif ($tabStatus === 'upcoming') {
                $query->where('scheduled_date', '>', now()->toDateString())
                    ->whereNotIn('status', ['canceled', 'completed']);
            } elseif ($tabStatus === 'canceled') {
                $query->where('status', 'canceled');
            }

            $bookings = $query->orderBy('scheduled_date', 'asc')
                            ->orderBy('scheduled_time', 'asc')
                            ->get();

            $formattedData = $bookings->map(function ($booking) {
                return [
                    'id'               => $booking->id,
                    'booking_uid'      => 'INS-' . (1000 + $booking->id),
                    'property_address' => $booking->property_address,
                    'property_type'    => $booking->property_type,
                    'property_size'    => $booking->property_size,
                    'note'             => $booking->note,
                    'property_img'     => $booking->property_img ? asset('storage/' . $booking->property_img) : asset('defaults/placeholder.png'),
                    'scheduled_date'   => $booking->scheduled_date ? $booking->scheduled_date->format('Y-m-d') : null,
                    'scheduled_time'   => $booking->scheduled_time,
                    'scheduled_shift'  => $booking->scheduled_shift,
                    'urgent_status'    => (bool) $booking->urgent_status,
                    'status'           => $booking->status,
                    'isRescheduled'    => (int) $booking->isRescheduled,
                    'payment' => $booking->payment ? [
                        'subtotal'     => floatval($booking->payment->subtotal),
                        'platform_fee' => floatval($booking->payment->platform_fee),
                        'total'        => floatval($booking->payment->total),
                        'trx_id'       => $booking->payment->trx_id,
                        'status'       => $booking->payment->status,
                    ] : null,
                    'inspection_types' => $booking->inspectionTypes->map(function ($type) {
                        return [
                            'id'         => $type->id,
                            'title'      => $type->title,
                            'short_desc' => $type->short_desc,
                            'price'      => floatval($type->price),
                            'img'        => $type->img ? asset('storage/' . $type->img) : null,
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Inspections list data structural nodes retrieved successfully.',
                'active_tab' => $tabStatus,
                'count'   => $formattedData->count(),
                'data'    => $formattedData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to parse listing arrays stream: ' . $e->getMessage()
            ], 500);
        }
    }


    public function homeownerPendingBookings(Request $request)
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized user contextual verification failed.'
                ], 401);
            }

            $bookings = InspectionBooking::with(['inspectionTypes', 'payment'])
                                    ->where('homeowner_id', $userId)
//                                   ->where('status', 'confirmed')
                                    ->whereHas('payment', function ($q) {
                                        $q->where('status', 'paid');
                                    })
                                    ->whereDoesntHave('inspectionAssign')
                                    ->latest()
                                    ->get();

            $formattedData = $bookings->map(function ($booking) {
                return [
                    'id'               => $booking->id,
                    'booking_uid'      => 'INS-' . (1000 + $booking->id),
                    'property_address' => $booking->property_address,
                    'property_type'    => $booking->property_type,
                    'property_size'    => $booking->property_size,
                    'note'             => $booking->note,
                    'property_img'     => $booking->property_img ? asset('storage/' . $booking->property_img) : asset('defaults/placeholder.png'),
                    'scheduled_date'   => $booking->scheduled_date ? $booking->scheduled_date->format('Y-m-d') : null,
                    'scheduled_time'   => $booking->scheduled_time,
                    'scheduled_shift'  => $booking->scheduled_shift,
                    'urgent_status'    => (bool) $booking->urgent_status,
                    'status'           => $booking->status,
                    'isRescheduled'    => (int) $booking->isRescheduled,
                    'payment' => $booking->payment ? [
                        'subtotal'     => floatval($booking->payment->subtotal),
                        'platform_fee' => floatval($booking->payment->platform_fee),
                        'total'        => floatval($booking->payment->total),
                        'trx_id'       => $booking->payment->trx_id,
                        'status'       => $booking->payment->status,
                    ] : null,
                    'inspection_types' => $booking->inspectionTypes->map(function ($type) {
                        return [
                            'id'         => $type->id,
                            'title'      => $type->title,
                            'short_desc' => $type->short_desc,
                            'price'      => floatval($type->price),
                            'img'        => $type->img ? asset('storage/' . $type->img) : null,
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Pending bookings list retrieved successfully.',
                'count'   => $formattedData->count(),
                'data'    => $formattedData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending bookings: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'inspection_type_ids'   => 'required|array|min:1',
            'inspection_type_ids.*' => 'required|integer|exists:inspection_types,id',
            'property_address'      => 'required|string|max:255',
            'property_type'         => 'required|string|max:255',
            'property_size'         => 'required|string|max:255',
            'note'                  => 'nullable|string',
            'property_img'          => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'scheduled_date'        => 'required|date_format:Y-m-d',
            'scheduled_time'        => 'required|date_format:H:i',
            'scheduled_shift'       => 'required|string|max:255',
            'urgent_status'         => 'nullable|boolean',

            'payment_method_id'     => 'nullable|string',

            'latitude'              => 'nullable|numeric',
            'longitude'             => 'nullable|numeric',
        ]);

        $inspectionTypes = InspectionType::whereIn('id', $request->inspection_type_ids)->get();
        $subtotal = $inspectionTypes->sum('price');

        $platformFee = 20.00;

        $total = $subtotal + $platformFee;

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

            Stripe::setApiKey($stripeSecret);

            $amountInCents = intval(round($total * 100));

            $paymentIntent = PaymentIntent::create([
                'amount' => $amountInCents,
                'currency' => 'usd',
                'payment_method' => $request->payment_method_id,
                'confirm' => true,
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never',
                ],
                'return_url' => 'https://stripe.com/stripe-return',
                'description' => 'Inspection Booking Payment by User ID: ' . $userId,
            ]);

            if ($paymentIntent->status !== 'succeeded') {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe payment verification failed. Status: ' . $paymentIntent->status
                ], 402);
            }

            $imagePath = null;
            if ($request->hasFile('property_img')) {
                $imagePath = $request->file('property_img')->store('inspections', 'public');
            }

            $lat = $request->latitude;
            $lng = $request->longitude;

            if (is_null($lat) || is_null($lng)) {
                $homeowner = User::with('profile')->find($userId);
                if ($homeowner && $homeowner->profile) {
                    $lat = $lat ?? $homeowner->profile->latitude;
                    $lng = $lng ?? $homeowner->profile->longitude;
                }

                if ((is_null($lat) || is_null($lng)) && !empty($request->zip_code)) {
                    $coords = \App\Services\GeoLocationService::getCoordinatesByZipCode($request->zip_code);
                    if ($coords) {
                        $lat = $lat ?? $coords['latitude'];
                        $lng = $lng ?? $coords['longitude'];
                    }
                }
            }

            $booking = InspectionBooking::create([
                'homeowner_id'     => $userId,
                'property_address' => $request->property_address,
                'property_type'    => $request->property_type,
                'property_size'    => $request->property_size,
                'note'             => $request->note,
                'property_img'     => $imagePath,
                'booking_date'     => now()->toDateString(),
                'scheduled_date'   => $request->scheduled_date,
                'scheduled_time'   => $request->scheduled_time,
                'scheduled_shift'  => $request->scheduled_shift,
                'urgent_status'    => $request->urgent_status ? 1 : 0,
                'status'           => 'pending',
                'latitude'         => $lat,
                'longitude'        => $lng,
                'isRescheduled'    => 0
            ]);

            $booking->inspectionTypes()->attach($request->inspection_type_ids);

            InspectionPayment::create([
                'inspection_booking_id' => $booking->id,
                'subtotal'              => $subtotal,
                'platform_fee'          => $platformFee,
                'total'                 => $total,
                'trx_id'                => $paymentIntent->id,
                'status'                => 'paid',
                'urgentStatus'          => $request->urgent_status ? '1' : '0',
                'stripe_id'             => $paymentIntent->id,
                'is_disbursed'          => false,
                'penalty_amount'        => 0.00,
                'refunded_amount'       => 0.00
            ]);

            DB::commit();

            // Notify homeowner via push notification & in-app database notification
            try {
                $homeowner = User::find($userId);
                if ($homeowner) {
                    $homeowner->notify(new PlatformNotification([
                        'type'       => 'booking_confirmed',
                        'title'      => 'Booking Confirmed!',
                        'message'    => "Your inspection booking #{$booking->id} has been placed and paid successfully.",
                        'booking_id' => $booking->id,
                        'sender_id'  => $userId,
                    ]));
                }
            } catch (\Throwable $notiEx) {
                Log::warning('Homeowner push notification failed in InspectionBookingController: ' . $notiEx->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Inspection Request Processed & Charged Successfully.',
                'data'    => $booking->load('payment', 'inspectionTypes')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Booking & Stripe Exception: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Process Failed: ' . $e->getMessage(),
                'line'    => $e->getLine()
            ], 500);
        }
    }

    public function completeInspectionAndPayout($bookingId)
    {
        DB::beginTransaction();

        try {
            $booking = InspectionBooking::with(['payment', 'assignment'])->findOrFail($bookingId);

            if ($booking->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'This inspection booking is already marked as completed.'
                ], 400);
            }

            if ($booking->payment && $booking->payment->is_disbursed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Funds for this booking have already been disbursed to the inspector.'
                ], 400);
            }

            $inspectorId = null;
            if ($booking->assignment) {
                $inspectorId = $booking->assignment->inspector_id;
            }

            if (!$inspectorId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No inspector is assigned to this booking yet.'
                ], 404);
            }

            $inspectorProfile = Profile::where('user_id', $inspectorId)->first();

            if (!$inspectorProfile || !$inspectorProfile->stripe_account_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'The assigned inspector has not completed their Stripe onboarding setup.'
                ], 400);
            }

            $totalCharged = $booking->payment ? $booking->payment->total : 120.00;
            $platformFee  = $booking->payment ? $booking->payment->platform_fee : 20.00;
            $payoutAmount = $totalCharged - $platformFee;

            if ($payoutAmount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid payout amount configuration. Transfer aborted.'
                ], 400);
            }

            $stripeSecret = config('services.stripe.secret') ?? env('STRIPE_SECRET');
            \Stripe\Stripe::setApiKey($stripeSecret);

            $amountInCents = intval(round($payoutAmount * 100));
            $transferId = null;

            try {
                $charge = \Stripe\Charge::create([
                    'amount' => $amountInCents,
                    'currency' => 'usd',
                    'source' => 'tok_visa',
                    'destination' => [
                        'account' => $inspectorProfile->stripe_account_id,
                    ],
                    'description' => 'Payout for Completed Inspection Booking ID: ' . $booking->id,
                ]);

                $transferId = $charge->id;

            } catch (\Stripe\Exception\InvalidRequestException $e) {
                if (config('app.env') !== 'production' && str_contains($e->getMessage(), 'capability')) {
                    $transferId = 'ch_sandbox_bypass_' . Str::random(10);
                } else {
                    throw $e;
                }
            }


            $booking->update([
                'status' => 'completed'
            ]);

            if ($booking->assignment) {
                $booking->assignment->update([
                    'status' => 'completed'
                ]);
            }

            if ($booking->payment) {
                $booking->payment->update([
                    'is_disbursed' => true,
                    'stripe_id' => $transferId
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inspection status marked as completed and funds successfully processed for the inspector!',
                'transfer_id' => $transferId,
                'payout_amount' => $payoutAmount
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payout Transfer Error for Booking ' . $bookingId . ': ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Payout Process Failed: ' . $e->getMessage(),
                'line'    => $e->getLine()
            ], 500);
        }
    }

    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'inspection_type_ids'   => 'required|array|min:1',
    //         'inspection_type_ids.*' => 'required|integer|exists:inspection_types,id',
    //         'property_address'      => 'required|string|max:255',
    //         'property_type'         => 'required|string|max:255',
    //         'property_size'         => 'required|string|max:255',
    //         'note'                  => 'nullable|string',
    //         'property_img'          => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
    //         'scheduled_date'        => 'required|date_format:Y-m-d',
    //         'scheduled_time'        => 'required|date_format:H:i',
    //         'scheduled_shift'       => 'required|string|max:255',
    //         'urgent_status'         => 'nullable|boolean',

    //         'subtotal'              => 'required|numeric',
    //         'platform_fee'          => 'required|numeric',
    //         'total'                 => 'required|numeric',
    //         'trx_id'                => 'nullable|string',

    //         'latitude'              => 'nullable|numeric',
    //         'longitude'             => 'nullable|numeric',
    //     ]);

    //     DB::beginTransaction();

    //     try {
    //         $userId = auth()->id() ?? $request->homeowner_id;

    //         if (!$userId) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Unauthorized user access context.'
    //             ], 401);
    //         }

    //         $imagePath = null;
    //         if ($request->hasFile('property_img')) {
    //             $imagePath = $request->file('property_img')->store('inspections', 'public');
    //         }

    //         $booking = InspectionBooking::create([
    //             'homeowner_id'     => $userId,
    //             'property_address' => $request->property_address,
    //             'property_type'    => $request->property_type,
    //             'property_size'    => $request->property_size,
    //             'note'             => $request->note,
    //             'property_img'     => $imagePath,
    //             'booking_date'     => now()->toDateString(),
    //             'scheduled_date'   => $request->scheduled_date,
    //             'scheduled_time'   => $request->scheduled_time,
    //             'scheduled_shift'  => $request->scheduled_shift,
    //             'urgent_status'    => $request->urgent_status ? 1 : 0,
    //             'status'           => 'pending',
    //             'latitude'         => $request->latitude,
    //             'longitude'        => $request->longitude,
    //             'isRescheduled'    => 0
    //         ]);

    //         $booking->inspectionTypes()->attach($request->inspection_type_ids);

    //         InspectionPayment::create([
    //             'inspection_booking_id' => $booking->id,
    //             'subtotal'              => $request->subtotal,
    //             'platform_fee'          => $request->platform_fee,
    //             'total'                 => $request->total,
    //             'trx_id'                => $request->trx_id ?? 'TRX-' . strtoupper(Str::random(10)),
    //             'status'                => 'paid',
    //             'urgentStatus'          => $request->urgent_status ? '1' : '0',
    //             'stripe_id'             => $request->stripe_charge_id ?? null,
    //             'is_disbursed'          => false,
    //             'penalty_amount'        => 0.00,
    //             'refunded_amount'       => 0.00
    //         ]);

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Inspection Request Processed Successfully.',
    //             'data'    => $booking->load('payment', 'inspectionTypes')
    //         ], 201);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::error('Booking Insertion Error: ' . $e->getMessage());
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Database SQL Error: ' . $e->getMessage(),
    //             'line'    => $e->getLine()
    //         ], 500);
    //     }
    // }
}

