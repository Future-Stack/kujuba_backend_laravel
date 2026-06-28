<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\InspectionAssign;
use App\Models\InspectionBooking;
use App\Models\InspectionPayment;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    public function overview(Request $request)
    {
        $now = now();
        $lastMonth = now()->subMonth();

       // =========================
                // BASIC STATS
                // =========================

                $totalRevenue = InspectionPayment::where('status', 'paid')->sum('total');

                $totalUsers = User::where('user_type', 'homeowner')->count();

                $totalInspectors = User::where('user_type', 'inspector')->count();

                // Pending Inspector Approvals
                $pendingInspectors = User::where('user_type', 'inspector')
                    ->where('status', 'pending')
                    ->count();

                // Pending Booking Requests
                $pendingInspections = InspectionBooking::where('status', 'pending')
                    ->count();

                $activeInspections = InspectionAssign::where('status', 'started')->count();

                $completedInspections = InspectionAssign::where('status', 'completed')->count();

                $cancelledInspections = InspectionAssign::where('status', 'cancelled')->count();

        // =========================
        // GROWTH HELPER USAGE
        // =========================
       // Pending Inspector Growth


       $revenueGrowth = $this->growth(
    InspectionPayment::where('status', 'paid')
        ->whereBetween('created_at', [
            $lastMonth->copy()->startOfMonth(),
            $lastMonth->copy()->endOfMonth(),
        ])
        ->sum('total'),

    InspectionPayment::where('status', 'paid')
        ->whereBetween('created_at', [
            $now->copy()->startOfMonth(),
            $now->copy()->endOfMonth(),
        ])
        ->sum('total')
);


$userGrowth = $this->growth(
    User::where('user_type', 'homeowner')
        ->whereMonth('created_at', $lastMonth->month)
        ->count(),

    User::where('user_type', 'homeowner')
        ->whereMonth('created_at', $now->month)
        ->count()
);

$inspectorGrowth = $this->growth(
    User::where('user_type', 'inspector')
        ->whereMonth('created_at', $lastMonth->month)
        ->count(),

    User::where('user_type', 'inspector')
        ->whereMonth('created_at', $now->month)
        ->count()
);


$pendingInspectorGrowth = $this->growth(
    User::where('user_type', 'inspector')
        ->where('status', 'pending')
        ->whereMonth('created_at', $lastMonth->month)
        ->count(),

    User::where('user_type', 'inspector')
        ->where('status', 'pending')
        ->whereMonth('created_at', $now->month)
        ->count()
);

// Pending Booking Growth
$pendingInspectionGrowth = $this->growth(
    InspectionBooking::where('status', 'pending')
        ->whereMonth('created_at', $lastMonth->month)
        ->count(),

    InspectionBooking::where('status', 'pending')
        ->whereMonth('created_at', $now->month)
        ->count()
);

// Active Inspections Growth (Started)
$activeGrowth = $this->growth(
    InspectionAssign::where('status', 'started')
        ->whereMonth('created_at', $lastMonth->month)
        ->count(),

    InspectionAssign::where('status', 'started')
        ->whereMonth('created_at', $now->month)
        ->count()
);

// Completed Inspections Growth
$completedGrowth = $this->growth(
    InspectionAssign::where('status', 'completed')
        ->whereMonth('created_at', $lastMonth->month)
        ->count(),

    InspectionAssign::where('status', 'completed')
        ->whereMonth('created_at', $now->month)
        ->count()
);

// Cancelled Inspections Growth
$cancelledGrowth = $this->growth(
    InspectionAssign::where('status', 'cancelled')
        ->whereMonth('created_at', $lastMonth->month)
        ->count(),

    InspectionAssign::where('status', 'cancelled')
        ->whereMonth('created_at', $now->month)
        ->count()
);

























         
$range = $request->get('range', 'weekly');

// =========================
// DATE RANGE
// =========================
if ($range == 'monthly') {
    $start = now()->startOfMonth();
    $end = now()->endOfMonth();
} elseif ($range == 'yearly') {
    $start = now()->startOfYear();
    $end = now()->endOfYear();
} else {
    $start = now()->startOfWeek();
    $end = now()->endOfWeek();
}

// =========================
// FINANCE METRICS
// =========================
$financeMetrics = [
    'receive_payment' => (float) \App\Models\InspectionPayment::where('status', 'paid')->sum('total'),

    'payout' => (float) \App\Models\InspectorPayout::where('status', 'paid')->sum('amount'),
];


// =========================
// RECEIVE CHART (Payments)
// =========================
$receiveData = \App\Models\InspectionPayment::select(
        DB::raw($range == 'monthly'
            ? 'DATE(created_at) as label'
            : 'DAYNAME(created_at) as label'
        ),
        DB::raw('SUM(total) as receive')
    )
    ->where('status', 'paid')
    ->whereBetween('created_at', [$start, $end])
    ->groupBy('label')
    ->get()
    ->keyBy('label');


// =========================
// PAYOUT CHART (Inspector)
// =========================
$payoutData = \App\Models\InspectorPayout::select(
        DB::raw($range == 'monthly'
            ? 'DATE(created_at) as label'
            : 'DAYNAME(created_at) as label'
        ),
        DB::raw('SUM(amount) as payout')
    )
    ->where('status', 'paid')
    ->whereBetween('created_at', [$start, $end])
    ->groupBy('label')
    ->get()
    ->keyBy('label');


// =========================
// MERGED CHART (FINAL OUTPUT)
// =========================
$labels = $receiveData->keys()
    ->merge($payoutData->keys())
    ->unique()
    ->values();

$financeChart = $labels->map(function ($label) use ($receiveData, $payoutData) {
    return [
        'label' => $label,
        'receive' => (float) ($receiveData[$label]->receive ?? 0),
        'payout' => (float) ($payoutData[$label]->payout ?? 0),
    ];
});











   
// =========================
// RECENT USERS
// =========================
$recentUsers = User::with('profile')
    ->latest()
    ->take(5)
    ->get()
    ->map(fn($user) => [
        'id' => $user->id,
        'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
        'email' => $user->email,
        'status' => $user->status,
        'profile' => [
            'phone' => $user->profile?->phone,
            'address' => $user->profile?->address,
            'avatar' => $user->profile?->profile_img
                ? asset('storage/' . $user->profile->profile_img)
                : null,
        ],
    ]);


// =========================
// TOP INSPECTORS
// =========================
$topInspectors = User::where('user_type', 'inspector')
    ->where('status', 'active')
    ->with('profile')
    ->get()
    ->map(function ($user) {

        $totalEarnings = InspectionPayment::join(
                'inspection_assigns',
                'inspection_assigns.inspection_booking_id',
                '=',
                'inspection_payments.inspection_booking_id'
            )
            ->where('inspection_assigns.inspector_id', $user->id)
            ->where('inspection_payments.status', 'paid')
            ->sum('inspection_payments.inspector_share');

        return [
            'id' => $user->id,
            'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            'profile' => [
                'phone' => $user->profile?->phone,
                'address' => $user->profile?->address,
                'avatar' => $user->profile?->profile_img
                    ? asset('storage/' . $user->profile->profile_img)
                    : null,
            ],
            'total_earnings' => (float) $totalEarnings,
        ];
    })
    ->sortByDesc('total_earnings')
    ->take(5)
    ->values();


            // =========================
// REQUEST APPROVALS (MISSING FIX)
// =========================
$requestApprovals = User::where('user_type', 'inspector')
    ->where('status', 'pending')
    ->with(['profile.inspectionTypes'])
    ->latest()
    ->take(5)
    ->get()
    ->map(function ($user) {

        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return [
            'id' => $user->id,
            'name' => $name,

            'short_name' => collect(explode(' ', $name))
                ->filter()
                ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                ->implode(''),

            'email' => $user->email,

            'profile' => [
                'phone' => $user->profile?->phone,
                'address' => $user->profile?->address,
                'avatar' => $user->profile?->profile_img
                    ? asset('storage/' . $user->profile->profile_img)
                    : null,
            ],

            'inspection_types' => optional($user->profile)
                ->inspectionTypes
                ->map(function ($type) {
                    return [
                        'title' => $type->title,
                        'image' => $type->img
                            ? asset('storage/' . $type->img)
                            : null,
                    ];
                }) ?? [],
        ];
    });
        // =========================
        // TOP INSPECTION TYPES
        // =========================
        $totalBookings = DB::table('booking_inspection_type')->count();

// =========================
// TOP INSPECTION TYPES
// =========================
$topInspectionTypes = DB::table('booking_inspection_type as pivot')
    ->join('inspection_types as t', 't.id', '=', 'pivot.inspection_type_id')
    ->select(
        't.id',
        't.title',
        't.img',
        DB::raw('COUNT(*) as total_bookings')
    )
    ->groupBy('t.id', 't.title', 't.img')
    ->orderByDesc('total_bookings')
    ->limit(6)
    ->get()
    ->map(function ($item) use ($totalBookings) {

        return [
            'id' => $item->id,

            // 🔥 short code (FP, RI etc)
            'short_name' => collect(explode(' ', $item->title))
                ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                ->implode(''),

            'title' => $item->title,

            'image' => $item->img
                ? asset('storage/' . $item->img)
                : null,

            'total_bookings' => (int) $item->total_bookings,

            // 🔥 demand rate %
            'demand_rate' => $totalBookings > 0
                ? round(($item->total_bookings / $totalBookings) * 100) . '%'
                : '0%',
        ];
    });
        // =========================
        // RECENT INSPECTIONS (FIXED)
        // =========================
        $recentInspections = InspectionAssign::with(['inspectionBooking.inspectionTypes','inspector'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'inspection_type' => $item->inspectionBooking?->inspectionTypes?->first()?->title,
                'image' => $item->inspectionBooking?->inspectionTypes?->first()?->img
                    ? asset('storage/' . $item->inspectionBooking->inspectionTypes->first()->img)
                    : null,
                'inspector' => trim(($item->inspector?->first_name ?? '') . ' ' . ($item->inspector?->last_name ?? '')),
                'status' => ucfirst($item->status),
                'duration' => optional($item->created_at)->diffForHumans(),
            ]);

          
           // =========================
            // BAR CHART (ALL DATA)
            // =========================
            $barChart = InspectionAssign::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw("SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned"),
                    DB::raw("SUM(CASE WHEN status = 'started' THEN 1 ELSE 0 END) as started"),
                    DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
                )
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get()
                ->map(function ($item) {
                    return [
                        'date'      => Carbon::parse($item->date)->format('d M'),
                        'assigned'  => (int) $item->assigned,
                        'started'   => (int) $item->started,
                        'completed' => (int) $item->completed,
                    ];
                });


            // =========================
            // CIRCLE CHART (FILTERED)
            // =========================
            $startDate = $request->start_date
                ? Carbon::parse($request->start_date)->startOfDay()
                : now()->subDays(6)->startOfDay();

            $endDate = $request->end_date
                ? Carbon::parse($request->end_date)->endOfDay()
                : now()->endOfDay();

            $circleQuery = InspectionAssign::whereBetween(
                'created_at',
                [$startDate, $endDate]
            );

            $circleChart = [
                'total_task' => (clone $circleQuery)->count(),

                'assigned_inspection' => (clone $circleQuery)
                    ->where('status', 'assigned')
                    ->count(),

                'started_inspection' => (clone $circleQuery)
                    ->where('status', 'started')
                    ->count(),

                'completed_inspection' => (clone $circleQuery)
                    ->where('status', 'completed')
                    ->count(),
            ];
    
        // =========================
        // RECENT ACTIVITY
        // =========================
        $recentActivity = InspectionAssign::with(['inspectionBooking', 'inspector'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($item) => [
                'title' => match ($item->status) {
                    'assigned'    => 'Inspector Assigned',
                    'started'     => 'Inspection Started',
                    'reports'     => 'Inspection Report Submitted',
                    'completed'   => 'Inspection Completed',
                    'cancelled'   => 'Inspection Cancelled',
                    'rescheduled' => 'Inspection Rescheduled',
                    default        => ucfirst($item->status),
                },

                'description' =>
                    ($item->inspectionBooking?->inspectionTypes?->first()?->title ?? 'Inspection')
                    . ' - ' .
                    trim(($item->inspector?->first_name ?? '') . ' ' . ($item->inspector?->last_name ?? '')),

                'time' => optional($item->created_at)->diffForHumans(),
            ]);

        // =========================
        // RESPONSE
        // =========================
        return response()->json([
            'success' => true,
            'data' => [
                  'stats' => [
                    'total_revenue' => (float) $totalRevenue,
                    'revenue_growth' => $revenueGrowth,

                    'total_users' => $totalUsers,
                    'user_growth' => $userGrowth,

                    'total_inspectors' => $totalInspectors,
                    'inspector_growth' => $inspectorGrowth,

                    // Pending Inspector Approval
                    'pending_inspectors' => $pendingInspectors,
                    'pending_inspector_growth' => $pendingInspectorGrowth,

                    // Pending Booking
                    'pending_inspections' => $pendingInspections,
                    'pending_inspection_growth' => $pendingInspectionGrowth,

                    // Active
                    'active_inspections' => $activeInspections,
                    'active_growth' => $activeGrowth,

                    // Completed
                    'completed_inspections' => $completedInspections,
                    'completed_growth' => $completedGrowth,

                    // Cancelled
                    'cancelled_inspections' => $cancelledInspections,
                    'cancelled_growth' => $cancelledGrowth,
                ],
                'recent_users' => $recentUsers,
                'top_inspectors' => $topInspectors,
                'request_approvals' => $requestApprovals,
                'top_inspection_types' => $topInspectionTypes,
                'recent_inspections' => $recentInspections,
                'bar_chart' => $barChart,
                'circle_chart' => $circleChart,
                'recent_activity' => $recentActivity,
                 // 🔥 FINANCE INSIGHTS (ADD THIS)
                'finance_metrics' => $financeMetrics,
                'finance_chart' => $financeChart,
            ]
        ]);
    }

    private function growth($last, $current)
    {
        return $last > 0
            ? round((($current - $last) / $last) * 100, 2)
            : 0;
    }
}