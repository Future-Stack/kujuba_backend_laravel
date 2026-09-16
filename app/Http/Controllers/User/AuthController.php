<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Jobs\SendOtpEmail;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function getProfile(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User context not found or unauthenticated.'
                ], 401);
            }

            $user->load(['profile.inspectionTypes']);

            $responseData = [
                'id'          => $user->id,
                'first_name'  => $user->first_name,
                'last_name'   => $user->last_name,
                'email'       => $user->email,
                'status'      => $user->status,
                'user_type'   => $user->user_type, 
                'permissions' => $user->permissions ?? [], 
                'profile'     => $user->profile ? [
                    'id'                          => $user->profile->id,
                    'address'                     => $user->profile->address,
                    'zip_code'                    => $user->profile->zip_code,
                    'latitude'                    => $user->profile->latitude ? (float) $user->profile->latitude : null,
                    'longitude'                   => $user->profile->longitude ? (float) $user->profile->longitude : null,
                    'service_radius'              => (float) ($user->profile->service_radius ?? 50.00),
                    'phone'                       => $user->profile->phone,
                    'profile_img'                 => $user->profile->profile_img
                                                        ? asset('storage/' . $user->profile->profile_img)
                                                        : asset('defaults/placeholder.png'),
                    'license_number'              => $user->profile->license_number,
                    'license_expiry'              => $user->profile->license_expiry,
                    'insurance_expiry'            => $user->profile->insurance_expiry,
                    'stripe_account_id'           => $user->profile->stripe_account_id,
                    'stripe_customer_id'          => $user->profile->stripe_customer_id,
                    'stripe_onboarding_completed' => (bool) $user->profile->stripe_onboarding_completed,
                    'inspection_types'            => $user->profile->inspectionTypes->map(function ($type) {
                        return [
                            'id'         => $type->id,
                            'title'      => $type->title,
                            'short_desc' => $type->short_desc,
                            'price'      => floatval($type->price),
                            'img'        => $type->img ? asset('storage/' . $type->img) : null,
                        ];
                    }),
                ] : null,
            ];

            return response()->json([
                'success' => true,
                'message' => 'User profile retrieved successfully.',
                'data'    => $responseData,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile data: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function register(Request $request)
    {
        $request->validate([
            'first_name'            => 'required|string|max:255',
            'last_name'             => 'required|string|max:255',
            'email'                 => 'required|string|email|max:255|unique:users',
            'password'              => ['required', 'string', 'confirmed', Password::min(8)],
            'user_type'             => 'required|string|in:inspector,homeowner',

            'device_token'          => 'nullable|string',
            'address'               => 'nullable|string',

            // Inspector can add up to 3 ZIP codes
            'zip_code'              => $request->user_type === 'inspector'
                ? 'required|array|max:3'
                : 'nullable|array|max:3',

            'zip_code.*'            => 'string|max:20',

            'latitude'              => 'nullable|numeric',
            'longitude'             => 'nullable|numeric',

            // Default: 50 miles
            'service_radius'        => 'nullable|numeric|min:1|max:500',

            'phone'                 => 'nullable|string|max:50',
            'profile_img'           => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',

            'license_number'        => 'nullable|string|max:255',
            'license_expiry'        => 'nullable|date_format:Y-m-d',
            'insurance_expiry'      => 'nullable|date_format:Y-m-d',

            'inspection_type_ids'   => 'nullable|array',
            'inspection_type_ids.*' => 'integer|exists:inspection_types,id',
        ]);

        DB::beginTransaction();

        try {
            $status = $request->user_type === 'inspector'
                ? 'pending'
                : 'active';

            $otp = random_int(1000, 9999);

            /*
            |--------------------------------------------------------------------------
            | Create User
            |--------------------------------------------------------------------------
            */
            $user = User::create([
                'first_name'    => $request->first_name,
                'last_name'     => $request->last_name,
                'email'         => $request->email,
                'password'      => Hash::make($request->password),
                'status'        => $status,
                'user_type'     => $request->user_type,
                'device_token'  => $request->device_token,
                'otp'           => $otp,
                'otp_expire_at' => now()->addMinutes(5),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Profile Image
            |--------------------------------------------------------------------------
            */
            $profileImgPath = null;

            if ($request->hasFile('profile_img')) {
                $profileImgPath = $request->file('profile_img')
                    ->store('profiles', 'public');
            }

            /*
            |--------------------------------------------------------------------------
            | ZIP Codes & Coordinates
            |--------------------------------------------------------------------------
            |
            | zip_code is now an array.
            | Example:
            | ["90210", "90211", "90212"]
            |
            | We use the first ZIP code as the primary ZIP
            | for latitude/longitude lookup.
            |
            |--------------------------------------------------------------------------
            */
            $zipCodes = $request->zip_code ?? [];

            $lat = $request->latitude;
            $lng = $request->longitude;

            if (
                (is_null($lat) || is_null($lng)) &&
                !empty($zipCodes)
            ) {
                $primaryZip = $zipCodes[0] ?? null;

                if ($primaryZip) {
                    $coords = \App\Services\GeoLocationService::getCoordinatesByZipCode(
                        $primaryZip
                    );

                    if ($coords) {
                        $lat = $coords['latitude'];
                        $lng = $coords['longitude'];
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Create Profile
            |--------------------------------------------------------------------------
            */
            $profile = Profile::create([
                'user_id'                     => $user->id,
                'address'                     => $request->address ?? null,

                // JSON array of up to 3 ZIP codes
                'zip_code'                    => !empty($zipCodes)
                    ? $zipCodes
                    : null,

                'latitude'                    => $lat,
                'longitude'                   => $lng,

                // Default service radius = 50 miles
                'service_radius'              => $request->service_radius ?? 50.00,

                'profile_img'                 => $profileImgPath,
                'phone'                       => $request->phone ?? null,

                'license_number'              => $request->license_number ?? null,
                'license_expiry'              => $request->license_expiry ?? null,
                'insurance_expiry'            => $request->insurance_expiry ?? null,

                'stripe_account_id'           => null,
                'stripe_customer_id'          => null,
                'stripe_onboarding_completed' => false,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Inspector Inspection Types
            |--------------------------------------------------------------------------
            */
            if (
                $request->user_type === 'inspector' &&
                $request->has('inspection_type_ids')
            ) {
                $profile->inspectionTypes()->attach(
                    $request->inspection_type_ids
                );
            }

            DB::commit();

            /*
            |--------------------------------------------------------------------------
            | Send OTP
            |--------------------------------------------------------------------------
            */
            SendOtpEmail::dispatch(
                $user->id,
                'register',
                $otp
            );

            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Please check your email for OTP verification.',
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);

        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Registration Failure: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again.',
            ], 500);
        }
    }


   public function updateProfile(Request $request)
{
    try {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated user context.',
            ], 401);
        }

        $request->validate([
            'first_name'            => 'required|string|max:255',
            'last_name'             => 'required|string|max:255',
            'address'               => 'nullable|string',

            // Up to 3 ZIP codes
            'zip_code'              => 'nullable|array|max:3',
            'zip_code.*'            => 'string|max:20',

            'latitude'              => 'nullable|numeric',
            'longitude'             => 'nullable|numeric',

            // Default remains 50 miles
            'service_radius'        => 'nullable|numeric|min:1|max:500',

            'phone'                 => 'nullable|string|max:50',
            'profile_img'           => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',

            'license_number'        => 'nullable|string|max:255',
            'license_expiry'        => 'nullable|date_format:Y-m-d',
            'insurance_expiry'      => 'nullable|date_format:Y-m-d',

            'inspection_type_ids'   => 'nullable|array',
            'inspection_type_ids.*' => 'integer|exists:inspection_types,id',
        ]);

        DB::beginTransaction();

        /*
        |--------------------------------------------------------------------------
        | Update User
        |--------------------------------------------------------------------------
        */
        $user->update([
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Get / Create Profile
        |--------------------------------------------------------------------------
        */
        $profile = Profile::firstOrCreate([
            'user_id' => $user->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Profile Image
        |--------------------------------------------------------------------------
        */
        if ($request->hasFile('profile_img')) {
            if (
                $profile->profile_img &&
                Storage::disk('public')->exists($profile->profile_img)
            ) {
                Storage::disk('public')->delete($profile->profile_img);
            }

            $profile->profile_img = $request->file('profile_img')
                ->store('profiles', 'public');
        }

        /*
        |--------------------------------------------------------------------------
        | Address
        |--------------------------------------------------------------------------
        */
        if ($request->has('address')) {
            $profile->address = $request->address;
        }

        /*
        |--------------------------------------------------------------------------
        | ZIP Codes
        |--------------------------------------------------------------------------
        |
        | Example:
        | ["90210", "90211", "90212"]
        |
        | The first ZIP is used for coordinate lookup.
        |--------------------------------------------------------------------------
        */
        if ($request->has('zip_code')) {

            $zipCodes = $request->input('zip_code') ?? [];

            $profile->zip_code = $zipCodes;

            /*
            |--------------------------------------------------------------------------
            | Get coordinates from the primary ZIP
            |--------------------------------------------------------------------------
            */
            if (
                (!$request->filled('latitude') || !$request->filled('longitude')) &&
                !empty($zipCodes)
            ) {
                $primaryZip = $zipCodes[0] ?? null;

                if ($primaryZip) {
                    $coords = \App\Services\GeoLocationService::getCoordinatesByZipCode(
                        $primaryZip
                    );

                    if ($coords) {
                        $profile->latitude = $coords['latitude'];
                        $profile->longitude = $coords['longitude'];
                    }
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Latitude / Longitude
        |--------------------------------------------------------------------------
        |
        | If user explicitly sends coordinates, they take priority.
        |--------------------------------------------------------------------------
        */
        if ($request->filled('latitude')) {
            $profile->latitude = $request->latitude;
        }

        if ($request->filled('longitude')) {
            $profile->longitude = $request->longitude;
        }

        /*
        |--------------------------------------------------------------------------
        | Service Radius
        |--------------------------------------------------------------------------
        */
        if ($request->has('service_radius')) {
            $profile->service_radius = $request->service_radius;
        } elseif (!$profile->service_radius) {
            $profile->service_radius = 50.00;
        }

        /*
        |--------------------------------------------------------------------------
        | Phone
        |--------------------------------------------------------------------------
        */
        if ($request->has('phone')) {
            $profile->phone = $request->phone;
        }

        /*
        |--------------------------------------------------------------------------
        | Inspector-specific Information
        |--------------------------------------------------------------------------
        */
        if ($user->user_type === 'inspector') {

            if ($request->has('license_number')) {
                $profile->license_number = $request->license_number;
            }

            if ($request->has('license_expiry')) {
                $profile->license_expiry = $request->license_expiry;
            }

            if ($request->has('insurance_expiry')) {
                $profile->insurance_expiry = $request->insurance_expiry;
            }

            /*
            |--------------------------------------------------------------------------
            | Inspection Types
            |--------------------------------------------------------------------------
            */
            if ($request->has('inspection_type_ids')) {
                $selectedTypes = $request->input('inspection_type_ids') ?? [];

                $profile->inspectionTypes()->sync($selectedTypes);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Save Profile
        |--------------------------------------------------------------------------
        */
        $profile->save();

        DB::commit();

        /*
        |--------------------------------------------------------------------------
        | Load Relationships
        |--------------------------------------------------------------------------
        */
        $user->load(['profile.inspectionTypes']);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data'    => [
                'id'         => $user->id,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'email'      => $user->email,
                'user_type'  => $user->user_type,

                'profile' => [
                    'id'                          => $profile->id,
                    'address'                     => $profile->address,
                    'zip_code'                    => $profile->zip_code,

                    'latitude'                    => $profile->latitude
                        ? (float) $profile->latitude
                        : null,

                    'longitude'                   => $profile->longitude
                        ? (float) $profile->longitude
                        : null,

                    'service_radius'              => (float) (
                        $profile->service_radius ?? 50.00
                    ),

                    'phone'                       => $profile->phone,

                    'profile_img'                 => $profile->profile_img
                        ? asset('storage/' . $profile->profile_img)
                        : asset('defaults/placeholder.png'),

                    'license_number'              => $profile->license_number,
                    'license_expiry'              => $profile->license_expiry,
                    'insurance_expiry'            => $profile->insurance_expiry,

                    'stripe_account_id'           => $profile->stripe_account_id,
                    'stripe_customer_id'          => $profile->stripe_customer_id,
                    'stripe_onboarding_completed' => (bool) $profile->stripe_onboarding_completed,

                    'inspection_types'            => $profile->inspectionTypes->map(function ($type) {
                        return [
                            'id'    => $type->id,
                            'title' => $type->title,
                            'price' => (float) $type->price,
                        ];
                    }),
                ],
            ],
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {

        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => collect($e->errors())->flatten()->first(),
        ], 422);

    } catch (\Exception $e) {

        DB::rollBack();

        Log::error('Profile Update Error: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to update profile. Please try again.',
        ], 500);
    }
}

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
                    'message' => 'Invalid login credentials.',
                ], 401);
            }

            if (is_null($user->email_verified_at)) {
                return response()->json([
                    'success'     => false,
                    'is_verified' => false,
                    'message'     => 'Your email is not verified. Please verify your email first.',
                ], 403);
            }

            if ($user->status !== 'active') {
                $message = $user->status === 'pending'
                    ? 'Your account is under admin review. Please wait for approval.'
                    : 'Your account is inactive. Please contact support.';

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 403);
            }

            if ($request->filled('device_token')) {
                $user->update(['device_token' => $request->device_token]);
            }

            $token = $user->createToken(config('auth.token_name', 'auth_token'))->plainTextToken;

            return response()->json([
                'success'      => true,
                'message'      => 'Logged in successfully.',
                'access_token' => $token,
                'token_type'   => 'Bearer',
                'user'         => $user,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Login Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Login failed. Please try again.',
            ], 500);
        }
    }


    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'user'    => $request->user(),
        ], 200);
    }


    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ], 200);
    }

    public function updateDeviceToken(Request $request)
    {
        $request->validate([
            'device_token' => 'required|string',
        ]);

        $user = $request->user();
        $user->update([
            'device_token' => $request->device_token,
        ]);

        return response()->json([
            'success'      => true,
            'message'      => 'Device token updated successfully.',
            'device_token' => $user->device_token,
        ], 200);
    }


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
                    'message' => 'Email not found.',
                ], 404);
            }

            $otp = random_int(1000, 9999);

            $user->update([
                'otp'           => $otp,
                'otp_expire_at' => now()->addMinutes(5),
            ]);

            SendOtpEmail::dispatch($user->id, 'forgot', $otp);

            return response()->json([
                'success' => true,
                'message' => 'OTP sent to your email for password reset.',
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


    public function verifyOtp(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'otp'   => 'required|digits:4',
            ], [
                'email.required' => 'Email field is required.',
                'email.email'    => 'Please enter a valid email address.',
                'otp.required'   => 'OTP field is required.',
                'otp.digits'     => 'OTP must be 4 digits.',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email not found.',
                ], 404);
            }

            if (!$user->otp || !$user->otp_expire_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active OTP found. Please request a new OTP.',
                ], 400);
            }

            if (Carbon::parse($user->otp_expire_at)->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP has expired. Please request a new one.',
                ], 400);
            }

            if ((string) $user->otp !== (string) $request->otp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP. Please try again.',
                ], 400);
            }

            $isRegisterFlow = is_null($user->email_verified_at);

            $user->update([
                'otp'               => null,
                'otp_expire_at'     => null,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);

            if ($isRegisterFlow) {
                $user = $user->fresh();

                if ($user->user_type === 'inspector') {
                    return response()->json([
                        'success' => true,
                        'message' => 'Email verified successfully. Your account is under admin review. Please wait for approval.',
                    ], 200);
                }

                $token = $user->createToken(config('auth.token_name', 'auth_token'))->plainTextToken;

                return response()->json([
                    'success'      => true,
                    'message'      => 'Email verified and logged in successfully.',
                    'access_token' => $token,
                    'token_type'   => 'Bearer',
                    'user'         => $user,
                ], 200);
            }

            Cache::put('password_reset_approved_' . $user->email, true, now()->addMinutes(15));

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
            Log::error('Verify OTP Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'OTP verification failed. Please try again.',
            ], 500);
        }
    }


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
                    'message' => 'Email not found.',
                ], 404);
            }

            $otp = random_int(1000, 9999);

            $user->update([
                'otp'           => $otp,
                'otp_expire_at' => Carbon::now()->addMinutes(5),
            ]);

            $mailType = is_null($user->email_verified_at) ? 'register' : 'forgot';
            SendOtpEmail::dispatch($user->id, $mailType, $otp);

            return response()->json([
                'success' => true,
                'message' => 'A new OTP has been sent to your email.',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Resend OTP Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend OTP. Please try again.',
            ], 500);
        }
    }


    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'email'        => 'required|email|exists:users,email',
                'new_password' => [
                    'required',
                    'confirmed',
                    Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
                ],
            ], [
                'email.required'         => 'Email field is required.',
                'email.exists'           => 'Email not found.',
                'new_password.required'  => 'New password is required.',
                'new_password.confirmed' => 'New password confirmation does not match.',
            ]);

            $cacheKey = 'password_reset_approved_' . $request->email;

            if (!Cache::get($cacheKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired session. Please verify OTP again.',
                ], 400);
            }

            $user = User::where('email', $request->email)->first();

            $user->update([
                'password' => Hash::make($request->new_password),
            ]);

            Cache::forget($cacheKey);

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


    public function changePassword(Request $request)
    {
        try {
            $request->validate([
                'current_password' => 'required',
                'new_password'     => ['required', 'confirmed', Password::min(8)],
            ]);

            $user = $request->user();

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect.',
                ], 400);
            }

            $user->update([
                'password' => Hash::make($request->new_password),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully.',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Change Password Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password. Please try again.',
            ], 500);
        }
    }
}