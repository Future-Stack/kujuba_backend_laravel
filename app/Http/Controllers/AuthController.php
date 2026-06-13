<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use App\Jobs\SendOtpEmail;

class AuthController extends Controller
{
    /**
     * User Registration
     */
    public function register(Request $request)
    {
        try {
            $request->validate([
                'first_name'      => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email'     => 'required|string|email|max:255|unique:users',
                'password'  => ['required', 'string', 'confirmed', Password::min(8)],
                'status'    => 'required|string|max:255',
                'user_type' => 'required|string|max:255',
            ]);

            $otp = random_int(100000, 999999);

            $user = User::create([
                'first_name'          => $request->first_name,
                'last_name'     => $request->last_name,
                'email'         => $request->email,
                'password'      => Hash::make($request->password),
                'status'        => $request->status,
                'user_type'     => $request->user_type,
                'otp'           => $otp,
                'otp_expire_at' => now()->addMinutes(5), 
            ]);

            SendOtpEmail::dispatch($user->id, 'register', $otp);

            return response()->json([
                'success' => true,
                'otp'         => $otp,
                'message' => 'Registration successful. Please check your email for the verification OTP.'
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Register Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again.',
            ], 500);
        }
    }

    /**
     * User Login
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email'    => 'required|string|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid login credentials.'
                ], 401);
            }

            if (is_null($user->email_verified_at)) {
                return response()->json([
                    'success'     => false,
                    'is_verified' => false,
                    'message'     => 'Your email is not verified. Please verify your email first.'
                ], 403);
            }

            if ($user->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is inactive. Please contact support.'
                ], 403);
            }

            $tokenName = config('auth.token_name', 'auth_token');
            $token     = $user->createToken($tokenName)->plainTextToken;

            return response()->json([
                'success'      => true,
                'message'      => 'Logged in successfully.',
                'access_token' => $token,
                'token_type'   => 'Bearer',
                'user'         => $user
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Login Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Login failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Get Authenticated User Details
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'user'    => $request->user()
        ], 200);
    }

    /**
     * User Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.'
        ], 200);
    }

    /**
     * Forgot Password - Request OTP
     */
    public function forgotPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
            ], [
                'email.required' => 'Email field is required.',
                'email.email'    => 'Please enter a valid email address.',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email not found.'
                ], 404);
            }

            // Standardized to 6-digit OTP
            $otp = random_int(100000, 999999); 

            $user->update([
                'otp'           => $otp,
                'otp_expire_at' => now()->addMinutes(5),
            ]);

            SendOtpEmail::dispatch($user->id, 'forgot', $otp);

            return response()->json([
                'success' => true,
                'message' => 'OTP sent to your email for password reset.'
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Forgot Password Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP. Please try again.',
            ], 500);
        }
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'otp'   => 'required|digits:6',
            ], [
                'email.required' => 'Email field is required.',
                'email.email'    => 'Please enter a valid email address.',
                'otp.required'   => 'OTP field is required.',
                'otp.digits'     => 'OTP must be 6 digits.',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email not found.'
                ], 404);
            }

            if (!$user->otp || !$user->otp_expire_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active OTP found. Please request a new OTP.'
                ], 400);
            }

            if (\Illuminate\Support\Carbon::parse($user->otp_expire_at)->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP has expired. Please request a new one.'
                ], 400);
            }

            if ((string)$user->otp !== (string)$request->otp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP. Please try again.'
                ], 400);
            }

            $isRegisterFlow = is_null($user->email_verified_at) || $user->status === 'pending';

            if ($isRegisterFlow) {
                
                $user->email_verified_at = $user->email_verified_at ?? now();
                $user->otp               = null;
                $user->otp_expire_at     = null;
                $user->status            = 'active';
                
                $user->save(); 

                $user = $user->fresh(); 

                $tokenName = config('auth.token_name', 'auth_token');
                $token     = $user->createToken($tokenName)->plainTextToken;

                return response()->json([
                    'success'      => true,
                    'message'      => 'Email verified and logged in successfully.',
                    'access_token' => $token,
                    'token_type'   => 'Bearer',
                    'user'         => $user
                ], 200);
            }

            $user->update([
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully. You can now reset your password.',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Verify OTP Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() . " in Line: " . $e->getLine()
            ], 500);
        }
    }

    /**
     * Resend OTP
     */
    public function resendOtp(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
            ], [
                'email.required' => 'Email field is required.',
                'email.email'    => 'Please enter a valid email address.',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email not found.'
                ], 404);
            }

            $otp = random_int(100000, 999999);

            $user->update([
                'otp'           => $otp,
                'otp_expire_at' => \Illuminate\Support\Carbon::now()->addMinutes(5),
            ]);

            $mailType = is_null($user->email_verified_at) ? 'register' : 'forgot';
            SendOtpEmail::dispatch($user->id, $mailType, $otp);

            return response()->json([
                'success' => true,
                'otp'     => $otp, 
                'message' => 'A new OTP has been sent to your email.',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first();
            return response()->json([
                'success' => false,
                'message' => $firstError,
            ], 422);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Resend OTP Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend OTP. Please try again.',
            ], 500);
        }
    }

    /**
     * Reset Password (With Secure OTP Check)
     */
    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'email'        => 'required|email|exists:users,email',
                'otp'          => 'required|digits:6',
                'new_password' => [
                    'required',
                    'confirmed',
                    Password::min(8)->letters()->mixedCase()->numbers()->symbols()
                ],
            ], [
                'email.required'         => 'Email field is required.',
                'email.exists'           => 'Email not found.',
                'otp.required'           => 'OTP is required to reset password.',
                'new_password.required'  => 'New password is required.',
                'new_password.confirmed' => 'New password confirmation does not match.',
            ]);

            $user = User::where('email', $request->email)->first();

            if ((string)$user->otp !== (string)$request->otp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired session. Please verify OTP again.'
                ], 400);
            }

            $user->update([
                'password'      => Hash::make($request->new_password),
                'otp'           => null,
                'otp_expire_at' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully.',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Reset Password Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password. Please try again.',
            ], 500);
        }
    }
}