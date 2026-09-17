<?php

namespace App\Http\Controllers\Inspection_Assign;

use App\Http\Controllers\Controller;
use App\Models\InspectionAssign;
use App\Models\InspectionBooking;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InspectionAssignsController extends Controller
{
    public function createOrUpdateInspectionAssign(Request $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'inspection_booking_id' => 'required|exists:inspection_bookings,id',
                // inspector_id is optional if inspector is assigning themselves
                'inspector_id'          => 'nullable|exists:users,id',
                'distance'              => 'nullable|numeric',
                'estimate_time'         => 'nullable|string',
                'status'                => 'nullable|string',
            ]);

            $user = Auth::user();

            // Decide inspector_id source
            $inspectorId = $user->user_type === 'inspector'
                ? $user->id
                : ($validated['inspector_id'] ?? null);

            if (!$inspectorId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inspector ID is required when admin assigns.'
                ], 422);
            }

            $today = now()->toDateString();

            $todayAcceptedCount = InspectionAssign::where('inspector_id', $inspectorId)
                ->whereDate('created_at', $today)
                ->where('inspection_booking_id', '!=', $validated['inspection_booking_id'])
                ->count();

            if ($todayAcceptedCount >= 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already accepted the maximum of 3 bookings for today. You cannot accept a 4th booking.'
                ], 422);
            }

            $assign = InspectionAssign::updateOrCreate(
                [
                    'inspection_booking_id' => $validated['inspection_booking_id'],
                    'inspector_id'          => $inspectorId,
                ],
                [
                    'distance'      => $validated['distance'] ?? null,
                    'estimate_time' => $validated['estimate_time'] ?? null,
                    'status'        => $validated['status'] ?? 'assigned',
                ]
            );

            if ($user->user_type === 'admin') {
                $assign->isAssignedAdmin = true;
                $assign->save();
            }

            $booking = InspectionBooking::find($validated['inspection_booking_id']);
            $booking->update(['status' => 'active']);

            DB::commit();

            // Notify homeowner that an inspector accepted their booking
            try {
                $homeowner = User::find($booking->homeowner_id);
                $inspector = User::find($inspectorId);
                if ($homeowner) {
                    $homeowner->notify(new PlatformNotification([
                        'type'       => 'inspection_accepted',
                        'title'      => 'Inspector Assigned!',
                        'message'    => "Inspector {$inspector->first_name} {$inspector->last_name} has accepted your inspection booking #{$booking->id}.",
                        'booking_id' => $booking->id,
                        'sender_id'  => $inspectorId,
                    ]));
                }
            } catch (\Throwable $e) {
                Log::warning('Homeowner notification failed on assign: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'data'    => $assign
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('InspectionAssign createOrUpdate failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong.'
            ], 500);
        }
    }
    
    // public function createOrUpdateInspectionAssign(Request $request)
    // {
    //     DB::beginTransaction();

    //     try {
    //         $validated = $request->validate([
    //             'inspection_booking_id' => 'required|exists:inspection_bookings,id',
    //             // inspector_id is optional if inspector is assigning themselves
    //             'inspector_id'          => 'nullable|exists:users,id',
    //             'distance'              => 'nullable|numeric',
    //             'estimate_time'         => 'nullable|string',
    //             'status'                => 'nullable|string',
    //         ]);

    //         $user = Auth::user();

    //         // Decide inspector_id source
    //         $inspectorId = $user->user_type === 'inspector'
    //             ? $user->id
    //             : ($validated['inspector_id'] ?? null);

    //         if (!$inspectorId) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Inspector ID is required when admin assigns.'
    //             ], 422);
    //         }


    //         // Create or update record
    //         $assign = InspectionAssign::updateOrCreate(
    //             [
    //                 'inspection_booking_id' => $validated['inspection_booking_id'],
    //                 'inspector_id'          => $inspectorId,
    //             ],
    //             [
    //                 'distance'      => $validated['distance'] ?? null,
    //                 'estimate_time' => $validated['estimate_time'] ?? null,
    //                 'status'        => $validated['status'] ?? 'assigned',
    //             ]
    //         );

    //         // If admin performed the action, mark flag
    //         if ($user->user_type === 'admin') {
    //             $assign->isAssignedAdmin = true;
    //             $assign->save();
    //         }

    //         //Update Booking Status as well
    //         InspectionBooking::where('id', $validated['inspection_booking_id'])->update([
    //             'status'=> 'active'
    //         ]);

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'data'    => $assign
    //         ], 200);

    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         \Log::error('InspectionAssign createOrUpdate failed: '.$e->getMessage());
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Something went wrong.'
    //         ], 500);
    //     }
    // }
}
