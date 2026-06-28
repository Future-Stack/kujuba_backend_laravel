<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Review;
use App\Models\InspectionAssign;
use App\Models\InspectionPayment;
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
    $activeInspections = InspectionAssign::whereIn('status', ['inspection', 'started'])->count();

    $currentMonthActive = InspectionAssign::whereIn('status', ['inspection', 'started'])
        ->whereMonth('created_at', now()->month)
        ->count();

    $lastMonthActive = InspectionAssign::whereIn('status', ['inspection', 'started'])
        ->whereMonth('created_at', now()->subMonth()->month)
        ->count();

    $activeGrowth = $lastMonthActive > 0
        ? round((($currentMonthActive - $lastMonthActive) / $lastMonthActive) * 100, 2)
        : 100;

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
}