<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\InspectionPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AdminPaymentController extends Controller
{
    public function paymentMetrics()
    {
        try {
            // Aggregate totals
            $totalRevenue = InspectionPayment::where('payment_type', 'inspection_fee')
                ->where('status', 'paid')
                ->sum('total');

//            $completedPayouts = InspectionPayment::where('status', 'paid')->where('payment_type', 'disbursement')
//                ->sum('inspector_share');
//
//            $pendingPayouts = InspectionPayment::where('status', 'pending')->where('payment_type', 'disbursement')
//                ->sum('inspector_share');

            $totalPlatformFee = InspectionPayment::where('payment_type', 'inspection_fee')->where('status','paid')->sum('platform_fee');
            $totalPaidHomeowner = InspectionPayment::where('payment_type', 'inspection_fee')->where('status','paid')->count();

            $totalRefunded = InspectionPayment::where('payment_type', 'refund')
                ->sum('refunded_amount');

            // Month-over-month growth
            $lastMonthRevenue = InspectionPayment::where('payment_type', 'inspection_fee')
                ->where('status', 'paid')
                ->whereBetween('created_at', [
                    now()->subMonth()->startOfMonth(),
                    now()->subMonth()->endOfMonth()
                ])
                ->sum('total');

            $growthRate = $lastMonthRevenue > 0
                ? round((($totalRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 2)
                : ($totalRevenue > 0 ? 100 : 0);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_revenue' => $totalRevenue,
                    'total_platformFee' => $totalPlatformFee,
                    'total_paid_homeowner' => $totalPaidHomeowner,
                    'total_refunded' => $totalRefunded,
                    'growth_rate' => $growthRate,
                ]
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Revenue summary fetch failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $query = InspectionPayment::with([
                'inspectionBooking.homeowner',
                'inspectionBooking.inspectionAssign.inspector.profile'
            ]);

            // Filters
            if ($request->status) {
                $query->where('status', $request->status);
            }

            if ($request->payment_type) {
                $query->where('payment_type', $request->payment_type);
            }

            if ($request->search) {
                $query->whereHas('inspectionBooking.homeowner', function ($q) use ($request) {
                    $q->where('first_name', 'like', "%{$request->search}%")
                        ->orWhere('last_name', 'like', "%{$request->search}%")
                        ->orWhere('email', 'like', "%{$request->search}%");
                });
            }

            $payments = $query->orderBy('created_at', 'desc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $payments
            ]);

        } catch (\Throwable $e) {
            \Log::error('Payment list fetch failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function exportPaymentData()
    {
        try {
            $payments = InspectionPayment::with([
                'inspectionBooking.homeowner',
                'inspectionBooking.inspectionAssign.inspector'
            ])->get();

            $headers = [
                'Transaction ID', 'Payment Type', 'Status', 'Total Amount',
                'Inspector Share', 'Admin Share', 'Urgent Fee', 'Platform Fee',
                'Booking ID', 'Inspector Name', 'Homeowner Name', 'Created Date'
            ];

            $rows = $payments->map(function ($payment) {
                $booking = $payment->inspectionBooking;
                $inspector = $booking?->inspectionAssign?->inspector;
                $homeowner = $booking?->homeowner;

                return [
                    $payment->trx_id ?? 'N/A',
                    ucfirst(str_replace('_', ' ', $payment->payment_type)),
                    ucfirst($payment->status),
                    number_format($payment->total, 2),
                    number_format($payment->inspector_share, 2),
                    number_format($payment->admin_share, 2),
                    number_format($payment->urgent_fee, 2),
                    number_format($payment->platform_fee, 2),
                    $booking?->id ?? 'N/A',
                    $inspector ? $inspector->first_name . ' ' . $inspector->last_name : 'Not assigned',
                    $homeowner ? $homeowner->first_name . ' ' . $homeowner->last_name : 'N/A',
                    optional($payment->created_at)->format('d M Y'),
                ];
            });

            // Build CSV content
            $output = implode(',', $headers) . "\n";
            foreach ($rows as $row) {
                $output .= implode(',', $row) . "\n";
            }

            return Response::make($output, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="payment_data.csv"',
            ]);

        } catch (\Throwable $e) {
            \Log::error('Payment data export failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export payment data.'
            ], 500);
        }
    }

    public function exportSinglePayment($id)
    {
        try {
            $payment = InspectionPayment::with([
                'inspectionBooking.homeowner',
                'inspectionBooking.inspectionAssign.inspector'
            ])->findOrFail($id);

            $booking   = $payment->inspectionBooking;
            $inspector = $booking?->inspectionAssign?->inspector;
            $homeowner = $booking?->homeowner;

            $headers = [
                'Transaction ID', 'Payment Type', 'Status', 'Total Amount',
                'Inspector Share', 'Admin Share', 'Urgent Fee', 'Platform Fee',
                'Booking ID', 'Inspector Name', 'Homeowner Name', 'Created Date'
            ];

            $row = [
                $payment->trx_id ?? 'N/A',
                ucfirst(str_replace('_', ' ', $payment->payment_type)),
                ucfirst($payment->status),
                number_format($payment->total, 2),
                number_format($payment->inspector_share, 2),
                number_format($payment->admin_share, 2),
                number_format($payment->urgent_fee, 2),
                number_format($payment->platform_fee, 2),
                $booking?->id ?? 'N/A',
                $inspector ? $inspector->first_name . ' ' . $inspector->last_name : 'Not assigned',
                $homeowner ? $homeowner->first_name . ' ' . $homeowner->last_name : 'N/A',
                optional($payment->created_at)->format('d M Y'),
            ];

            // Build CSV content
            $output = implode(',', $headers) . "\n" . implode(',', $row) . "\n";

            return Response::make($output, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="payment_' . $payment->id . '_data.csv"',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment record not found.'
            ], 404);

        } catch (\Throwable $e) {
            \Log::error('Single payment export failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export payment data.'
            ], 500);
        }
    }


}
