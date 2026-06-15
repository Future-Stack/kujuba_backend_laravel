<?php

namespace App\Http\Controllers\Inspection_Assign;

use App\Http\Controllers\Controller;
use App\Models\InspectionAssign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InspectionAssignsController extends Controller
{
    public function createOrUpdateInspectionAssign(Request $request)
    {
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

            // Create or update record
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

            // If admin performed the action, mark flag
            if ($user->user_type === 'admin') {
                $assign->isAssignedAdmin = true;
                $assign->save();
            }

            return response()->json([
                'success' => true,
                'data'    => $assign
            ], 200);

        } catch (\Exception $e) {
            \Log::error('InspectionAssign createOrUpdate failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong.'
            ], 500);
        }
    }
}
