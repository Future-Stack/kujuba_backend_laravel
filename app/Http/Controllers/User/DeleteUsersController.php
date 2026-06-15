<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeleteUsersController extends Controller
{
    public function destroy(Request $request)
    {
        try {
            // Optional: verify password before deletion
            $request->validate([
                'password' => 'required|string',
            ]);

            $user = Auth::user();

            // Verify password
            if (!\Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Incorrect password.'
                ], 403);
            }

            // Delete user (cascade will handle related data)
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully. All associated data has been removed.'
            ], 200);


        } catch (\Exception $e) {
            \Log::error('User deletion failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete account.'
            ], 500);
        }
    }
}
