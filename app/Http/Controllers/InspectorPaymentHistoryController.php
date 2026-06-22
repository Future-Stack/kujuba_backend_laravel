<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InspectionPayment;
use App\Models\InspectionAssign;

class InspectorPaymentHistoryController extends Controller
{
    /**
     * 🟦 Earnings Overview (Dashboard)
     */
    public function overview(Request $request)
        {
            $userId = auth()->id();

            $payments = InspectionPayment::where('status', 'paid')
                ->whereHas('inspectionBooking.inspectionAssign', function ($q) use ($userId) {
                    $q->where('inspector_id', $userId)
                    ->where('status', 'completed');
                });

            // Total Earnings
            $totalEarning = (clone $payments)->sum('inspector_share');

            // This Month Earnings
            $thisMonthEarning = (clone $payments)
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)
                ->sum('inspector_share');

            // Last Month Earnings
            $lastMonthEarning = (clone $payments)
                ->whereMonth('updated_at', now()->subMonth()->month)
                ->whereYear('updated_at', now()->subMonth()->year)
                ->sum('inspector_share');

            // Weekly Earnings
            $weeklyIncome = (clone $payments)
                ->whereBetween('updated_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ])
                ->sum('inspector_share');

            // Completed Jobs
            $completedJobs = InspectionAssign::where('inspector_id', $userId)
                ->where('status', 'completed')
                ->count();

            // Rating
            $rating = \App\Models\Review::whereHas('inspectionAssign', function ($q) use ($userId) {
                    $q->where('inspector_id', $userId);
                })
                ->avg('rating');

            // Growth %
            $growthPercentage = 0;

            if ($lastMonthEarning > 0) {
                $growthPercentage =
                    (($thisMonthEarning - $lastMonthEarning)
                    / $lastMonthEarning) * 100;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'this_month_earning' => round($thisMonthEarning, 2),
                    'total_earning' => round($totalEarning, 2),
                    'completed_jobs' => $completedJobs,
                    'rating' => round($rating ?? 0, 1),

                    'analytics' => [
                        'weekly_income' => round($weeklyIncome, 2),
                        'last_month_earning' => round($lastMonthEarning, 2),
                        'growth_percentage' => round($growthPercentage, 2),
                    ]
                ]
            ]);
        }

   
    /**
     * Recent Payouts
     */
    public function index()
{
    $userId = auth()->id();

    $payments = InspectionPayment::with([
        'inspectionBooking.inspectionAssign',
        'inspectionBooking.inspectionTypes'
    ])
        ->where('status', 'paid')
        ->where('is_disbursed', true)
        ->whereHas('inspectionBooking.inspectionAssign', function ($q) use ($userId) {
            $q->where('inspector_id', $userId);
        })
        ->latest()
        ->get();

    $data = $payments->map(function ($payment) {

        $booking = $payment->inspectionBooking;
        $assign  = $booking?->inspectionAssign;
        $type    = $booking?->inspectionTypes?->first();

        return [
            'payment_id' => $payment->id,

            'title' => $type?->title ?? 'Inspection',

            // ✅ ADD IMAGE FROM INSPECTION TYPE
            'image' => $type?->img
                ? asset('storage/' . $type->img)
                : null,

            'amount' => (float) $payment->inspector_share,

            'status' => 'Paid',

            'address' => $booking?->property_address,

            'completed_at' => optional($assign?->updated_at)
                ->format('M d, Y h:i A'),
        ];
    });

    return response()->json([
        'success' => true,
        'data' => $data
    ]);
}
    public function show($id)
{
    $userId = auth()->id();

    $payment = InspectionPayment::with([
        'inspectionBooking.inspectionAssign',
        'inspectionBooking.inspectionTypes'
    ])
        ->where('id', $id)
        ->where('status', 'paid')
        ->where('is_disbursed', true)
        ->whereHas('inspectionBooking.inspectionAssign', function ($q) use ($userId) {
            $q->where('inspector_id', $userId);
        })
        ->firstOrFail();

    $booking = $payment->inspectionBooking;
    $assign  = $booking?->inspectionAssign;
    $type    = $booking?->inspectionTypes?->first();

    return response()->json([
        'success' => true,
        'data' => [

            'title' => $type?->title ?? 'Inspection',

            // ✅ IMAGE
            'image' => $type?->img
                ? asset('storage/' . $type->img)
                : null,

            // ✅ STATUS ADDED (same as index style)
            'status' => ucfirst($payment->status ?? 'pending'),

            'payment_received' => true,

            'amount' => (float) $payment->inspector_share,

            'address' => $booking?->property_address,

            'completed_at' => optional($assign?->updated_at)
                ->format('M d, Y h:i A'),

            'payment_breakdown' => [
                'inspection_fee' => (float) $payment->total,
                'platform_fee'   => (float) $payment->platform_fee,
                'total_payout'   => (float) $payment->inspector_share
            ],

            'payment_method' => 'Stripe',

            'date_paid' => optional($payment->updated_at)
                ->format('M d, Y h:i A'),

            'transaction_id' => $payment->trx_id,
            'stripe_transfer_id' => $payment->stripe_id,
        ]
    ]);
}
}