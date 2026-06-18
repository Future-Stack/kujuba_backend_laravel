<?php

namespace App\Http\Controllers\Inspection;

use App\Http\Controllers\Controller;
use App\Models\InspectionAssign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InspectionController extends Controller
{
//    public function upcomingInspections()
//    {
//        try {
//            $user = Auth::user();
//
//            // Base query with relationships
//            $query = InspectionAssign::with([
//                'inspectionBooking:id,homeowner_id,property_address,property_type,property_img,scheduled_date,scheduled_time,urgent_status,status',
//                'inspectionBooking.inspectionTypes:id,title',
//                'inspector:id,first_name,last_name',
//            ])
//                ->latest();
//
//            // Filter by user type
//            if ($user->user_type === 'inspector') {
//                $query->where('inspector_id', $user->id)
//                    ->whereIn('status', ['assigned', 'inspection', 'rescheduled']);
//            } elseif ($user->user_type === 'homeowner') {
//                $query->whereHas('inspectionBooking', function ($q) use ($user) {
//                    $q->where('homeowner_id', $user->id);
//                });
//            } else {
//                return response()->json([
//                    'success' => false,
//                    'message' => 'Unauthorized user type.'
//                ], 403);
//            }
//
//            $inspections = $query->get()->map(function ($assign) {
//                $booking = $assign->inspectionBooking;
//                return [
//                    'id'                => $assign->id,
//                    'inspection_type'   => $booking->inspectionTypes,
//                    'property_address'  => $booking->property_address,
//                    'property_type'     => $booking->property_type,
//                    'property_img'      => $booking->property_img,
//                    'scheduled_date'    => $booking->scheduled_date,
//                    'scheduled_time'    => $booking->scheduled_time,
//                    'urgent_status'     => $booking->urgent_status,
//                    'status'            => $assign->status,
//                    'inspector'         => $assign->inspector ? $assign->inspector->first_name.' '.$assign->inspector->last_name : null,
//                ];
//            });
//
//            return response()->json([
//                'success' => true,
//                'data'    => $inspections,
//            ], 200);
//
//        } catch (\Exception $e) {
//            \Log::error('Upcoming inspections fetch failed: '.$e->getMessage());
//            return response()->json([
//                'success' => false,
//                'message' => 'Failed to retrieve upcoming inspections.'
//            ], 500);
//        }
//    }
//
//    public function completedInspections()
//    {
//        try {
//            $user = Auth::user();
//
//            // Base query with relationships
//            $query = InspectionAssign::with([
//                'inspectionBooking:id,homeowner_id,property_address,property_type,property_img,scheduled_date,scheduled_time,urgent_status,status',
//                'inspectionBooking.inspectionTypes:id,title',
//                'inspector:id,first_name,last_name',
//            ])
//                ->where('status', 'completed')
//                ->latest();
//
//            // Filter by user type
//            if ($user->user_type === 'inspector') {
//                $query->where('inspector_id', $user->id);
//            } elseif ($user->user_type === 'homeowner') {
//                $query->whereHas('inspectionBooking', function ($q) use ($user) {
//                    $q->where('homeowner_id', $user->id);
//                });
//            } else {
//                return response()->json([
//                    'success' => false,
//                    'message' => 'Unauthorized user type.'
//                ], 403);
//            }
//
//            $inspections = $query->get()->map(function ($assign) {
//                $booking = $assign->inspectionBooking;
//                return [
//                    'id'                => $assign->id,
//                    'inspection_type'   => $booking->inspectionTypes,
//                    'property_address'  => $booking->property_address,
//                    'property_type'     => $booking->property_type,
//                    'property_img'      => $booking->property_img,
//                    'scheduled_date'    => $booking->scheduled_date,
//                    'scheduled_time'    => $booking->scheduled_time,
//                    'urgent_status'     => $booking->urgent_status,
//                    'status'            => $assign->status,
//                    'inspector'         => $assign->inspector ? $assign->inspector->first_name.' '.$assign->inspector->last_name : null,
//                ];
//            });
//
//            return response()->json([
//                'success' => true,
//                'data'    => $inspections,
//            ], 200);
//
//        } catch (\Exception $e) {
//            \Log::error('Upcoming inspections fetch failed: '.$e->getMessage());
//            return response()->json([
//                'success' => false,
//                'message' => 'Failed to retrieve upcoming inspections.'
//            ], 500);
//        }
//    }


    public function statusInspections(Request $request)
    {
        try {
            $filter = $request->query('filter');
            $user = Auth::user();

            // Base query with relationships
            $query = InspectionAssign::with([
                'inspectionBooking:id,homeowner_id,property_address,property_type,property_img,scheduled_date,scheduled_time,urgent_status,status',
                'inspectionBooking.inspectionTypes:id,title',
                'inspector:id,first_name,last_name',
            ])
                ->where('status', $filter)
                ->latest();

            // Filter by user type
            if ($user->user_type === 'inspector') {
                $query->where('inspector_id', $user->id);
            } elseif ($user->user_type === 'homeowner') {
                $query->whereHas('inspectionBooking', function ($q) use ($user) {
                    $q->where('homeowner_id', $user->id);
                });
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized user type.'
                ], 403);
            }

            $inspections = $query->get()->map(function ($assign) {
                $booking = $assign->inspectionBooking;
                return [
                    'id'                => $assign->id,
                    'inspection_type'   => $booking->inspectionTypes,
                    'property_address'  => $booking->property_address,
                    'property_type'     => $booking->property_type,
                    'property_img'      => $booking->property_img,
                    'scheduled_date'    => $booking->scheduled_date,
                    'scheduled_time'    => $booking->scheduled_time,
                    'urgent_status'     => $booking->urgent_status,
                    'status'            => $assign->status,
                    'inspector'         => $assign->inspector ? $assign->inspector->first_name.' '.$assign->inspector->last_name : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $inspections,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Upcoming inspections fetch failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve upcoming inspections.'
            ], 500);
        }
    }

}
