<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class InHouseAdminController extends Controller
{
    /**
     * 📋 Grouped Granular Permissions for In-House Admins
     */
    public const PERMISSION_GROUPS = [
        [
            'module'      => 'Dashboard Overview',
            'module_key'  => 'dashboard',
            'permissions' => [
                [
                    'key'         => 'dashboard.view',
                    'name'        => 'View Dashboard Overview',
                    'description' => 'Can view platform summary, booking trends, earnings and analytics'
                ]
            ]
        ],
        [
            'module'      => 'Inspections & Bookings',
            'module_key'  => 'inspections',
            'permissions' => [
                [
                    'key'         => 'inspections.view',
                    'name'        => 'View Inspections',
                    'description' => 'Can view all inspection bookings, details and status metrics'
                ],
                [
                    'key'         => 'inspections.assign',
                    'name'        => 'Assign Inspector',
                    'description' => 'Can assign inspectors to new or pending bookings'
                ],
                [
                    'key'         => 'inspections.complete',
                    'name'        => 'Mark Complete',
                    'description' => 'Can manually mark inspections as complete'
                ],
                [
                    'key'         => 'inspections.export',
                    'name'        => 'Export Data',
                    'description' => 'Can export inspection bookings data to Excel/CSV'
                ]
            ]
        ],
        [
            'module'      => 'Inspection Reports',
            'module_key'  => 'reports',
            'permissions' => [
                [
                    'key'         => 'reports.view',
                    'name'        => 'View & Download Reports',
                    'description' => 'Can view inspection reports and download PDF copies'
                ],
                [
                    'key'         => 'reports.manage',
                    'name'        => 'Archive & Manage Reports',
                    'description' => 'Can archive, restore, and favorite inspection reports'
                ]
            ]
        ],
        [
            'module'      => 'Client Management (Realtors & Insurers)',
            'module_key'  => 'clients',
            'permissions' => [
                [
                    'key'         => 'clients.view',
                    'name'        => 'View Clients',
                    'description' => 'Can view client lists, metrics, and profiles'
                ],
                [
                    'key'         => 'clients.create',
                    'name'        => 'Add New Client',
                    'description' => 'Can register new realtors, insurance companies, and agencies'
                ],
                [
                    'key'         => 'clients.edit',
                    'name'        => 'Edit Client',
                    'description' => 'Can update client company information and profile details'
                ],
                [
                    'key'         => 'clients.suspend',
                    'name'        => 'Suspend / Activate Client',
                    'description' => 'Can suspend or reactivate client accounts'
                ],
                [
                    'key'         => 'clients.link_bookings',
                    'name'        => 'Link Bookings',
                    'description' => 'Can associate existing inspection bookings with a client'
                ],
                [
                    'key'         => 'clients.delete',
                    'name'        => 'Delete Client',
                    'description' => 'Can permanently delete client accounts'
                ]
            ]
        ],
        [
            'module'      => 'Client Automated / Summary Reports',
            'module_key'  => 'client_reports',
            'permissions' => [
                [
                    'key'         => 'client_reports.generate',
                    'name'        => 'Generate Reports',
                    'description' => 'Can generate daily and weekly client inspection summary reports'
                ],
                [
                    'key'         => 'client_reports.download',
                    'name'        => 'Download PDF Reports',
                    'description' => 'Can download PDF versions of client inspection summary reports'
                ],
                [
                    'key'         => 'client_reports.email',
                    'name'        => 'Email Reports',
                    'description' => 'Can directly email inspection summary reports to clients'
                ]
            ]
        ],
        [
            'module'      => 'Inspectors Management',
            'module_key'  => 'inspectors',
            'permissions' => [
                [
                    'key'         => 'inspectors.view',
                    'name'        => 'View Inspectors',
                    'description' => 'Can view inspector lists, statistics, and profile details'
                ],
                [
                    'key'         => 'inspectors.approve_reject',
                    'name'        => 'Approve / Reject',
                    'description' => 'Can approve or reject inspector registrations'
                ],
                [
                    'key'         => 'inspectors.suspend',
                    'name'        => 'Suspend / Reactivate',
                    'description' => 'Can suspend or reactivate inspector accounts'
                ]
            ]
        ],
        [
            'module'      => 'Homeowners / Customers',
            'module_key'  => 'users',
            'permissions' => [
                [
                    'key'         => 'users.view',
                    'name'        => 'View Homeowners',
                    'description' => 'Can view customer accounts and registration statistics'
                ],
                [
                    'key'         => 'users.manage',
                    'name'        => 'Manage Customers',
                    'description' => 'Can suspend, unsuspend, or delete homeowner accounts'
                ]
            ]
        ],
        [
            'module'      => 'Help & Support',
            'module_key'  => 'support',
            'permissions' => [
                [
                    'key'         => 'support.view',
                    'name'        => 'View Support Tickets',
                    'description' => 'Can view customer support and inquiry requests'
                ],
                [
                    'key'         => 'support.reply',
                    'name'        => 'Reply to Support',
                    'description' => 'Can send responses and resolution messages to support tickets'
                ]
            ]
        ],
        [
            'module'      => 'Reviews & Ratings',
            'module_key'  => 'reviews',
            'permissions' => [
                [
                    'key'         => 'reviews.view',
                    'name'        => 'View Reviews',
                    'description' => 'Can view homeowner and inspector ratings & feedback'
                ],
                [
                    'key'         => 'reviews.toggle',
                    'name'        => 'Moderate Reviews',
                    'description' => 'Can toggle review visibility or suspend fraudulent reviews'
                ]
            ]
        ]
    ];

    /**
     * Get flat list of all valid permission keys
     */
    private static function getAllValidPermissionKeys(): array
    {
        $keys = [];
        foreach (self::PERMISSION_GROUPS as $group) {
            foreach ($group['permissions'] as $perm) {
                $keys[] = $perm['key'];
            }
        }
        return $keys;
    }

    /**
     * 🛡️ Verify Super Admin Access
     * Only full Admin (user_type === 'admin') can manage in-house admins.
     */
    private function verifySuperAdmin(Request $request)
    {
        $currentUser = $request->user();
        if (!$currentUser || $currentUser->user_type !== 'admin') {
            abort(response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can manage in-house admins.'
            ], 403));
        }
    }

    /**
     * 🔑 1. Available Permissions List (Grouped with Sub-Permissions for Checkboxes on Frontend)
     */
    public function availablePermissions(Request $request)
    {
        $this->verifySuperAdmin($request);

        return response()->json([
            'success' => true,
            'message' => 'Available permissions list retrieved successfully.',
            'data'    => self::PERMISSION_GROUPS
        ], 200);
    }

    /**
     * 📊 2. In-House Admins Stats
     */
    public function stats(Request $request)
    {
        $this->verifySuperAdmin($request);

        $totalAdmins = User::where('user_type', 'inhouse_admin')->count();
        $activeAdmins = User::where('user_type', 'inhouse_admin')->where('status', 'active')->count();
        $suspendedAdmins = User::where('user_type', 'inhouse_admin')->where('status', 'suspended')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_inhouse_admins' => $totalAdmins,
                'active_admins'        => $activeAdmins,
                'suspended_admins'     => $suspendedAdmins,
            ]
        ], 200);
    }

    /**
     * 📋 3. List In-House Admins
     */
    public function index(Request $request)
    {
        $this->verifySuperAdmin($request);

        $query = User::where('user_type', 'inhouse_admin')->with('profile');

        // Status Filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search Filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $admins = $query->latest()->paginate($request->get('per_page', 10));

        $admins->getCollection()->transform(function ($admin) {
            return [
                'id'          => $admin->id,
                'first_name'  => $admin->first_name,
                'last_name'   => $admin->last_name,
                'email'       => $admin->email,
                'phone'       => $admin->profile?->phone,
                'address'     => $admin->profile?->address,
                'user_type'   => $admin->user_type,
                'permissions' => $admin->permissions ?? [],
                'status'      => $admin->status,
                'image'       => $admin->profile?->profile_img
                    ? asset('storage/' . $admin->profile->profile_img)
                    : null,
                'created_at'  => $admin->created_at?->format('Y-m-d H:i:s'),
                'updated_at'  => $admin->updated_at?->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $admins
        ], 200);
    }

    /**
     * ➕ 4. Create New In-House Admin with Selected Granular Permissions
     */
    public function store(Request $request)
    {
        $this->verifySuperAdmin($request);

        $validKeys = self::getAllValidPermissionKeys();

        $request->validate([
            'first_name'    => 'required|string|max:255',
            'last_name'     => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'password'      => ['required', 'string', Password::min(8)],
            'phone'         => 'nullable|string|max:50',
            'address'       => 'nullable|string|max:255',
            'profile_img'   => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string|in:' . implode(',', $validKeys),
        ]);

        DB::beginTransaction();
        try {
            $permissions = $request->input('permissions', []);

            // 1. Create User
            $admin = User::create([
                'first_name'        => $request->first_name,
                'last_name'         => $request->last_name,
                'email'             => $request->email,
                'password'          => Hash::make($request->password),
                'user_type'         => 'inhouse_admin',
                'permissions'       => $permissions,
                'status'            => 'active',
                'email_verified_at' => now(),
            ]);

            // 2. Profile Image
            $imagePath = null;
            if ($request->hasFile('profile_img')) {
                $imagePath = $request->file('profile_img')->store('profiles', 'public');
            }

            // 3. Create Profile
            Profile::create([
                'user_id'     => $admin->id,
                'phone'       => $request->phone,
                'address'     => $request->address,
                'profile_img' => $imagePath,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'In-house admin created successfully with assigned permissions.',
                'data'    => [
                    'id'          => $admin->id,
                    'first_name'  => $admin->first_name,
                    'last_name'   => $admin->last_name,
                    'email'       => $admin->email,
                    'phone'       => $request->phone,
                    'address'     => $request->address,
                    'user_type'   => $admin->user_type,
                    'permissions' => $admin->permissions ?? [],
                    'status'      => $admin->status,
                    'image'       => $imagePath ? asset('storage/' . $imagePath) : null,
                    'created_at'  => $admin->created_at?->format('Y-m-d H:i:s'),
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create in-house admin: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 👁️ 5. Show Specific In-House Admin Details
     */
    public function show(Request $request, $id)
    {
        $this->verifySuperAdmin($request);

        $admin = User::with('profile')
            ->where('user_type', 'inhouse_admin')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'          => $admin->id,
                'first_name'  => $admin->first_name,
                'last_name'   => $admin->last_name,
                'email'       => $admin->email,
                'phone'       => $admin->profile?->phone,
                'address'     => $admin->profile?->address,
                'user_type'   => $admin->user_type,
                'permissions' => $admin->permissions ?? [],
                'status'      => $admin->status,
                'image'       => $admin->profile?->profile_img
                    ? asset('storage/' . $admin->profile->profile_img)
                    : null,
                'created_at'  => $admin->created_at?->format('Y-m-d H:i:s'),
                'updated_at'  => $admin->updated_at?->format('Y-m-d H:i:s'),
            ]
        ], 200);
    }

    /**
     * ✏️ 6. Update In-House Admin & Permissions
     */
    public function update(Request $request, $id)
    {
        $this->verifySuperAdmin($request);

        $admin = User::where('user_type', 'inhouse_admin')->findOrFail($id);

        $validKeys = self::getAllValidPermissionKeys();

        $request->validate([
            'first_name'    => 'required|string|max:255',
            'last_name'     => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email,' . $admin->id,
            'password'      => ['nullable', 'string', Password::min(8)],
            'phone'         => 'nullable|string|max:50',
            'address'       => 'nullable|string|max:255',
            'profile_img'   => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string|in:' . implode(',', $validKeys),
        ]);

        DB::beginTransaction();
        try {
            $adminData = [
                'first_name' => $request->first_name,
                'last_name'  => $request->last_name,
                'email'      => $request->email,
            ];

            if ($request->has('permissions')) {
                $adminData['permissions'] = $request->permissions;
            }

            if ($request->filled('password')) {
                $adminData['password'] = Hash::make($request->password);
            }

            $admin->update($adminData);

            $profile = Profile::firstOrCreate(['user_id' => $admin->id]);

            if ($request->hasFile('profile_img')) {
                if ($profile->profile_img && Storage::disk('public')->exists($profile->profile_img)) {
                    Storage::disk('public')->delete($profile->profile_img);
                }
                $profile->profile_img = $request->file('profile_img')->store('profiles', 'public');
            }

            if ($request->has('phone')) {
                $profile->phone = $request->phone;
            }
            if ($request->has('address')) {
                $profile->address = $request->address;
            }
            $profile->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'In-house admin updated successfully.',
                'data'    => [
                    'id'          => $admin->id,
                    'first_name'  => $admin->first_name,
                    'last_name'   => $admin->last_name,
                    'email'       => $admin->email,
                    'phone'       => $profile->phone,
                    'address'     => $profile->address,
                    'user_type'   => $admin->user_type,
                    'permissions' => $admin->permissions ?? [],
                    'status'      => $admin->status,
                    'image'       => $profile->profile_img ? asset('storage/' . $profile->profile_img) : null,
                    'created_at'  => $admin->created_at?->format('Y-m-d H:i:s'),
                    'updated_at'  => $admin->updated_at?->format('Y-m-d H:i:s'),
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update admin: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ⛔ 7. Suspend In-House Admin
     */
    public function suspend(Request $request, $id)
    {
        $this->verifySuperAdmin($request);

        $admin = User::where('user_type', 'inhouse_admin')->findOrFail($id);
        $admin->update(['status' => 'suspended']);

        // Revoke active auth tokens
        $admin->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'In-house admin suspended successfully.'
        ], 200);
    }

    /**
     * 🟢 8. Unsuspend / Activate In-House Admin
     */
    public function unsuspend(Request $request, $id)
    {
        $this->verifySuperAdmin($request);

        $admin = User::where('user_type', 'inhouse_admin')->findOrFail($id);
        $admin->update(['status' => 'active']);

        return response()->json([
            'success' => true,
            'message' => 'In-house admin activated successfully.'
        ], 200);
    }

    /**
     * 🗑️ 9. Delete In-House Admin
     */
    public function destroy(Request $request, $id)
    {
        $this->verifySuperAdmin($request);

        $admin = User::with('profile')->findOrFail($id);

        // Security check: Only inhouse_admin accounts can be deleted here
        if ($admin->user_type !== 'inhouse_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Action forbidden. Only in-house admin accounts can be deleted here.'
            ], 403);
        }

        if ($admin->profile && $admin->profile->profile_img) {
            Storage::disk('public')->delete($admin->profile->profile_img);
        }

        $admin->profile?->delete();
        $admin->tokens()->delete();
        $admin->delete();

        return response()->json([
            'success' => true,
            'message' => 'In-house admin deleted successfully.'
        ], 200);
    }

    /**
     * 🎯 10. Assign / Update Permissions Directly for an In-House Admin
     */
    public function assignPermissions(Request $request, $id)
    {
        $this->verifySuperAdmin($request);

        $admin = User::where('user_type', 'inhouse_admin')->findOrFail($id);

        $validKeys = self::getAllValidPermissionKeys();

        $request->validate([
            'permissions'   => 'required|array',
            'permissions.*' => 'string|in:' . implode(',', $validKeys),
        ]);

        $admin->update([
            'permissions' => $request->permissions,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permissions successfully assigned to in-house admin.',
            'data'    => [
                'id'          => $admin->id,
                'first_name'  => $admin->first_name,
                'last_name'   => $admin->last_name,
                'email'       => $admin->email,
                'user_type'   => $admin->user_type,
                'permissions' => $admin->permissions ?? [],
            ]
        ], 200);
    }
}
