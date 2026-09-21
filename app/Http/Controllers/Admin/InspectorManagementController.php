<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Review;
use App\Models\InspectionAssign;
use App\Models\InspectionPayment;
use App\Models\InspectorPayout;
use Illuminate\Http\Request;


class InspectorManagementController extends Controller
{
    /**
     * 📊 DASHBOARD STATS
     */
    public function stats()
{
    // ================= INSPECTORS =================
    $totalInspectors = User::where('user_type', 'inspector')->count();

    $currentMonthInspectors = User::where('user_type', 'inspector')
        ->whereMonth('created_at', now()->month)
        ->whereYear('created_at', now()->year)
        ->count();

    $lastMonthInspectors = User::where('user_type', 'inspector')
        ->whereMonth('created_at', now()->subMonth()->month)
        ->whereYear('created_at', now()->subMonth()->year)
        ->count();

    $inspectorGrowth = $lastMonthInspectors > 0
        ? round((($currentMonthInspectors - $lastMonthInspectors) / $lastMonthInspectors) * 100, 2)
        : 100;

    // ================= ACTIVE INSPECTIONS =================
    $activeInspections = InspectionAssign::whereIn('status', ['assigned', 'started', 'rescheduled', 'reports'])->count();

    $currentMonthActive = InspectionAssign::whereIn('status', ['assigned', 'started', 'rescheduled', 'reports'])
        ->whereMonth('created_at', now()->month)
        ->whereYear('created_at', now()->year)
        ->count();

    $lastMonthActive = InspectionAssign::whereIn('status', ['assigned', 'started', 'rescheduled', 'reports'])
        ->whereMonth('created_at', now()->subMonth()->month)
        ->whereYear('created_at', now()->subMonth()->year)
        ->count();

    $activeGrowth = $lastMonthActive > 0
        ? round((($currentMonthActive - $lastMonthActive) / $lastMonthActive) * 100, 2)
        : ($currentMonthActive > 0 ? 100 : 0);

    // ================= PENDING APPROVAL =================
    $pendingApproval = User::where('user_type', 'inspector')
        ->where('status', 'pending')
        ->count();

    $currentMonthPending = User::where('user_type', 'inspector')
        ->where('status', 'pending')
        ->whereMonth('created_at', now()->month)
        ->count();

    $lastMonthPending = User::where('user_type', 'inspector')
        ->where('status', 'pending')
        ->whereMonth('created_at', now()->subMonth()->month)
        ->count();

    $pendingGrowth = $lastMonthPending > 0
        ? round((($currentMonthPending - $lastMonthPending) / $lastMonthPending) * 100, 2)
        : 100;

    return response()->json([
        'success' => true,
        'data' => [
            'total_inspectors' => $totalInspectors,
            'total_inspectors_growth' => $inspectorGrowth,

            'active_inspections' => $activeInspections,
            'active_inspections_growth' => $activeGrowth,

            'pending_approval' => $pendingApproval,
            'pending_approval_growth' => $pendingGrowth,
        ]
    ]);
}

    /**
     * 📋 INSPECTOR LIST
     */
    public function index(Request $request)
    {
        $inspectors = User::with([
                'profile.inspectionTypes'
            ])
            ->where('user_type', 'inspector');

        // status filter
        if ($request->filled('status')) {
            $inspectors->where('status', $request->status);
        }

        // search
        if ($request->filled('search')) {
            $search = $request->search;

            $inspectors->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $inspectors = $inspectors
            ->latest()
            ->paginate(10);

        $inspectors->getCollection()->transform(function ($inspector) {

            return [
                'id' => $inspector->id,

                'name' => trim(
                    ($inspector->first_name ?? '') . ' ' .
                    ($inspector->last_name ?? '')
                ),

                'email' => $inspector->email,

                'status' => $inspector->status,

                'image' => $inspector->profile?->profile_img
                    ? asset('storage/' . $inspector->profile->profile_img)
                    : null,

                'phone' => $inspector->profile?->phone,

                'inspection_type' =>
                    $inspector->profile?->inspectionTypes?->first()?->title,

                'created_at' => $inspector->created_at,
                'updated_at' => $inspector->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'inspectors' => $inspectors->items(),
                'pagination' => [
                    'current_page' => $inspectors->currentPage(),
                    'next_page' => $inspectors->hasMorePages(),
                    'per_page' => $inspectors->perPage(),
                    'total' => $inspectors->total(),
                ]
            ]
        ]);
    }

/**
 * INSPECTOR DETAILS
 */
public function show($id)
{
    $inspector = User::with(['profile.inspectionTypes'])
        ->where('user_type', 'inspector')
        ->findOrFail($id);

    // ================= REVIEWS =================
    $averageRating = Review::whereHas('inspectionAssign', function ($q) use ($id) {
        $q->where('inspector_id', $id);
    })->avg('rating');

    $totalReviews = Review::whereHas('inspectionAssign', function ($q) use ($id) {
        $q->where('inspector_id', $id);
    })->count();

    // ================= PERFORMANCE =================
    $completed = InspectionAssign::where('inspector_id', $id)
        ->where('status', 'completed')
        ->count();

    $cancelled = InspectionAssign::where('inspector_id', $id)
        ->where('status', 'cancelled')
        ->count();

    // ================= EARNINGS (FIXED - REAL SOURCE) =================
    // ================= EARNINGS =================
    $totalEarnings = InspectionPayment::join(
            'inspection_assigns',
            'inspection_assigns.inspection_booking_id',
            '=',
            'inspection_payments.inspection_booking_id'
        )
        ->where('inspection_assigns.inspector_id', $id)
        ->where('inspection_payments.payment_type', 'inspection_fee')
        ->where('inspection_payments.status', 'paid')
        ->sum('inspection_payments.inspector_share');
    // ================= RESPONSE =================
    return response()->json([
        'success' => true,
        'data' => [
            'id' => $inspector->id,

            'name' => trim(
                ($inspector->first_name ?? '') . ' ' . ($inspector->last_name ?? '')
            ),

            'email' => $inspector->email,
            'status' => $inspector->status,

            // PROFILE
            'image' => $inspector->profile?->profile_img
                ? asset('storage/' . $inspector->profile->profile_img)
                : null,

            'phone' => $inspector->profile?->phone,
            'location' => $inspector->profile?->address,

            // LICENSE INFO
            'license_number' => $inspector->profile?->license_number,
            'license_expiry' => $inspector->profile?->license_expiry,
            'insurance_expiry' => $inspector->profile?->insurance_expiry,

            'member_since' => optional($inspector->created_at)->format('Y-m-d'),

            // SPECIALIZATIONS
            'specializations' => $inspector->profile?->inspectionTypes
                ? $inspector->profile->inspectionTypes->pluck('title')->values()
                : [],

            // REVIEWS
            'reviews' => [
                'average_rating' => round($averageRating ?? 0, 1),
                'total_reviews' => $totalReviews,
            ],

            // PERFORMANCE
            'performance' => [
                'completed' => $completed,
                'cancelled' => $cancelled,
            ],

            // EARNINGS (REAL + SAFE)
            'total_earnings' => (float) $totalEarnings,
            'total_earnings_formatted' => '$' . number_format($totalEarnings, 2),

            // TIMESTAMPS
            'created_at' => $inspector->created_at,
            'updated_at' => $inspector->updated_at,
        ]
    ]);
}
    /**
     * ✅ APPROVE
     */
    public function approve($id)
    {
        $user = User::findOrFail($id);

        $user->update([
            'status' => 'active'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inspector approved successfully'
        ]);
    }

    /**
     * ⛔ REJECT
     */
    public function reject($id)
    {
        $user = User::findOrFail($id);

        $user->update([
            'status' => 'rejected'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inspector rejected successfully'
        ]);
    }

    /**
     * 🚫 SUSPEND
     */
    public function suspend($id)
    {
        $user = User::findOrFail($id);

        $user->update([
            'status' => 'suspended'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inspector suspended successfully'
        ]);
    }

    /**
     * 🔄 REACTIVATE
     */
    public function reactivate($id)
    {
        $user = User::findOrFail($id);

        $user->update([
            'status' => 'active'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inspector reactivated successfully'
        ]);
    }

    /**
     * 💰 INSPECTOR EARNINGS & PAYOUT STATUS (ALL INSPECTORS LIST)
     */
    public function earningsList(Request $request)
    {
        try {
            // Summary metrics for top cards
            $totalDisbursed = (float) InspectorPayout::where('status', 'paid')->sum('amount');
            $totalPendingPayout = (float) InspectorPayout::whereIn('status', ['pending', 'processing'])->sum('amount');
            $totalInspectorsCount = User::where('user_type', 'inspector')->count();
            $totalConnectedStripe = User::where('user_type', 'inspector')
                ->whereHas('profile', fn($q) => $q->whereNotNull('stripe_account_id')->where('stripe_account_id', '!=', ''))
                ->count();

            $query = User::with([
                'profile.inspectionTypes',
                'inspectorPayouts' => fn($q) => $q->latest()
            ])
            ->where('user_type', 'inspector');

            // Search filter
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // User status filter (active, pending, suspended)
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Stripe connection filter
            if ($request->filled('stripe_status')) {
                if ($request->stripe_status === 'connected') {
                    $query->whereHas('profile', fn($q) => $q->whereNotNull('stripe_account_id')->where('stripe_account_id', '!=', ''));
                } elseif ($request->stripe_status === 'not_connected') {
                    $query->where(function ($q) {
                        $q->whereDoesntHave('profile')
                          ->orWhereHas('profile', fn($pq) => $pq->whereNull('stripe_account_id')->orWhere('stripe_account_id', ''));
                    });
                }
            }

            $perPage = (int) $request->get('per_page', 15);
            $inspectors = $query->latest()->paginate($perPage);

            $inspectors->getCollection()->transform(function ($inspector) {
                $payouts = $inspector->inspectorPayouts ?? collect();

                $totalPaid = (float) $payouts->where('status', 'paid')->sum('amount');
                $totalPending = (float) $payouts->whereIn('status', ['pending', 'processing'])->sum('amount');
                $totalFailed = (float) $payouts->where('status', 'failed')->sum('amount');
                $totalEarned = $totalPaid + $totalPending;

                $latestPayout = $payouts->first();
                $completedCount = InspectionAssign::where('inspector_id', $inspector->id)
                    ->where('status', 'completed')
                    ->count();

                $stripeAccountId = $inspector->profile?->stripe_account_id;

                return [
                    'id'                        => $inspector->id,
                    'name'                      => trim(($inspector->first_name ?? '') . ' ' . ($inspector->last_name ?? '')),
                    'email'                     => $inspector->email,
                    'phone'                     => $inspector->profile?->phone,
                    'image'                     => $inspector->profile?->profile_img
                        ? asset('storage/' . $inspector->profile->profile_img)
                        : null,
                    'status'                    => $inspector->status,
                    'stripe_connected'          => !empty($stripeAccountId),
                    'stripe_account_id'         => $stripeAccountId,
                    'completed_inspections'     => $completedCount,
                    'total_earned'              => round($totalEarned, 2),
                    'total_paid'                => round($totalPaid, 2),
                    'total_pending'             => round($totalPending, 2),
                    'total_failed'              => round($totalFailed, 2),
                    'total_earned_formatted'    => '$' . number_format($totalEarned, 2),
                    'total_paid_formatted'      => '$' . number_format($totalPaid, 2),
                    'total_pending_formatted'   => '$' . number_format($totalPending, 2),
                    'latest_payout_status'      => $latestPayout ? $latestPayout->status : 'no_payout',
                    'last_payout_date'          => optional($latestPayout?->paid_at ?? $latestPayout?->created_at)->format('d M Y, h:i A'),
                    'created_at'                => optional($inspector->created_at)->format('d M Y'),
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => [
                    'summary' => [
                        'total_disbursed'           => round($totalDisbursed, 2),
                        'total_disbursed_formatted' => '$' . number_format($totalDisbursed, 2),
                        'total_pending_payout'      => round($totalPendingPayout, 2),
                        'total_pending_formatted'   => '$' . number_format($totalPendingPayout, 2),
                        'total_inspectors'          => $totalInspectorsCount,
                        'stripe_connected_count'    => $totalConnectedStripe,
                    ],
                    'inspectors' => $inspectors->items(),
                    'pagination' => [
                        'current_page' => $inspectors->currentPage(),
                        'next_page'    => $inspectors->hasMorePages(),
                        'per_page'     => $inspectors->perPage(),
                        'total'        => $inspectors->total(),
                        'last_page'    => $inspectors->lastPage(),
                    ]
                ]
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Inspector earnings list failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load inspector earnings list: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 📜 SINGLE INSPECTOR PAYOUT / TRANSACTION HISTORY (ADMIN VIEW)
     */
    public function inspectorPayoutHistory($id, Request $request)
    {
        try {
            $inspector = User::with(['profile.inspectionTypes'])
                ->where('user_type', 'inspector')
                ->findOrFail($id);

            $payoutsQuery = InspectorPayout::with([
                'inspectionAssign.inspectionBooking.homeowner',
                'inspectionAssign.inspectionBooking.inspectionTypes'
            ])
            ->where('inspector_id', $id);

            if ($request->filled('status')) {
                $payoutsQuery->where('status', $request->status);
            }

            $perPage = (int) $request->get('per_page', 15);
            $payouts = $payoutsQuery->latest()->paginate($perPage);

            $payouts->getCollection()->transform(function ($payout) {
                $assign    = $payout->inspectionAssign;
                $booking   = $assign?->inspectionBooking;
                $type      = $booking?->inspectionTypes?->first();
                $homeowner = $booking?->homeowner;

                return [
                    'payout_id'          => $payout->id,
                    'booking_id'         => $booking?->id,
                    'inspection_title'   => $type?->title ?? 'Inspection',
                    'property_address'   => $booking?->property_address,
                    'homeowner_name'     => $homeowner ? trim($homeowner->first_name . ' ' . $homeowner->last_name) : 'N/A',
                    'amount'             => (float) $payout->amount,
                    'amount_formatted'   => '$' . number_format($payout->amount, 2),
                    'platform_fee'       => (float) ($payout->platform_fee ?? 0),
                    'status'             => $payout->status,
                    'is_disbursed'       => (bool) $payout->is_disbursed,
                    'method'             => $payout->method ?? 'stripe',
                    'stripe_transfer_id' => $payout->stripe_transfer_id,
                    'paid_at'            => optional($payout->paid_at)->format('d M Y, h:i A'),
                    'created_at'         => optional($payout->created_at)->format('d M Y, h:i A'),
                ];
            });

            $totalEarned = (float) InspectorPayout::where('inspector_id', $id)->where('status', 'paid')->sum('amount');
            $pendingPayout = (float) InspectorPayout::where('inspector_id', $id)->whereIn('status', ['pending', 'processing'])->sum('amount');

            return response()->json([
                'success' => true,
                'data'    => [
                    'inspector' => [
                        'id'                     => $inspector->id,
                        'name'                   => trim(($inspector->first_name ?? '') . ' ' . ($inspector->last_name ?? '')),
                        'email'                  => $inspector->email,
                        'phone'                  => $inspector->profile?->phone,
                        'status'                 => $inspector->status,
                        'stripe_connected'       => !empty($inspector->profile?->stripe_account_id),
                        'stripe_account_id'      => $inspector->profile?->stripe_account_id,
                        'total_paid'             => round($totalEarned, 2),
                        'total_paid_formatted'   => '$' . number_format($totalEarned, 2),
                        'total_pending'          => round($pendingPayout, 2),
                        'total_pending_formatted'=> '$' . number_format($pendingPayout, 2),
                    ],
                    'payouts'    => $payouts->items(),
                    'pagination' => [
                        'current_page' => $payouts->currentPage(),
                        'next_page'    => $payouts->hasMorePages(),
                        'per_page'     => $payouts->perPage(),
                        'total'        => $payouts->total(),
                        'last_page'    => $payouts->lastPage(),
                    ]
                ]
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Inspector payout history failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load inspector payout history: ' . $e->getMessage()
            ], 500);
        }
    }
}