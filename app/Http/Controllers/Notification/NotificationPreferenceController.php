<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationPreferenceController extends Controller
{
    public function notificationPreference(Request $request)
    {
        try {
            $userId = Auth::id(); // Sanctum authenticated user

            $validated = $request->validate([
                'new_inspection' => 'boolean',
                'upcoming'       => 'boolean',
                'reschedule'     => 'boolean',
                'cancellation'   => 'boolean',
                'email'          => 'boolean',
            ]);

            $preferences = NotificationPreference::updateOrCreate(
                ['user_id' => $userId],
                $validated
            );

            return response()->json([
                'success' => true,
                'data'    => $preferences,
                'message' => 'Notification preferences updated successfully'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Failed to update notification preferences: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to update notification preferences'
            ], 500);
        }
    }
}
