<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Profile;
use App\Models\InspectionAssign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class UserManagementController extends Controller
{
    /**
 * USER LIST + STATS
 */
public function index(Request $request)
{
    $users = User::query();

    /**
     *  FILTER BY USER TYPE (homeowner / inspector)
     */
    if ($request->filled('user_type')) {
        $users->where('user_type', $request->user_type);
    } else {
        // default only these two
        $users->whereIn('user_type', ['homeowner', 'inspector']);
    }

    /**
     * 🔍 SEARCH
     */
    if ($request->filled('search')) {
        $search = $request->search;

        $users->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%$search%")
              ->orWhere('last_name', 'like', "%$search%")
              ->orWhere('email', 'like', "%$search%");
        });
    }

    /**
     *  RELATIONS + STATS
     */
    $users = $users->with('profile')
        ->withCount([
            'inspectionBookings as total_inspections',
            'inspectionBookings as cancelled_inspections' => function ($q) {
                $q->where('status', 'cancelled');
            },
            'inspectionBookings as completed_inspections' => function ($q) {
                $q->where('status', 'completed');
            }
        ])
        ->latest()
        ->paginate(10);

    /**
     *  FORMAT RESPONSE (add profile image)
     */
    $users->getCollection()->transform(function ($user) {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->profile?->phone,
            'user_type' => $user->user_type,
            'status' => $user->status,

           
            // PROFILE IMAGE
            'image' => $user->profile?->profile_img
                ? asset('storage/' . $user->profile->profile_img)
                : null,

                'address' => $user->profile?->address,
            // STATS
            'total_inspections' => $user->total_inspections,
            'cancelled_inspections' => $user->cancelled_inspections,
            'completed_inspections' => $user->completed_inspections,
            //  DATES (ADD THIS)
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            ];
    });

    return response()->json([
        'success' => true,
        'data' => $users
    ]);
}
    
    /**
 *  USER DETAILS
 */
public function show($id)
{
    $user = User::with('profile')->findOrFail($id);

    return response()->json([
        'success' => true,
        'data' => [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->profile?->phone,
            'user_type' => $user->user_type,
            'status' => $user->status,

            // PROFILE IMAGE
            'image' => $user->profile?->profile_img
                ? asset('storage/' . $user->profile->profile_img)
                : null,
                'address' => $user->profile?->address,

            // STATS
            'total_inspections' => $user->inspectionBookings()->count(),

           
            'cancelled_inspections' => InspectionAssign::where('status', 'cancelled')
                ->whereHas('inspectionBooking', function ($q) use ($user) {
                    $q->where('homeowner_id', $user->id);
                })
                ->count(),
            // DATES (NEW)
            'created_at' => $user->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $user->updated_at?->format('Y-m-d H:i:s'),
        ]
    ]);
}

    /**
     * ➕ CREATE USER / INSPECTOR MANUALLY (ADMIN)
     */
    public function store(Request $request)
    {
        $request->validate([
            'first_name'            => 'required|string|max:255',
            'last_name'             => 'required|string|max:255',
            'email'                 => 'required|email|unique:users,email',
            'password'              => 'required|string|min:6',
            'user_type'             => 'required|in:homeowner,inspector',
            'phone'                 => 'nullable|string|max:20',
            'address'               => 'nullable|string|max:255',
            'profile_img'           => 'nullable|image|mimes:jpg,jpeg,png|max:5120',

            // Inspector-specific fields
            'license_number'        => 'nullable|string|max:100',
            'license_expiry'        => 'nullable|date',
            'insurance_expiry'      => 'nullable|date',
            'inspection_type_ids'   => 'nullable|array',
            'inspection_type_ids.*' => 'integer|exists:inspection_types,id',
        ]);

        DB::beginTransaction();
        try {
            // 1. Create User
            $user = User::create([
                'first_name'        => $request->first_name,
                'last_name'         => $request->last_name,
                'email'             => $request->email,
                'password'          => Hash::make($request->password),
                'user_type'         => $request->user_type,
                'status'            => 'active',
                'email_verified_at' => now(),
            ]);

            // 2. Profile Image Upload
            $imagePath = null;
            if ($request->hasFile('profile_img')) {
                $imagePath = $request->file('profile_img')->store('profiles', 'public');
            }

            // 3. Create Profile
            $profileData = [
                'user_id'          => $user->id,
                'phone'            => $request->phone,
                'address'          => $request->address,
                'profile_img'      => $imagePath,
            ];

            if ($request->user_type === 'inspector') {
                $profileData['license_number']   = $request->license_number;
                $profileData['license_expiry']   = $request->license_expiry;
                $profileData['insurance_expiry'] = $request->insurance_expiry;
            }

            $profile = Profile::create($profileData);

            // 4. Attach specializations if inspector
            if ($request->user_type === 'inspector' && $request->filled('inspection_type_ids')) {
                $profile->inspectionTypes()->sync($request->inspection_type_ids);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => ucfirst($request->user_type) . ' created successfully',
                'data'    => $user->load('profile.inspectionTypes')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * SUSPEND USER
     */
    public function suspend($id)
    {
        $user = User::findOrFail($id);

        $user->update([
            'status' => 'suspended'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User suspended successfully'
        ]);
    }

    /**
     * 🟢 UNSUSPEND USER
     */
    public function unsuspend($id)
    {
        $user = User::findOrFail($id);

        $user->update([
            'status' => 'active'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User activated successfully'
        ]);
    }

    /**
     * 🗑️ DELETE USER
     */
    public function destroy($id)
    {
        $user = User::with('profile')->findOrFail($id);

        // Security check: prevent deleting super admin
        if ($user->user_type === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Admin accounts cannot be deleted.'
            ], 403);
        }

        // Delete profile image if exists
        if ($user->profile && $user->profile->profile_img) {
            Storage::disk('public')->delete($user->profile->profile_img);
        }

        // Delete profile record
        if ($user->profile) {
            $user->profile->delete();
        }

        // Delete user tokens
        $user->tokens()->delete();

        // Delete user
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }


   public function stats(Request $request)
{
    $userType = $request->user_type; // homeowner / inspector

    $now = Carbon::now();
    $lastMonth = Carbon::now()->subMonth();

    // ================= TOTAL USERS =================
    $totalUsers = User::where('user_type', $userType)->count();

    $currentMonthUsers = User::where('user_type', $userType)
        ->whereMonth('created_at', $now->month)
        ->whereYear('created_at', $now->year)
        ->count();

    $lastMonthUsers = User::where('user_type', $userType)
        ->whereMonth('created_at', $lastMonth->month)
        ->whereYear('created_at', $lastMonth->year)
        ->count();

    $userGrowth = $lastMonthUsers > 0
        ? round((($currentMonthUsers - $lastMonthUsers) / $lastMonthUsers) * 100, 2)
        : ($currentMonthUsers > 0 ? 100 : 0);

    // ================= ACTIVE USERS =================
    $activeUsers = User::where('user_type', $userType)
        ->where('status', 'active')
        ->count();

    $currentMonthActive = User::where('user_type', $userType)
        ->where('status', 'active')
        ->whereMonth('created_at', $now->month)
        ->whereYear('created_at', $now->year)
        ->count();

    $lastMonthActive = User::where('user_type', $userType)
        ->where('status', 'active')
        ->whereMonth('created_at', $lastMonth->month)
        ->whereYear('created_at', $lastMonth->year)
        ->count();

    $activeGrowth = $lastMonthActive > 0
        ? round((($currentMonthActive - $lastMonthActive) / $lastMonthActive) * 100, 2)
        : ($currentMonthActive > 0 ? 100 : 0);

    // ================= PENDING USERS =================
    $pendingUsers = User::where('user_type', $userType)
        ->where('status', 'pending')
        ->count();

    $currentMonthPending = User::where('user_type', $userType)
        ->where('status', 'pending')
        ->whereMonth('created_at', $now->month)
        ->whereYear('created_at', $now->year)
        ->count();

    $lastMonthPending = User::where('user_type', $userType)
        ->where('status', 'pending')
        ->whereMonth('created_at', $lastMonth->month)
        ->whereYear('created_at', $lastMonth->year)
        ->count();

    $pendingGrowth = $lastMonthPending > 0
        ? round((($currentMonthPending - $lastMonthPending) / $lastMonthPending) * 100, 2)
        : ($currentMonthPending > 0 ? 100 : 0);

    // ================= SUSPENDED USERS =================
    $suspendedUsers = User::where('user_type', $userType)
        ->where('status', 'suspended')
        ->count();

    $currentMonthSuspended = User::where('user_type', $userType)
        ->where('status', 'suspended')
        ->whereMonth('created_at', $now->month)
        ->whereYear('created_at', $now->year)
        ->count();

    $lastMonthSuspended = User::where('user_type', $userType)
        ->where('status', 'suspended')
        ->whereMonth('created_at', $lastMonth->month)
        ->whereYear('created_at', $lastMonth->year)
        ->count();

    $suspendedGrowth = $lastMonthSuspended > 0
        ? round((($currentMonthSuspended - $lastMonthSuspended) / $lastMonthSuspended) * 100, 2)
        : ($currentMonthSuspended > 0 ? 100 : 0);

    return response()->json([
        'success' => true,
        'data' => [
            'user_type' => $userType,

            'total_users' => $totalUsers,
            'user_growth_percentage' => $userGrowth,

            'active_users' => $activeUsers,
            'active_growth_percentage' => $activeGrowth,

            'pending_users' => $pendingUsers,
            'pending_growth_percentage' => $pendingGrowth,

            'suspended_users' => $suspendedUsers,
            'suspended_growth_percentage' => $suspendedGrowth,
        ]
    ]);
}
}