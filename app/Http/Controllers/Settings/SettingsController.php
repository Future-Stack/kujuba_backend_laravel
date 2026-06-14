<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    public function createOrUpdate(Request $request)
    {
        try {
            $validated = $request->validate([
                'platform_name' => 'required|string|max:255',
                'support_mail' => 'required|email',
                'max_inspector_area' => 'required|integer',
                'inspector_response_time' => 'integer|min:1',
                'urgent_booking_lead' => 'integer|min:1',
                'report_deadline' => 'integer|min:1',
                'platform_commission' => 'numeric|min:0|max:100',
                'auto_approve' => 'boolean',
                'urgent_inspection_fee' => 'numeric|min:0',
                'late_cancellation_penalty' => 'numeric|min:0',
                'last_minute_cancel_penalty' => 'numeric|min:0',
            ]);

            // updateOrCreate ensures either update existing row or create new one
            $settings = Setting::updateOrCreate(
                ['id' => 1], // assuming singleton row
                $validated
            );

            return response()->json([
                'success' => true,
                'data' => $settings,
                'message' => 'Setting saved successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Setting create/update failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current settings.
     */
    public function show()
    {
        try {
            $settings = Setting::first();

            if (!$settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'No settings found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $settings
            ], 200);

        } catch (\Exception $e) {
            Log::error('Setting fetch failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch settings'
            ], 500);
        }
    }
}
