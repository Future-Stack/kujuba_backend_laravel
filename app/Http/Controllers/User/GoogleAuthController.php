<?php
namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class GoogleAuthController extends Controller
{
    public function tokenLogin(Request $request)
    {
        $request->validate([
            'id_token'  => 'required|string',
            'user_type' => 'required|string|in:homeowner,inspector',
        ]);

        // 1. Verify Google token
        $response = Http::get(
            "https://oauth2.googleapis.com/tokeninfo?id_token={$request->id_token}"
        );

        if ($response->failed()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Google token'
            ], 401);
        }

        $googleUser = $response->json();

        // 2. Get email
        $email = $googleUser['email'] ?? null;

        if (!$email) {
            return response()->json([
                'success' => false,
                'message' => 'Email not found from Google'
            ], 422);
        }

        // 3. Name split
        $nameParts = explode(' ', $googleUser['name'] ?? 'User', 2);

        $firstName = $nameParts[0];
        $lastName  = $nameParts[1] ?? '';

        // 4. SAFE USER TYPE (IMPORTANT)
        // admin block for security
        $allowedTypes = ['homeowner', 'inspector'];

        $userType = in_array($request->user_type, $allowedTypes)
            ? $request->user_type
            : 'homeowner';

        // 5. Create or update user
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email_verified_at' => now(),
                'password'   => Hash::make(Str::random(24)),
                'status'     => 'active',
                'user_type'  => $userType,
            ]
        );

        // 6. Create token
        $token = $user->createToken('google_token')->plainTextToken;

        // 7. Response
        return response()->json([
            'success' => true,
            'message' => 'Google login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ], 200);
    }
}