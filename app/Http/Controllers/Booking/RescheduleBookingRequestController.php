<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\InspectionAssign;
use App\Models\InspectionBooking;
use App\Models\InspectionPayment;
use App\Models\RescheduleInspection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RescheduleBookingRequestController extends Controller
{


    public function requestReschedule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'inspection_assign_id' => 'required|exists:inspection_assigns,id',
            'inspection_booking_id' => 'required|unique:reschedule_inspections,inspection_booking_id',
            'date' => 'required|date|after:today',
            'time' => 'required',
            'shift' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

       $checkAssign= InspectionAssign::where('id', $request->inspection_assign_id)
           ->where('inspection_booking_id', $request->inspection_booking_id)
            ->first();

        if (!$checkAssign)
        {
            return response()->json([
                'success' => false,
                'message' => 'User not Assigned Yet'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $reschedule = RescheduleInspection::create([
                'inspection_assign_id' => $request->inspection_assign_id,
                'inspection_booking_id' => $request->inspection_booking_id,
                'accepted_inspector_id' => null,
                'date' => $request->date,
                'time' => $request->time,
                'shift' => $request->shift,
                'status' => 'pending',
            ]);

            InspectionAssign::where('id', $request->inspection_assign_id)
                ->update([
                    'status' => 'rescheduled',
                    'isReschedule' => 1
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reschedule request submitted successfully.',
                'data' => $reschedule
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit reschedule request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function declineRequest(string $assign_id)
    {
        try {
            DB::beginTransaction();

            $assign = InspectionAssign::where('id', $assign_id)
                ->where('status', 'rescheduled')
                ->first();

            if (!$assign) {
                return response()->json([
                    'success' => false,
                    'message' => 'No rescheduled assignment found for this ID.'
                ], 404);
            }

            // Delete the related assignment row
            $assign->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reschedule declined and assignment deleted.'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to decline reschedule.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function acceptRequest(string $assign_id)
    {
        try {

            $reschedule = RescheduleInspection::where('inspection_assign_id', $assign_id)->first();

            if (!$reschedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'No reschedule request found for this assignment.'
                ], 404);
            }

            // Update reschedule record
            $reschedule->update([
                'status' => 'accepted',
                'accepted_inspector_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reschedule request accepted successfully.',
                'data'    => $reschedule
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to accept reschedule request.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }



}
