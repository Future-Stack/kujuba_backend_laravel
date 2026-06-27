<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\InspectionAssign;
use Illuminate\Http\Request;
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
            'cancelled_inspections' => $user->inspectionBookings()->where('status', 'cancelled')->count(),
            'completed_inspections' => $user->inspectionBookings()->where('status', 'completed')->count(),

            // DATES (NEW)
            'created_at' => $user->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $user->updated_at?->format('Y-m-d H:i:s'),
        ]
    ]);
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