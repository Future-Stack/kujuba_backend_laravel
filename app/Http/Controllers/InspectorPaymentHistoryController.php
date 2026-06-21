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

        // Base query (reusable)
        $baseQuery = InspectionPayment::where('payout_status', 'paid')
            ->whereHas('inspectionBooking.inspectionAssign', function ($q) use ($userId) {
                $q->where('inspector_id', $userId)
                  ->where('status', 'completed');
            });

        // Total Earnings
        $totalEarning = (clone $baseQuery)->sum('inspector_share');

        // This Month Earnings
        $thisMonthEarning = (clone $baseQuery)
            ->whereMonth('created_at', now()->month)
            ->sum('inspector_share');

        // Weekly Income (last 7 days)
        $weeklyIncome = (clone $baseQuery)
            ->whereBetween('created_at', [now()->subDays(7), now()])
            ->sum('inspector_share');

        // Completed Jobs
        $completedJobs = InspectionAssign::where('inspector_id', $userId)
            ->where('status', 'completed')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'this_month_earning' => (float) $thisMonthEarning,
                'total_earning' => (float) $totalEarning,
                'completed_jobs' => $completedJobs,
                'weekly_income' => (float) $weeklyIncome,
            ]
        ]);
    }

    /**
     * 🟦 Recent Payouts List
     */
    public function index(Request $request)
    {
        $userId = auth()->id();

        $payouts = InspectionPayment::where('payout_status', 'paid')
            ->whereHas('inspectionBooking.inspectionAssign', function ($q) use ($userId) {
                $q->where('inspector_id', $userId);
            })
            ->with([
                'inspectionBooking.inspectionTypes',
                'inspectionBooking.inspectionAssign'
            ])
            ->latest()
            ->get();

        $data = $payouts->map(function ($payment) {

            $assign = $payment->inspectionBooking?->inspectionAssign;
            $type = $payment->inspectionBooking?->inspectionTypes?->first();

            return [
                'id' => $payment->id,
                'title' => $type?->title ?? 'Inspection',
                'amount' => (float) $payment->inspector_share,
                'status' => ucfirst($payment->payout_status),

                'address' => $payment->inspectionBooking?->address
                    ?? $assign?->address
                    ?? null,

                'completed_at' => optional($assign?->completed_at)
                    ?->format('M d, Y h:i A'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'total' => $payouts->count(),
            ]
        ]);
    }

    /**
     * 🟦 Single Payout Details
     */
    public function show($id)
    {
        $userId = auth()->id();

        $payment = InspectionPayment::where('id', $id)
            ->where('payout_status', 'paid')
            ->whereHas('inspectionBooking.inspectionAssign', function ($q) use ($userId) {
                $q->where('inspector_id', $userId);
            })
            ->with([
                'inspectionBooking.inspectionAssign',
                'inspectionBooking.inspectionTypes'
            ])
            ->firstOrFail();

        $assign = $payment->inspectionBooking?->inspectionAssign;
        $type = $payment->inspectionBooking?->inspectionTypes?->first();

        return response()->json([
            'success' => true,
            'data' => [
                'title' => $type?->title ?? 'Inspection',
                'amount' => (float) $payment->inspector_share,
                'address' => $payment->inspectionBooking?->address
                    ?? $assign?->address
                    ?? null,

                'completed_at' => optional($assign?->completed_at)?->format('M d, Y h:i A'),

                'breakdown' => [
                    'inspection_fee' => (float) $payment->total,
                    'platform_fee' => (float) $payment->platform_fee,
                    'inspector_share' => (float) $payment->inspector_share,
                ],

                'payment_method' => 'Stripe',
                'transaction_id' => $payment->trx_id,
                'date_paid' => optional($payment->updated_at)?->format('M d, Y h:i A'),
            ]
        ]);
    }
}