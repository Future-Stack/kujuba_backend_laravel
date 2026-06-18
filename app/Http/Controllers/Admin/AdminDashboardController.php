<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\InspectionAssign;
use App\Models\InspectionBooking;
use App\Models\InspectionPayment;

class AdminDashboardController extends Controller
{
    /**
     * ===========================
     * ADMIN DASHBOARD OVERVIEW
     * ===========================
     */
   
        //next
        public function overview(Request $request)
{
$now = now();
$lastMonth = now()->subMonth();

// ================= STATS =================

$totalRevenue = InspectionPayment::sum('total');

$totalUsers = User::count();

$totalInspectors = User::where('user_type', 'inspector')->count();

$pendingApprovals = User::where('user_type', 'inspector')
    ->where('status', 'pending')
    ->count();

$activeInspections = InspectionAssign::whereIn('status', [
    'assigned',
    'inspection',
    'in_progress'
])->count();

$completedInspections = InspectionAssign::where(
    'status',
    'completed'
)->count();

$cancelledInspections = InspectionAssign::where(
    'status',
    'cancelled'
)->count();

$pendingInspections = InspectionAssign::where(
    'status',
    'pending'
)->count();

// ================= GROWTH =================

$lastMonthRevenue = InspectionPayment::whereMonth(
    'created_at',
    $lastMonth->month
)->sum('total');

$currentMonthRevenue = InspectionPayment::whereMonth(
    'created_at',
    $now->month
)->sum('total');

$revenueGrowth = $lastMonthRevenue > 0
    ? round(
        (($currentMonthRevenue - $lastMonthRevenue)
        / $lastMonthRevenue) * 100,
        2
    )
    : 100;

// ================= RECENT USERS =================

$recentUsers = User::latest()
    ->take(5)
    ->get()
    ->map(function ($user) {

        return [
            'id' => $user->id,
            'name' => trim(
                ($user->first_name ?? '') . ' ' .
                ($user->last_name ?? '')
            ),
            'email' => $user->email,
            'status' => $user->status,
        ];
    });

// ================= TOP INSPECTORS =================

$topInspectors = User::where('user_type', 'inspector')
    ->with('profile')
    ->get()
    ->map(function ($user) {

        $earnings = InspectionPayment::whereHas(
            'inspectionBooking.inspectionAssign',
            function ($q) use ($user) {
                $q->where('inspector_id', $user->id)
                    ->where('status', 'completed');
            }
        )->sum(DB::raw('COALESCE(total - platform_fee,0)'));

        return [
            'id' => $user->id,
            'name' => trim(
                ($user->first_name ?? '') . ' ' .
                ($user->last_name ?? '')
            ),
            'location' => $user->profile?->address,
            'total_earnings' => (float) $earnings,
        ];
    })
    ->sortByDesc('total_earnings')
    ->values()
    ->take(5);

// ================= REQUEST APPROVALS =================

$requestApprovals = User::where('user_type', 'inspector')
    ->where('status', 'pending')
    ->with('profile.inspectionTypes')
    ->latest()
    ->limit(5)
    ->get()
    ->map(function ($user) {

        return [
            'id' => $user->id,
            'name' => trim(
                ($user->first_name ?? '') . ' ' .
                ($user->last_name ?? '')
            ),
            'email' => $user->email,
            'specializations' => $user->profile?->inspectionTypes
                ? $user->profile->inspectionTypes
                    ->pluck('title')
                    ->values()
                : [],
        ];
    });

// ================= TOP INSPECTION TYPES =================

$topInspectionTypes = DB::table('booking_inspection_type as pivot')
    ->join(
        'inspection_types as t',
        't.id',
        '=',
        'pivot.inspection_type_id'
    )
    ->select(
        't.id',
        't.title',
        DB::raw('COUNT(*) as total_bookings')
    )
    ->groupBy('t.id', 't.title')
    ->orderByDesc('total_bookings')
    ->limit(6)
    ->get()
    ->map(function ($item) {

        $totalBookings = InspectionBooking::count();

        return [
            'id' => $item->id,

            'title' => $item->title,

            'short_name' => collect(
                explode(' ', $item->title)
            )
                ->map(fn($word) => strtoupper(substr($word, 0, 1)))
                ->implode(''),

            'total_bookings' => $item->total_bookings,

            'demand_rate' => $totalBookings > 0
                ? round(
                    ($item->total_bookings / $totalBookings) * 100
                )
                : 0,
        ];
    });

// ================= RECENT INSPECTIONS =================

$recentInspections = InspectionAssign::with([
        'inspectionBooking.inspectionTypes',
        'inspector'
    ])
    ->latest()
    ->limit(5)
    ->get()
    ->map(function ($item) {

        $inspectionType = $item->inspectionBooking
            ?->inspectionTypes
            ?->first();

        return [

            'id' => $item->id,

            'inspection_type' => $inspectionType?->title,

            'short_name' => collect(
                explode(
                    ' ',
                    $inspectionType?->title ?? ''
                )
            )
            ->map(fn($word) => strtoupper(substr($word, 0, 1)))
            ->implode(''),

            'inspector' => trim(
                ($item->inspector?->first_name ?? '') . ' ' .
                ($item->inspector?->last_name ?? '')
            ),

            'status' => ucfirst($item->status),

            'duration' => $item->created_at
                ? $item->created_at->diffForHumans()
                : null,
        ];
    });

// ================= MONTHLY REVENUE =================

$monthlyRevenue = InspectionPayment::selectRaw(
        'MONTH(created_at) as month,
        SUM(total) as revenue'
    )
    ->groupBy('month')
    ->orderBy('month')
    ->get();

return response()->json([
    'success' => true,

    'data' => [

        'stats' => [
            'total_revenue' => (float) $totalRevenue,
            'total_users' => $totalUsers,
            'total_inspectors' => $totalInspectors,
            'pending_approvals' => $pendingApprovals,
            'active_inspections' => $activeInspections,
            'completed_inspections' => $completedInspections,
            'cancelled_inspections' => $cancelledInspections,
            'pending_inspections' => $pendingInspections,
            'revenue_growth' => $revenueGrowth,
        ],

        'recent_users' => $recentUsers,

        'monthly_revenue' => $monthlyRevenue,

        'top_inspectors' => $topInspectors,

        'request_approvals' => $requestApprovals,

        'top_inspection_types' => $topInspectionTypes,

        'recent_inspections' => $recentInspections,
    ]
]);


}
    
}