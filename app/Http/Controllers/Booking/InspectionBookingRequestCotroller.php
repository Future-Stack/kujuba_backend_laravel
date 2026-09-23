<?php


namespace App\Http\Controllers\Booking;


use App\Http\Controllers\Controller;
use App\Models\CancelRequest;
use App\Models\InspectionAssign;
use App\Models\InspectionBooking;
use App\Models\InspectionPayment;
use App\Models\InspectionType;
use App\Models\InspectorPayout;
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
            'zip_code' => 'nullable|string|max:20',
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

        if ($request->urgent_status) {
            $nowEst = now('America/New_York');
            $todayEst = $nowEst->toDateString();

            // 1. Same day check
            if ($request->scheduled_date !== $todayEst) {
                return response()->json([
                    'success' => false,
                    'message' => 'Urgent inspection must be scheduled for today (same day).'
                ], 422);
            }

            // 2. Cap it at 5 PM EST (17:00) check
            $maxUrgentTime = '17:00';
            if ($request->scheduled_time > $maxUrgentTime) {
                return response()->json([
                    'success' => false,
                    'message' => 'Urgent inspection time cannot be later than 5:00 PM EST for the same day.'
                ], 422);
            }

            // 3. Current time theke 12 hourser moddhe ba valid range check (optional additional safety)
            if ($request->scheduled_time < $nowEst->format('H:i')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Scheduled time for urgent inspection cannot be in the past.'
                ], 422);
            }
        }

        $inspectionTypes = InspectionType::whereIn('id', $request->inspection_type_ids)->get();
        $subtotal = $inspectionTypes->sum('price');

        $platformFeeDigit = Setting::first()->platform_commission ?? 20.00;
        $platformFee = $subtotal * ($platformFeeDigit / 100);
        $urgentFee = Setting::first()->urgent_inspection_fee ?? 50.00;

        if ($request->urgent_status) {
            $total = $subtotal + $urgentFee;
            $inspector_share = ($subtotal - $platformFee)  + $urgentFee;
        } else {
            $total = $subtotal;
            $inspector_share = $subtotal - $platformFee;
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
                'homeowner_id' => $userId,
                'property_address' => $request->property_address,
                'zip_code' => $request->zip_code,
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
                'latitude' => $lat,
                'longitude' => $lng,
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
                'payment_type' => 'inspection_fee',
                'trx_id' => null,
                'status' => 'pending',
                'urgentStatus' => $request->urgent_status ? '1' : '0',
                'stripe_id' => null,
                'is_disbursed' => false,
                'penalty_amount' => 0.00,
                'refunded_amount' => 0.00
            ]);

            // Stripe Payment Flow
            $stripe = new \Stripe\StripeClient($stripeSecret);

            $paymentIntent = $stripe->paymentIntents->create([
                'amount' => intval($total * 100), // cents
                'currency' => 'usd',
                'description' => 'Inspection Booking',
                'metadata' => [
                    'type' => 'booking',
                    'booking_id' => $booking->id,
                    'booking_amount' => $total,
                    'payment_id' => $payment->id,
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            $payment->update([
                'trx_id' => $paymentIntent->id,
                'stripe_id' => $paymentIntent->id,
            ]);

            DB::commit();

            // Geo-targeted notification to nearby inspectors is sent in handleWebhook() after payment confirmation

            // Notify Homeowner
            try {
                $homeowner = User::find($booking->homeowner_id);
                if ($homeowner) {
                    $homeowner->notify(new PlatformNotification([
                        'type'       => 'booking_created',
                        'title'      => 'Booking Request Placed!',
                        'message'    => "Your inspection booking #{$booking->id} has been placed successfully.",
                        'booking_id' => $booking->id,
                        'sender_id'  => $booking->homeowner_id,
                    ]));
                }
            } catch (\Throwable $homeownerEx) {
                Log::warning('Homeowner notification failed in store: ' . $homeownerEx->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully.',
                'amount' => $total,
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
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

    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'inspection_type_ids' => 'required|array|min:1',
    //         'inspection_type_ids.*' => 'required|integer|exists:inspection_types,id',
    //         'property_address' => 'required|string|max:255',
    //         'zip_code' => 'nullable|string|max:20',
    //         'property_type' => 'required|string|max:255',
    //         'property_size' => 'required|string|max:255',
    //         'note' => 'nullable|string',
    //         'property_img' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
    //         'scheduled_date' => 'required|date_format:Y-m-d',
    //         'scheduled_time' => 'required|date_format:H:i',
    //         'scheduled_shift' => 'required|string|max:255',
    //         'urgent_status' => 'nullable|boolean',
    //         'payment_method_id' => 'nullable|string',
    //         'latitude' => 'nullable|numeric',
    //         'longitude' => 'nullable|numeric',
    //     ]);


    //     $inspectionTypes = InspectionType::whereIn('id', $request->inspection_type_ids)->get();
    //     $subtotal = $inspectionTypes->sum('price');


    //     $platformFeeDigit = Setting::first()->platform_commission ?? 20.00;
    //     $platformFee = $subtotal * ($platformFeeDigit / 100);
    //     $urgentFee = Setting::first()->urgent_inspection_fee ?? 50.00;


    //     if ($request->urgent_status) {
    //         $total = $subtotal + $urgentFee;
    //         $inspector_share = ($subtotal - $platformFee)  + $urgentFee;
    //     } else {
    //         $total = $subtotal;
    //         $inspector_share = $subtotal - $platformFee;
    //     }




    //     DB::beginTransaction();


    //     try {
    //         $userId = auth()->id() ?? $request->homeowner_id;


    //         if (!$userId) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Unauthorized user access context.'
    //             ], 401);
    //         }


    //         $stripeSecret = config('services.stripe.secret') ?? env('STRIPE_SECRET');


    //         if (!$stripeSecret) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Stripe API secret key is missing in your .env file.'
    //             ], 500);
    //         }


    //         $imagePath = null;
    //         if ($request->hasFile('property_img')) {
    //             $imagePath = $request->file('property_img')->store('inspections', 'public');
    //         }


    //         $booking = InspectionBooking::create([
    //             'homeowner_id' => $userId,
    //             'property_address' => $request->property_address,
    //             'zip_code' => $request->zip_code,
    //             'property_type' => $request->property_type,
    //             'property_size' => $request->property_size,
    //             'note' => $request->note,
    //             'property_img' => $imagePath,
    //             'booking_date' => now()->toDateString(),
    //             'scheduled_date' => $request->scheduled_date,
    //             'scheduled_time' => $request->scheduled_time,
    //             'scheduled_shift' => $request->scheduled_shift,
    //             'urgent_status' => $request->urgent_status ? 1 : 0,
    //             'status' => 'pending',
    //             'latitude' => $request->latitude,
    //             'longitude' => $request->longitude,
    //             'isRescheduled' => 0
    //         ]);


    //         $booking->inspectionTypes()->attach($request->inspection_type_ids);


    //         $payment = InspectionPayment::create([
    //             'inspection_booking_id' => $booking->id,


    //             'subtotal' => $subtotal,
    //             'platform_fee' => $platformFee,
    //             'inspector_share' => $inspector_share,
    //             'admin_share' => $platformFee,
    //             'urgent_fee' => $request->urgent_status ? $urgentFee : 0.00,
    //             'total' => $total,
    //             'payment_type' => 'inspection_fee',
    //             'trx_id' => null,
    //             'status' => 'pending',
    //             'urgentStatus' => $request->urgent_status ? '1' : '0',
    //             'stripe_id' => null,
    //             'is_disbursed' => false,
    //             'penalty_amount' => 0.00,
    //             'refunded_amount' => 0.00
    //         ]);


    //         // Stripe Payment Flow
    //         $stripe = new \Stripe\StripeClient($stripeSecret);


    //         $paymentIntent = $stripe->paymentIntents->create([
    //             'amount' => intval($total * 100), // cents
    //             'currency' => 'usd',
    //             'description' => 'Inspection Booking',
    //             'metadata' => [
    //                 'type' => 'booking',
    //                 'booking_id' => $booking->id,
    //                 'booking_amount' => $total,
    //                 'payment_id' => $payment->id,
    //             ],
    //             'automatic_payment_methods' => [
    //                 'enabled' => true,
    //             ],
    //         ]);


    //         $payment->update([
    //             'trx_id' => $paymentIntent->id,
    //             'stripe_id' => $paymentIntent->id,
    //         ]);


    //         DB::commit();

    //         // 🎯 Geo-targeted Notification: Notify nearby inspectors within 50 miles radius
    //         try {
    //             $nearbyInspectors = \App\Services\GeoLocationService::getNearbyInspectors(
    //                 $booking->latitude ? (float) $booking->latitude : null,
    //                 $booking->longitude ? (float) $booking->longitude : null,
    //                 $booking->zip_code
    //             );

    //             if ($nearbyInspectors->isNotEmpty()) {
    //                 Notification::send($nearbyInspectors, new PlatformNotification([
    //                     'type'       => 'new_inspection_lead',
    //                     'title'      => 'New Inspection Lead in Your Area!',
    //                     'message'    => "A new inspection booking ({$booking->property_type}) is available near you ({$booking->property_address}).",
    //                     'booking_id' => $booking->id,
    //                     'sender_id'  => $booking->homeowner_id,
    //                 ]));
    //             }
    //         } catch (\Throwable $geoEx) {
    //             Log::warning('Nearby inspector notification failed in store: ' . $geoEx->getMessage());
    //         }

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Booking created successfully.',
    //             'amount' => $total,
    //             'booking_id' => $booking->id,
    //             'payment_id' => $payment->id,
    //             'stripe' => $paymentIntent,
    //         ], 201);


    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'success' => false,
    //             'errors' => $e->errors(),
    //         ], 422);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::error('Booking Store Error: ' . $e->getMessage());
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to create Booking.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }


    public function handleWebhook(Request $request)
    {
        Log::info('Webhook Route Hit Successfully! Raw Payload: ' . $request->getContent());
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');


        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                config('services.stripe.booking_webhook_secret')
            );


            Log::info('Stripe Webhook Received: ' . $event->type);


            if ($event->type === 'payment_intent.succeeded') {
                $intent = $event->data->object;
                $paymentId = $intent->metadata->payment_id ?? null;
                $bookingId = $intent->metadata->booking_id ?? null;


                Log::info("Webhook Processing - Payment ID: " . $paymentId . " | Booking ID: " . $bookingId);


                if ($paymentId) {
                    $payment = InspectionPayment::find($paymentId);


                    if ($payment && $payment->status !== 'paid') {
                        $payment->update([
                            'status' => 'paid',
                        ]);


                        if ($bookingId) {
                            $booking = InspectionBooking::find($bookingId);
                            if ($booking) {
                                $booking->update([
                                    'status' => 'confirmed'
                                ]);

                                // 🎯 Geo-targeted Notification: Notify nearby inspectors within 50 miles radius
                                try {
                                    $nearbyInspectors = \App\Services\GeoLocationService::getNearbyInspectors(
                                        $booking->latitude ? (float) $booking->latitude : null,
                                        $booking->longitude ? (float) $booking->longitude : null,
                                        $booking->zip_code
                                    );

                                    if ($nearbyInspectors->isNotEmpty()) {
                                        Notification::send($nearbyInspectors, new PlatformNotification([
                                            'type'       => 'new_inspection_lead',
                                            'title'      => 'New Inspection Lead in Your Area!',
                                            'message'    => "A new inspection booking ({$booking->property_type}) is available near you ({$booking->property_address}).",
                                            'booking_id' => $booking->id,
                                            'sender_id'  => $booking->homeowner_id,
                                        ]));
                                    }
                                } catch (\Throwable $geoEx) {
                                    Log::warning('Nearby inspector notification failed: ' . $geoEx->getMessage());
                                }

                                // 📲 Notify Homeowner of Booking Confirmation
                                try {
                                    $homeowner = User::find($booking->homeowner_id);
                                    if ($homeowner) {
                                        $homeowner->notify(new PlatformNotification([
                                            'type'       => 'booking_confirmed',
                                            'title'      => 'Booking Confirmed!',
                                            'message'    => "Your inspection booking #{$booking->id} has been confirmed successfully.",
                                            'booking_id' => $booking->id,
                                            'sender_id'  => $booking->homeowner_id,
                                        ]));
                                    }
                                } catch (\Throwable $homeownerEx) {
                                    Log::warning('Homeowner webhook confirmation notification failed: ' . $homeownerEx->getMessage());
                                }
                            }
                        }
                    }
                }
            }


            $admin = User::where('user_type', 'admin')->first();
            if ($admin) {
                Notification::send($admin, new AdminIconNotification([
                    'type'      => 'inspection_booking',
                    'title'     => 'Inspection Booking',
                    'message'   => 'A new Inspection Booking has been paid and created.',
                    'sender_id' => null,
                ]));
            }


            return response('OK', 200);


        } catch (\Exception $e) {
            Log::error('Webhook Structure/Process Error: ' . $e->getMessage());
            return response('Webhook Error: ' . $e->getMessage(), 400);
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


    public function hmmandleWebhook(Request $request)
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
            $user = Auth::user()->load('profile');

            $inspectorLat = $user->profile?->latitude;
            $inspectorLng = $user->profile?->longitude;
            $maxDistanceKm = 50.0;

            // Base query with relationships
            $query = InspectionBooking::with(['payment', 'inspectionTypes', 'reschedule'])
                ->whereHas('payment', fn($q) => $q->where('status', 'paid'))
                ->whereDoesntHave('declines', function ($q) use ($user) {
                    $q->where('inspector_id', $user->id);
                })
                ->whereDoesntHave('inspectionAssign');

            if (!is_null($inspectorLat) && !is_null($inspectorLng)) {

                // Haversine formula (6371 km) calculating distance between inspector profile and inspection_bookings location
                $haversineSql = "(6371 * acos(least(1.0, greatest(-1.0,
                    cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?))
                    + sin(radians(?)) * sin(radians(latitude))
                ))))";

                $query->select('inspection_bookings.*')
                    ->selectRaw("{$haversineSql} AS distance_km", [$inspectorLat, $inspectorLng, $inspectorLat])
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->having('distance_km', '<=', $maxDistanceKm)
                    ->orderBy('distance_km', 'asc');
            } else {
                $query->latest('inspection_bookings.created_at');
            }

            // Apply filter logic
            if ($filter === 'urgent') {
                $query->where('inspection_bookings.urgent_status', true);
            } elseif ($filter === 'rescheduled') {
                $query->where('inspection_bookings.isRescheduled', true);
            } elseif ($filter === 'new') {
                $query->limit(5);
            }

            $bookings = $query->get()->map(function ($booking) {
                $payment = $booking->payment;
                $reschedule = $booking->reschedule ?? null;
                $type = $booking->inspectionTypes->pluck('title')->toArray();
                $img = $booking->inspectionTypes->pluck('img')->toArray();
                $price = $booking->inspectionTypes->pluck('price')->toArray();

                $distanceFormatted = isset($booking->distance_km)
                    ? round($booking->distance_km, 1) . ' km'
                    : null;

                return [
                    'id' => $booking->id,
                    'inspection_img' => $img,
                    'inspection_type' => $type,
                    'inspection_price' => $price,
                    'property_address' => $booking->property_address,
                    'property_type' => $booking->property_type,
                    'property_size' => $booking->property_size,
                    'property_img' => $booking->property_img,
                    'scheduled_date' => $booking->scheduled_date ? $booking->scheduled_date->format('Y-m-d') : null,
                    'scheduled_time' => $booking->scheduled_time,
                    'urgent_status' => $booking->urgent_status,
                    'rescheduled_status' => $booking->isRescheduled,
                    'reschedule' => $reschedule,
                    'status' => $booking->status,
                    'note' => $booking->note,
                    'price' => $payment ? number_format($payment->subtotal, 2) : null,
                    'distance' => $distanceFormatted,
                    'latitude' => $booking->latitude ? (float) $booking->latitude : null,
                    'longitude' => $booking->longitude ? (float) $booking->longitude : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $bookings,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Booking list fetch failed: ' . $e->getMessage());
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


    public function cancelBookingInspection(Request $request)
    {
        $request->validate([
            'booking_id' => 'required',
            'cancellation_notes' => 'nullable',
        ]);

        $booking = InspectionBooking::findOrFail($request->booking_id);
        $payment = InspectionPayment::where('inspection_booking_id', $request->booking_id)
            ->where('payment_type', 'inspection_fee')
            ->firstOrFail();

        if ($payment->status !== 'paid') {
            $booking->update([
                'status' => 'cancelled',
                'cancellation_notes' => $request->cancellation_notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Booking cancelled. Payment was not completed — no refund issued.',
                'refunded_amount' => 0,
                'cancellation_fee' => 0,
            ]);
        }

        $scheduledDateTime = \Carbon\Carbon::parse(
            $booking->scheduled_date->format('Y-m-d') . ' ' . $booking->scheduled_time
        );
        $hoursLeft = now()->diffInHours($scheduledDateTime, false);

        $cancellationFee = 0;
        $admin_share = 0;
        $platform_fee = 0;
        $inspector_share = 0;
        $setting = Setting::first();

        if ($booking->inspectionAssign) {
            if ($hoursLeft < 2) {
                $cancellationFee = $setting->last_minute_cancel_penalty ?? 75;
            } elseif ($hoursLeft < 24) {
                $cancellationFee = $setting->late_cancellation_penalty ?? 50;
            }
            $admin_share = $cancellationFee / 2;
            $platform_fee = $admin_share;
            $inspector_share = $cancellationFee / 2;
        }

        $refundAmount = max(($payment->total - $cancellationFee), 0);
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret') ?? env('STRIPE_SECRET'));

        try {
            DB::beginTransaction();

            $refund = $stripe->refunds->create([
                'payment_intent' => $payment->stripe_id,
                'amount'         => intval($refundAmount * 100),
                'metadata'       => [
                    'booking_id' => $booking->id,
                ],
            ]);

            $refundPayment = InspectionPayment::create([
                'inspection_booking_id' => $booking->id,
                'subtotal'              => $refundAmount,
                'platform_fee'          => $platform_fee,
                'urgent_fee'            => 0,
                'inspector_share'       => $inspector_share,
                'admin_share'           => $admin_share,
                'total'                 => $refundAmount,
                'payment_type'          => 'refund',
                'trx_id'                => $refund->id,
                'status'                => 'pending',
                'stripe_id'             => $payment->stripe_id,
                'penalty_amount'        => $cancellationFee,
                'refunded_amount'       => $refundAmount,
                'is_disbursed'          => false,
            ]);

//            $booking->update([
//                'status' => 'cancelled',
//                'cancellation_notes' => $request->cancellation_notes,
//            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel inspection.',
                'error'   => $e->getMessage(),
            ], 500);
        }

        if ($booking->inspectionAssign && $inspector_share > 0) {

            $inspector = $booking->inspectionAssign->inspector;
            $stripeAccountId = $inspector?->profile?->stripe_account_id;
            $onboardingCompleted = $inspector?->profile?->stripe_onboarding_completed;

            $payoutData = [
                'inspector_id'          => $booking->inspectionAssign->inspector_id,
                'inspection_assign_id'  => $booking->inspectionAssign->id,
                'inspection_payment_id' => $refundPayment->id,
                'amount'                => $inspector_share,
                'platform_fee'          => $platform_fee,
                'currency'              => 'USD',
                'payment_type'          => 'cancellation_penalty',
                'method'                => 'stripe',
                'calculated_at'         => now(),
                'note'                  => 'Cancellation penalty share for booking #' . $booking->id,
            ];

            if (!$stripeAccountId || !$onboardingCompleted) {
                $this->safeCreatePayout(array_merge($payoutData, [
                    'status'             => 'failed',
                    'is_disbursed'       => false,
                    'transaction_id'     => null,
                    'stripe_transfer_id' => null,
                    'paid_at'            => null,
                    'note'               => $payoutData['note'] . ' — Inspector Stripe account not connected/onboarded.',
                ]));
            } else {
                try {
                    $transfer = $stripe->transfers->create([
                        'amount'         => intval($inspector_share * 100),
                        'currency'       => 'usd',
                        'destination'    => $stripeAccountId,
                        'transfer_group' => 'booking_' . $booking->id,
                        'metadata'       => [
                            'booking_id' => $booking->id,
                            'type'       => 'cancellation_fee_share',
                        ],
                    ]);

                    $this->safeCreatePayout(array_merge($payoutData, [
                        'status'             => 'processing',
                        'is_disbursed'       => false,
                        'transaction_id'     => $transfer->id,
                        'stripe_transfer_id' => $transfer->id,
                        'paid_at'            => null,
                    ]), $transfer->id);

                } catch (\Exception $transferException) {
                    Log::error('Inspector payout transfer failed: ' . $transferException->getMessage(), [
                        'booking_id'   => $booking->id,
                        'inspector_id' => $inspector->id ?? null,
                        'amount'       => $inspector_share,
                    ]);

                    $this->safeCreatePayout(array_merge($payoutData, [
                        'status'             => 'failed',
                        'is_disbursed'       => false,
                        'transaction_id'     => null,
                        'stripe_transfer_id' => null,
                        'paid_at'            => null,
                        'note'               => $payoutData['note'] . ' — Transfer failed: ' . $transferException->getMessage(),
                    ]));
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Inspection cancelled. Refund initiated.',
            'refund'  => $refund,
            'cancellation_fee' => $cancellationFee,
            'refunded_amount' => $refundAmount,
        ], 200);
    }

    private function safeCreatePayout(array $data, ?string $transferIdIfMoneyMoved = null)
    {
        try {
            return InspectorPayout::create($data);
        } catch (\Exception $e) {
            Log::critical('InspectorPayout DB insert failed after Stripe transfer!', [
                'stripe_transfer_id' => $transferIdIfMoneyMoved,
                'payout_data'        => $data,
                'db_error'           => $e->getMessage(),
            ]);
            return null;
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

                $charge = $event->data->object;
                $paymentIntentId = $charge->payment_intent;
                $booking_id = $charge->metadata->booking_id ?? null;

                // 2. Fallback: If metadata was passed during $stripe->refunds->create()
                if (!$booking_id && !empty($charge->refunds->data)) {
                    $latestRefund = $charge->refunds->data[0];
                    $booking_id = $latestRefund->metadata->booking_id ?? null;
                }

                Log::info("Booking ID extracted: " . ($booking_id ?? 'null'));

                if ($booking_id) {
                    InspectionBooking::where('id', $booking_id)->update([
                        'status'  => 'cancelled',
                    ]);
                }

                $payment = InspectionPayment::where('stripe_id', $paymentIntentId)
                    ->where('payment_type', 'refund')
                    ->first();

                Log::info("Refund payment found: " . ($payment->id ?? 'none'));

                if ($payment && $payment->status !== 'paid') {
                    $payment->update([
                        'status' => 'paid',
                    ]);
                }
            }

            if ($event->type === 'transfer.created') {
                $transfer = $event->data->object;

                $payout = InspectorPayout::where('stripe_transfer_id', $transfer->id)->first();

                if ($payout) {
                    $payout->update([
                        'status'       => 'paid',
                        'is_disbursed' => true,
                        'paid_at'      => now(),
                    ]);

                    Log::info("Inspector payout confirmed via webhook", [
                        'payout_id'    => $payout->id,
                        'transfer_id'  => $transfer->id,
                        'amount'       => $transfer->amount / 100,
                    ]);
                } else {
                    Log::warning("transfer.created webhook received but no matching InspectorPayout found", [
                        'transfer_id' => $transfer->id,
                    ]);
                }
            }

            if ($event->type === 'transfer.reversed') {
                $transfer = $event->data->object;

                $payout = InspectorPayout::where('stripe_transfer_id', $transfer->id)->first();

                if ($payout) {
                    $payout->update([
                        'status'       => 'failed',
                        'is_disbursed' => false,
                        'note'         => ($payout->note ?? '') . ' — Transfer reversed by Stripe.',
                    ]);

                    Log::warning("Inspector payout reversed", [
                        'payout_id'   => $payout->id,
                        'transfer_id' => $transfer->id,
                    ]);
                }
            }

            $admin = User::where('user_type', 'admin')->first();

            if ($admin) {
                Notification::send($admin, new AdminIconNotification([
                    'type'      => 'cancelled_booking',
                    'title'     => 'Booking Inspection Cancelled',
                    'message'   => 'A new Inspection Booking has been Cancelled.',
                    'sender_id' => null,
                ]));
            }

            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error('Cancel Webhook Error: ' . $e->getMessage());
            return response('Webhook Error: ' . $e->getMessage(), 400);
        }
    }


    public function inspectorCancelRequest(Request $request)
    {
        $request->validate([
            'title'                => 'required|string|max:255',
            'problem'              => 'required|string',
            'inspection_assign_id' => 'required|integer|unique:cancel_requests,inspection_assign_id',
        ]);


        try {
            DB::beginTransaction();


            // Create cancel request
            $cancelRequest = CancelRequest::create([
                'inspection_assign_id' => $request->inspection_assign_id,
                'title'                => $request->title,
                'problem'              => $request->problem,
            ]);


            // Update inspection assign status
            InspectionAssign::where('id', $request->inspection_assign_id)->update([
                'status' => 'cancelled',
            ]);


            DB::commit();


            return response()->json([
                'success' => true,
                'message' => 'Cancel request submitted successfully.',
                'data'    => $cancelRequest, // eager relation
            ], 201);


        } catch (\Throwable $e) {
            DB::rollBack();


            return response()->json([
                'success' => false,
                'message' => 'Failed to submit cancel request.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    public function declineInspectionRequest(Request $request)
    {
        $request->validate([
            'inspection_assign_id' => 'required|integer|exists:inspection_assigns,id',
        ]);


        try {
            DB::beginTransaction();


            $assign = InspectionAssign::findOrFail($request->inspection_assign_id);


            // Prevent duplicate decline
            if ($assign->status === 'assigned') {
                return response()->json([
                    'success' => false,
                    'message' => 'This assignment is already marked as assigned.',
                ], 400);
            }


            // Update assign status back to "assigned"
            $assign->update([
                'status' => 'assigned',
            ]);


            DB::commit();


            return response()->json([
                'success' => true,
                'message' => 'Assign declined by admin and status reverted to assigned.',
                'data'    => $assign,
            ], 200);


        } catch (\Throwable $e) {
            DB::rollBack();


            return response()->json([
                'success' => false,
                'message' => 'Failed to decline assign.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    public function acceptInspectionRequest(Request $request)
    {
        $request->validate([
            'inspection_assign_id' => 'required|integer|exists:inspection_assigns,id',
            'inspector_id' => 'required|integer|exists:users,id',
        ]);


        try {
            DB::beginTransaction();


            $assign = InspectionAssign::findOrFail($request->inspection_assign_id);


          $assign->update([
              'inspector_id' => $request->inspector_id,
          ]);






            // Update assign status to "accepted"
            $assign->update([
                'status' => 'assigned',
            ]);


            DB::commit();


            return response()->json([
                'success' => true,
                'message' => 'Assign accepted successfully.',
                'data' => $assign,
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to accept assign.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
