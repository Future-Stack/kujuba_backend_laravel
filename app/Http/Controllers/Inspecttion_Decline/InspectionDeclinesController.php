<?php

namespace App\Http\Controllers\Inspecttion_Decline;

use App\Http\Controllers\Controller;
use App\Models\DeclineInspection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InspectionDeclinesController extends Controller
{
    public function storeDecline(Request $request)
    {
        try {
            // Validate request
            $validated = $request->validate([
                'inspection_booking_id' => 'required|exists:inspection_bookings,id',
            ]);

            // Build data
            $data = [
                'inspector_id'          => Auth::id(), // current inspector
                'inspection_booking_id' => $validated['inspection_booking_id'],
                'status'                => 'declined',
            ];

            // Create record
            $decline = DeclineInspection::create($data);

            return response()->json([
                'success' => true,
                'data'    => $decline
            ], 201);

        } catch (\Exception $e) {
            \Log::error('DeclineInspection store failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
