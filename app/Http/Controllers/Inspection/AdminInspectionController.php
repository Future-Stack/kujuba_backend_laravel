<?php

namespace App\Http\Controllers\Inspection;

use App\Http\Controllers\Controller;
use App\Models\InspectionAssign;
use App\Models\InspectionBooking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class AdminInspectionController extends Controller
{
    public function inspectionMetrics()
    {
        try {
            // Current month and previous month ranges
            $currentMonthStart = now()->startOfMonth();
            $previousMonthStart = now()->subMonth()->startOfMonth();
            $previousMonthEnd = now()->subMonth()->endOfMonth();

            // Current month counts
            $activeCount     = InspectionAssign::whereIn('status', ['assigned', 'started', 'rescheduled'])->count();
            $completedCount  = InspectionAssign::where('status', 'completed')->count();
            $cancelledCount  = InspectionAssign::where('status', 'cancelled')->count();
            $pendingCount    = InspectionBooking::where('status', 'pending')->count();

            // Previous month counts (for percentage comparison)
            $activePrev     = InspectionAssign::whereIn('status', ['assigned', 'started', 'rescheduled'])
                ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])->count();
            $completedPrev  = InspectionAssign::where('status', 'completed')
                ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])->count();
            $cancelledPrev  = InspectionAssign::where('status', 'cancelled')
                ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])->count();
            $pendingPrev    = InspectionBooking::where('status', 'pending')
                ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])->count();

            // Percentage change helper
            $percentChange = function ($current, $previous) {
                if ($previous == 0) return 0;
                return round((($current - $previous) / $previous) * 100, 1);
            };

            $data = [
                'active_inspections' => [
                    'count' => $activeCount,
                    'change' => $percentChange($activeCount, $activePrev),
                ],
                'completed_inspections' => [
                    'count' => $completedCount,
                    'change' => $percentChange($completedCount, $completedPrev),
                ],
                'cancelled_inspections' => [
                    'count' => $cancelledCount,
                    'change' => $percentChange($cancelledCount, $cancelledPrev),
                ],
                'pending_inspections' => [
                    'count' => $pendingCount,
                    'change' => $percentChange($pendingCount, $pendingPrev),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Inspection dashboard summary failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve inspection summary.'
            ], 500);
        }
    }

    public function inspectionManagement(Request $request)
    {
        try {
            $filter = $request->query('filter'); // all, pending, assigned, completed, cancelled

            $query = InspectionBooking::with([
                'payment',
                'inspectionTypes',
                'inspectionAssign.inspector'
            ])->latest();

            // Apply filter logic
            switch ($filter) {
                case 'pending':
                    $query->where('status', 'pending');
                    break;

                case 'assigned':
                    $query->whereHas('inspectionAssign', function ($q) {
                        $q->whereIn('status', ['assigned', 'started', 'rescheduled']);
                    });
                    break;

                case 'completed':
                    $query->whereHas('inspectionAssign', function ($q) {
                        $q->where('status', 'completed');
                    });
                    break;

                case 'cancelled':
                    $query->whereHas('inspectionAssign', function ($q) {
                        $q->where('status', 'cancelled')
                        ->with('cancelRequest');
                    });
                    break;

                default: // all
                    // no filter applied
                    break;
            }

            $bookings = $query->get()->map(function ($booking) {
                $assign = $booking->inspectionAssign;
                $payment = $booking->payment;
                $has_cancel_request = $booking->inspectionAssign->cancelRequest;

                return [
                    'id'                => $booking->id,
                    'inspection_assign_id' => $assign->id ?? null,
                    'inspection_types'  => $booking->inspectionTypes->pluck('title')->toArray(),
                    'property_address'  => $booking->property_address,
                    'property_type'     => $booking->property_type,
                    'property_size'     => $booking->property_size,
                    'property_img'      => $booking->property_img,
                    'urgent_status'     => $booking->urgent_status,
                    'status'            => $booking->status,
                    'assigned_inspector'=> $assign && $assign->inspector
                        ? $assign->inspector->first_name.' '.$assign->inspector->last_name
                        : 'Not assigned yet',
                    'assign_status'     => $assign ? $assign->status : 'unassigned',
                    'user_payment'      => $payment ? ucfirst($payment->status) : 'Unpaid',
                    'inspection_report' => $assign && $assign->status === 'completed' ? 'Submitted' : null,
                    'ins_payment'       => $assign && $assign->status === 'completed' ? 'Released' : null,
                    'has_cancel_request' =>$has_cancel_request ?? null
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $bookings,
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Inspection management fetch failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve inspection management data.'
            ], 500);
        }
    }

    public function availableInspectors()
    {
        try {
            // Count active assignments per inspector
            $inspectors = User::where('user_type', 'inspector')
                ->withCount(['inspectionAssigns as active_assignments' => function ($q) {
                    $q->whereIn('status', ['assigned', 'started', 'rescheduled']);
                }])
                ->orderBy('active_assignments', 'asc') // least workload first
                ->get(['id', 'first_name', 'last_name', 'email']);

            $data = $inspectors->map(function ($inspector) {
                return [
                    'id' => $inspector->id,
                    'name' => $inspector->first_name . ' ' . $inspector->last_name,
                    'email' => $inspector->email,
                    'active_assignments' => $inspector->active_assignments,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Inspector workload fetch failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function exportInspectionData()
    {
        try {
            $inspections = InspectionBooking::with(['inspectionAssign.inspector', 'payment', 'inspectionTypes'])
                ->get();

            $headers = [
                'Column', 'Inspection', 'Urgent', 'Status',
                'User Payment', 'Report', 'Inspector Payment', 'Assigned Inspector'
            ];

            $rows = $inspections->map(function ($booking) {
                $assign = $booking->inspectionAssign;
                $payment = $booking->payment;

                return [
                    ucfirst($booking->status),
                    $booking->inspectionTypes->pluck('title')->implode(', '),
                    $booking->urgent_status ? 'Yes' : 'No',
                    $assign ? ucfirst($assign->status) : 'Not assigned',
                    $payment ? ucfirst($payment->status) : 'N/A',
                    $assign && $assign->status === 'completed' ? 'Submitted' : 'N/A',
                    $assign && $assign->status === 'completed' ? 'Released' : 'N/A',
                    $assign && $assign->inspector
                        ? $assign->inspector->first_name . ' ' . $assign->inspector->last_name
                        : 'Not assigned',
                ];
            });

            // Build CSV content
            $output = implode(',', $headers) . "\n";
            foreach ($rows as $row) {
                $output .= implode(',', $row) . "\n";
            }

            return Response::make($output, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="inspection_data.csv"',
            ]);

        } catch (\Throwable $e) {
            \Log::error('Inspection data export failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export inspection data.'
            ], 500);
        }
    }

    public function bookingDetails($id)
    {
        try {
            $booking = InspectionBooking::with([
                'homeowner',
                'inspectionAssign.inspector',
                'inspectionTypes',
                'payment',
                'reschedule',
                'inspectionAssign.report',
            ])->findOrFail($id);

            $assign = $booking->inspectionAssign;
            $inspector = $assign?->inspector;
            $payment = $booking->inspectionFeePayment;
            $report = $booking->inspectionAssign?->report;
            $reschedule = $booking->reschedule;

            $response = [
                'booking_id' => $booking->id,
                'inspection_title' => $booking->inspectionTypes->pluck('title')->implode(', '),
                'status' => $booking->status,
                'urgent_status' => $booking->urgent_status,
                'scheduled_date' => optional($booking->scheduled_date)->format('F d, Y'),
                'scheduled_time' =>  optional($booking->scheduled_time)
                    ? \Carbon\Carbon::parse($booking->scheduled_time)->format('h:i A')
                    : null,
                'note' => $booking->note ?? null,

                // Homeowner Info
                'homeowner' => [
                    'name' => $booking->homeowner->first_name,
                    'email' => $booking->homeowner->email,
                    'phone' => $booking->homeowner->phone,
                    'address' => $booking->property_address,
                    'property_type' => $booking->property_type,
                    'property_size' => $booking->property_size,
                ],

                // Inspector Info
                'inspector' => $inspector ? [
                    'name'       => $inspector->first_name,
                    'email'      => $inspector->email,
                    'phone'      => $inspector->profile?->phone,
                    'address'    => $inspector->profile?->address,
                    'profile_img'=> $inspector->profile?->profile_img,

                    // License & Insurance
                    'license_number'  => $inspector->profile?->license_number,
                    'license_expiry'  => optional($inspector->profile?->license_expiry)->format('F d, Y'),
                    'insurance_expiry'=> optional($inspector->profile?->insurance_expiry)->format('F d, Y'),

                    // Stripe / Earnings
                    'stripe_account_id' => $inspector->profile?->stripe_account_id,
                    'stripe_customer_id'=> $inspector->profile?->stripe_customer_id,
                    'onboarding_completed' => (bool) $inspector->profile?->stripe_onboarding_completed,
                    'earnings'            => $inspector->earnings,

                    // Specializations (inspection types linked via pivot)
                    'inspection_types' => $inspector->profile?->inspectionTypes->pluck('title')->toArray(),

                    // Assignment metadata
                    'assigned_on' => optional($assign->created_at)->format('F d, Y'),
                ] : null,

                // Payment Info
                'payment' => [
                    'inspection_fee' => $payment?->subtotal,
                    'urgent_fee' => $payment?->urgent_fee ?? 0,
                    'platform_commission' => $payment?->platform_fee ?? 0,
                    'inspector_payout' => $payment?->inspector_share,
                    'method' => 'Credit Card',
                    'status' => $payment?->status,
                    'paid_on' => optional($payment?->created_at)->format('F d, Y'),
                ],

                // Report & Media
                'report' => $report ? [
                    'notes'             => $report->notes ?? null,
                    'homeowner_feedback'=> $report->homeowner_feedback ?? null,
                    'media'             => $report->media ?? [],
                    'report_file'       => $report->report_file ?? null,
                    'is_favorite'       => $report->is_favorite ?? null,
                    'status'            => $report->status ?? null,
                    'started_at'        => $report->started_at ?? null,
                    'completed_at'      => $report->completed_at ?? null,
                    'cancelled_at'      => $report->cancelled_at ?? null,
                    'uploaded_on'       => $report->created_at ?? null,
                ] : null,

                // Reschedule History
                'reschedule_history' => $reschedule ? [
                    'date'   => optional($reschedule->date)->format('F d, Y'),
                    'time'   => optional($reschedule->time)->format('h:i A'),
                    'shift'  => $reschedule->shift,
                    'status' => $reschedule->status,
                    'accepted_inspector' => $reschedule->acceptedInspector?->name,
                ] : null,

                // Activity Timeline
                'timeline' => [
                    ['event' => 'Booking created', 'timestamp' => $booking->created_at],
                    ['event' => 'Payment received', 'timestamp' => $payment?->created_at],
                    ['event' => 'Inspector assigned', 'timestamp' => $assign?->created_at],
                    ['event' => 'Inspection rescheduled', 'timestamp' => $booking->reschedule?->created_at],
                    ['event' => 'Inspection started', 'timestamp' => $assign?->started_at],
                    ['event' => 'Inspection completed', 'timestamp' => $assign?->completed_at],
                    ['event' => 'Report uploaded', 'timestamp' => $report?->created_at],
                    ['event' => 'Payment released to inspector', 'timestamp' => $payment?->released_at],
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $response,
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Inspection booking details fetch failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function suspendInspector($id)
    {
        try {

            // Get the inspector linked to this review
            $inspector = User::where('id',$id)->first();

            // Toggle suspendInspector flag

            $inspector->status = $inspector->status === 'active' ? 'suspended' : 'active';
            $inspector->save();

            return response()->json([
                'success'   => true,
                'inspector' => [
                    'id'         => $inspector->id,
                    'first_name' => $inspector->first_name,
                    'last_name'  => $inspector->last_name,
                    'status'     => $inspector->status,

                ],
                'message'   => 'Inspector suspension status updated successfully'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Suspend inspector failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function markInspectionComplete($id)
    {
        try {
            $assign = InspectionAssign::findOrFail($id);

            $assign->update([
                'status' => 'completed',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Inspection complete status updated successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Inspection assignment not found.'
            ], 404);

        } catch (\Throwable $e) {
            \Log::error('Failed to mark inspection complete: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while updating inspection status.'
            ], 500);
        }
    }

}
