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

        $completedInspections = InspectionAssign::where('status', 'completed')->count();
        $cancelledInspections = InspectionAssign::where('status', 'cancelled')->count();
        $pendingInspections = InspectionAssign::where('status', 'pending')->count();

        // =========================
        // GROWTH HELPER USAGE
        // =========================
        $revenueGrowth = $this->growth(
            InspectionPayment::whereMonth('created_at', $lastMonth->month)->sum('total'),
            InspectionPayment::whereMonth('created_at', $now->month)->sum('total')
        );

        $userGrowth = $this->growth(
            User::whereMonth('created_at', $lastMonth->month)->count(),
            User::whereMonth('created_at', $now->month)->count()
        );

        $inspectorGrowth = $this->growth(
            User::where('user_type', 'inspector')->whereMonth('created_at', $lastMonth->month)->count(),
            User::where('user_type', 'inspector')->whereMonth('created_at', $now->month)->count()
        );

        // =========================
        // OTHER GROWTH (FIXED)
        // =========================

        $pendingApprovalGrowth = $this->growth(
            User::where('user_type', 'inspector')->where('status', 'pending')
                ->whereMonth('created_at', $lastMonth->month)->count(),
            User::where('user_type', 'inspector')->where('status', 'pending')
                ->whereMonth('created_at', $now->month)->count()
        );

        $activeGrowth = $this->growth(
            InspectionAssign::whereIn('status', ['assigned','inspection','in_progress'])
                ->whereMonth('created_at', $lastMonth->month)->count(),
            InspectionAssign::whereIn('status', ['assigned','inspection','in_progress'])
                ->whereMonth('created_at', $now->month)->count()
        );

        $completedGrowth = $this->growth(
            InspectionAssign::where('status', 'completed')
                ->whereMonth('created_at', $lastMonth->month)->count(),
            InspectionAssign::where('status', 'completed')
                ->whereMonth('created_at', $now->month)->count()
        );

        $cancelledGrowth = $this->growth(
            InspectionAssign::where('status', 'cancelled')
                ->whereMonth('created_at', $lastMonth->month)->count(),
            InspectionAssign::where('status', 'cancelled')
                ->whereMonth('created_at', $now->month)->count()
        );

        $pendingGrowth = $this->growth(
            InspectionAssign::where('status', 'pending')
                ->whereMonth('created_at', $lastMonth->month)->count(),
            InspectionAssign::where('status', 'pending')
                ->whereMonth('created_at', $now->month)->count()
        );



        //finance 

        // =========================
// FINANCE INSIGHTS (DYNAMIC)
// =========================

            $range = $request->get('range', 'weekly'); 
            // weekly | monthly | yearly

            // DATE RANGE SET
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

            // METRICS
            $financeMetrics = [
                'receive_payment' => InspectionPayment::sum('total'),
                'payout' => InspectionPayment::sum('platform_fee'),
            ];

            // CHART DATA
            $financeChart = InspectionPayment::select(
                    DB::raw($range == 'monthly' ? 'DATE(created_at) as label' : 'DAYNAME(created_at) as label'),
                    DB::raw('SUM(total) as receive'),
                    DB::raw('SUM(platform_fee) as payout')
                )
                ->whereBetween('created_at', [$start, $end])
                ->groupBy('label')
                ->orderBy('label')
                ->get()
                ->map(function ($item) {
                    return [
                        'label' => $item->label,
                        'receive' => (float) $item->receive,
                        'payout' => (float) $item->payout,
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
                    'avatar' => $user->profile?->avatar
                        ? asset('storage/' . $user->profile->avatar)
                        : null,
                ],
            ]);

        // =========================
        // TOP INSPECTORS
        // =========================
        $topInspectors = User::where('user_type', 'inspector')
            ->with('profile')
            ->get()
            ->map(function ($user) {

                $earnings = InspectionPayment::whereHas(
                    'inspectionBooking.inspectionAssign',
                    fn($q) => $q->where('inspector_id', $user->id)
                                ->where('status', 'completed')
                )->sum(DB::raw('COALESCE(total - platform_fee,0)'));

                return [
                    'id' => $user->id,
                    'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                    'profile' => [
                        'phone' => $user->profile?->phone,
                        'address' => $user->profile?->address,
                        'avatar' => $user->profile?->avatar
                            ? asset('storage/' . $user->profile->avatar)
                            : null,
                    ],
                    'total_earnings' => (float) $earnings,
                ];
            })
            ->sortByDesc('total_earnings')
            ->values()
            ->take(5);




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
        // BAR CHART (DATE WISE)
        // =========================
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->subDays(6);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : now();

        $barChart = InspectionAssign::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw("SUM(CASE WHEN status='assigned' THEN 1 ELSE 0 END) as assigned"),
                DB::raw("SUM(CASE WHEN status='in_progress' THEN 1 ELSE 0 END) as started"),
                DB::raw("SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed")
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn($item) => [
                'date' => Carbon::parse($item->date)->format('d M'),
                'assigned' => $item->assigned,
                'started' => $item->started,
                'completed' => $item->completed,
            ]);

        // =========================
        // CIRCLE CHART
        // =========================
        $circleChart = [
            'total_task' => InspectionAssign::count(),
            'assigned_inspection' => InspectionAssign::where('status','assigned')->count(),
            'started_inspection' => InspectionAssign::where('status','in_progress')->count(),
            'completed_inspection' => InspectionAssign::where('status','completed')->count(),
        ];

        // =========================
        // RECENT ACTIVITY
        // =========================
        $recentActivity = InspectionAssign::with(['inspectionBooking','inspector'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($item) => [
                'title' => match($item->status) {
                    'completed' => 'Inspection Completed',
                    'in_progress' => 'Inspection Started',
                    'assigned' => 'New Booking Received',
                    'cancelled' => 'Cancellation Request',
                    default => ucfirst($item->status)
                },
                'description' => ($item->inspectionBooking?->inspectionTypes?->first()?->title ?? 'Inspection')
                    .' - '.trim(($item->inspector?->first_name ?? '').' '.($item->inspector?->last_name ?? '')),
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

                    'pending_approvals' => $pendingApprovals,
                    'pending_approval_growth' => $pendingApprovalGrowth,

                    'active_inspections' => $activeInspections,
                    'active_growth' => $activeGrowth,

                    'completed_inspections' => $completedInspections,
                    'completed_growth' => $completedGrowth,

                    'cancelled_inspections' => $cancelledInspections,
                    'cancelled_growth' => $cancelledGrowth,

                    'pending_inspections' => $pendingInspections,
                    'pending_growth' => $pendingGrowth,
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