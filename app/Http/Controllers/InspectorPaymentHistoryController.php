<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InspectorPayout;
use App\Models\InspectionAssign;
use App\Models\Review;

class InspectorPaymentHistoryController extends Controller
{
    /**
     * 🟦 Overview (Dashboard)
     */
    public function overview(Request $request)
    {
        $userId = auth()->id();

        $payouts = InspectorPayout::where('inspector_id', $userId)
            ->where('status', 'paid');

        $totalEarning = (clone $payouts)->sum('amount');

        $thisMonthEarning = (clone $payouts)
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $lastMonthEarning = (clone $payouts)
            ->whereMonth('paid_at', now()->subMonth()->month)
            ->whereYear('paid_at', now()->subMonth()->year)
            ->sum('amount');

        $weeklyIncome = (clone $payouts)
            ->whereBetween('paid_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])
            ->sum('amount');

        $completedJobs = InspectionAssign::where('inspector_id', $userId)
            ->where('status', 'completed')
            ->count();

        $rating = Review::whereHas('inspectionAssign', function ($q) use ($userId) {
            $q->where('inspector_id', $userId);
        })->avg('rating');

        $growthPercentage = $lastMonthEarning > 0
            ? (($thisMonthEarning - $lastMonthEarning) / $lastMonthEarning) * 100
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_earning' => round($totalEarning, 2),
                'this_month_earning' => round($thisMonthEarning, 2),
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
     * 🟦 Payout History List
     */
    public function index()
    {
        $userId = auth()->id();

        $payouts = InspectorPayout::with([
                'inspectionAssign.inspectionBooking.inspectionTypes'
            ])
            ->where('inspector_id', $userId)
            ->where('status', 'paid')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $payouts->map(function ($payout) {

                $assign  = $payout->inspectionAssign;
                $booking = $assign?->inspectionBooking;
                $type    = $booking?->inspectionTypes?->first();

                return [
                    'payment_id' => $payout->id,
                    'title' => $type?->title ?? 'Inspection',
                    'image' => $type?->img
                        ? asset('storage/' . $type->img)
                        : null,

                    'amount' => (float) $payout->amount,
                    'status' => ucfirst($payout->status),
                    'address' => $booking?->property_address,

                    'completed_at' => optional($assign?->updated_at)
                        ->format('M d, Y h:i A'),

                    'paid_at' => optional($payout->paid_at)
                        ->format('M d, Y h:i A'),
                ];
            })
        ]);
    }

    /**
     * 🟦 Single Payout Details
     */
    public function show($id)
{
    $userId = auth()->id();

    $payout = InspectorPayout::with([
        'inspectionAssign.inspectionBooking.payment',
        'inspectionAssign.inspectionBooking.inspectionTypes'
    ])
    ->where('id', $id)
    ->where('inspector_id', $userId)
    ->where('status', 'paid')
    ->firstOrFail();

    $assign  = $payout->inspectionAssign;
    $booking = $assign?->inspectionBooking;
    $payment = $booking?->payment;
    $type    = $booking?->inspectionTypes?->first();

    return response()->json([
        'success' => true,
        'data' => [
            'title' => $type?->title ?? 'Inspection',

            'image' => $type?->img
                ? asset('storage/' . $type->img)
                : null,

            'status' => ucfirst($payout->status),

            'payment_received' => true,

            'amount' => (float) $payout->amount,

            'address' => $booking?->property_address,

            'completed_at' => optional($assign?->updated_at)
                ->format('M d, Y h:i A'),

            'paid_at' => optional($payout->paid_at)
                ->format('M d, Y h:i A'),

            'transaction_id' => $payout->transaction_id,
            'stripe_transfer_id' => $payout->stripe_transfer_id,

            // ✅ SAME STRUCTURE AS YOU USED
            'payment_breakdown' => [
                'inspection_fee' => (float) ($payment?->total ?? 0),
                'platform_fee' => (float) ($payment?->platform_fee ?? 0),
                'total_payout' => (float) ($payment?->inspector_share ?? 0),
            ],
        ]
    ]);
}
}