<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Profile;
use App\Models\InspectionBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ClientManagementController extends Controller
{
    /**
     * 📊 CLIENT STATS & METRICS
     */
    public function stats(Request $request)
    {
        $now = Carbon::now();
        $lastMonth = Carbon::now()->subMonth();

        $baseQuery = User::where('user_type', 'client');

        // Total Clients
        $totalClients = (clone $baseQuery)->count();
        $currentMonthClients = (clone $baseQuery)
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();
        $lastMonthClients = (clone $baseQuery)
            ->whereMonth('created_at', $lastMonth->month)
            ->whereYear('created_at', $lastMonth->year)
            ->count();
        $clientGrowth = $this->calcGrowth($lastMonthClients, $currentMonthClients);

        // Active Clients
        $activeClients = (clone $baseQuery)->where('status', 'active')->count();
        $currentMonthActive = (clone $baseQuery)
            ->where('status', 'active')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();
        $lastMonthActive = (clone $baseQuery)
            ->where('status', 'active')
            ->whereMonth('created_at', $lastMonth->month)
            ->whereYear('created_at', $lastMonth->year)
            ->count();
        $activeGrowth = $this->calcGrowth($lastMonthActive, $currentMonthActive);

        // Pending Clients
        $pendingClients = (clone $baseQuery)->where('status', 'pending')->count();
        $currentMonthPending = (clone $baseQuery)
            ->where('status', 'pending')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();
        $lastMonthPending = (clone $baseQuery)
            ->where('status', 'pending')
            ->whereMonth('created_at', $lastMonth->month)
            ->whereYear('created_at', $lastMonth->year)
            ->count();
        $pendingGrowth = $this->calcGrowth($lastMonthPending, $currentMonthPending);

        // Suspended Clients
        $suspendedClients = (clone $baseQuery)->where('status', 'suspended')->count();
        $currentMonthSuspended = (clone $baseQuery)
            ->where('status', 'suspended')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();
        $lastMonthSuspended = (clone $baseQuery)
            ->where('status', 'suspended')
            ->whereMonth('created_at', $lastMonth->month)
            ->whereYear('created_at', $lastMonth->year)
            ->count();
        $suspendedGrowth = $this->calcGrowth($lastMonthSuspended, $currentMonthSuspended);

        // Breakdown by Client Type (e.g. Insurance Company, Realtor, Broker, Agency)
        $typeBreakdown = Profile::whereHas('user', function ($q) {
                $q->where('user_type', 'client');
            })
            ->select('client_type', DB::raw('count(*) as total'))
            ->groupBy('client_type')
            ->get()
            ->map(function ($item) {
                return [
                    'client_type' => $item->client_type ?: 'Other / Unspecified',
                    'total' => $item->total,
                ];
            });

        // Total Inspections Under All Clients
        $totalClientInspections = InspectionBooking::whereNotNull('client_id')->count();
        $completedClientInspections = InspectionBooking::whereNotNull('client_id')->where('status', 'completed')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_clients' => $totalClients,
                'client_growth_percentage' => $clientGrowth,

                'active_clients' => $activeClients,
                'active_growth_percentage' => $activeGrowth,

                'pending_clients' => $pendingClients,
                'pending_growth_percentage' => $pendingGrowth,

                'suspended_clients' => $suspendedClients,
                'suspended_growth_percentage' => $suspendedGrowth,

                'total_client_inspections' => $totalClientInspections,
                'completed_client_inspections' => $completedClientInspections,

                'client_type_breakdown' => $typeBreakdown,
            ]
        ]);
    }

    /**
     * 📋 CLIENT LIST WITH SEARCH, FILTERS & STATS
     */
    public function index(Request $request)
    {
        $query = User::where('user_type', 'client')->with('profile');

        // Status Filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Client Type Filter
        if ($request->filled('client_type')) {
            $clientType = $request->client_type;
            $query->whereHas('profile', function ($q) use ($clientType) {
                $q->where('client_type', $clientType);
            });
        }

        // Search by Name, Email, Company Name, Phone
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                  ->orWhere('last_name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhereHas('profile', function ($p) use ($search) {
                      $p->where('company_name', 'like', "%$search%")
                        ->orWhere('phone', 'like', "%$search%")
                        ->orWhere('client_type', 'like', "%$search%");
                  });
            });
        }

        // Count linked inspection bookings
        $query->withCount([
            'clientBookings as total_inspections',
            'clientBookings as completed_inspections' => function ($q) {
                $q->where('status', 'completed');
            },
            'clientBookings as pending_inspections' => function ($q) {
                $q->where('status', 'pending');
            },
            'clientBookings as in_progress_inspections' => function ($q) {
                $q->whereIn('status', ['assigned', 'started', 'reports']);
            },
            'clientBookings as cancelled_inspections' => function ($q) {
                $q->where('status', 'cancelled');
            },
        ]);

        $perPage = $request->get('per_page', 10);
        $clients = $query->latest()->paginate($perPage);

        $clients->getCollection()->transform(function ($client) {
            return $this->formatClient($client);
        });

        return response()->json([
            'success' => true,
            'data' => $clients
        ]);
    }

    /**
     * 👁 CLIENT DETAILS + RECENT INSPECTIONS
     */
    public function show($id)
    {
        $client = User::where('user_type', 'client')
            ->with(['profile', 'clientBookings.homeowner.profile', 'clientBookings.inspectionTypes', 'clientBookings.assignment.inspector'])
            ->withCount([
                'clientBookings as total_inspections',
                'clientBookings as completed_inspections' => function ($q) {
                    $q->where('status', 'completed');
                },
                'clientBookings as pending_inspections' => function ($q) {
                    $q->where('status', 'pending');
                },
                'clientBookings as in_progress_inspections' => function ($q) {
                    $q->whereIn('status', ['assigned', 'started', 'reports']);
                },
                'clientBookings as cancelled_inspections' => function ($q) {
                    $q->where('status', 'cancelled');
                },
            ])
            ->findOrFail($id);

        $recentBookings = $client->clientBookings()
            ->with(['homeowner.profile', 'inspectionTypes', 'assignment.inspector'])
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'property_address' => $booking->property_address,
                    'property_type' => $booking->property_type,
                    'status' => ucfirst($booking->status),
                    'scheduled_date' => $booking->scheduled_date ? $booking->scheduled_date->format('Y-m-d') : null,
                    'scheduled_time' => $booking->scheduled_time,
                    'homeowner' => [
                        'id' => $booking->homeowner?->id,
                        'name' => trim(($booking->homeowner?->first_name ?? '') . ' ' . ($booking->homeowner?->last_name ?? '')),
                        'email' => $booking->homeowner?->email,
                        'phone' => $booking->homeowner?->profile?->phone,
                    ],
                    'inspector' => [
                        'name' => trim(($booking->assignment?->inspector?->first_name ?? '') . ' ' . ($booking->assignment?->inspector?->last_name ?? '')),
                        'email' => $booking->assignment?->inspector?->email,
                    ],
                    'inspection_types' => $booking->inspectionTypes->map(fn($t) => $t->title),
                ];
            });

        $formatted = $this->formatClient($client);
        $formatted['recent_inspections'] = $recentBookings;

        return response()->json([
            'success' => true,
            'data' => $formatted
        ]);
    }

    /**
     * ➕ CREATE CLIENT
     */
    public function store(Request $request)
    {
        $request->validate([
            'first_name'   => 'required|string|max:255',
            'last_name'    => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|string|min:6',
            'company_name' => 'nullable|string|max:255',
            'client_type'  => 'nullable|string|in:insurance_company,realtor,broker,agency,other',
            'phone'        => 'nullable|string|max:25',
            'address'      => 'nullable|string|max:255',
            'profile_img'  => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'first_name'        => $request->first_name,
                'last_name'         => $request->last_name,
                'email'             => $request->email,
                'password'          => Hash::make($request->password),
                'user_type'         => 'client',
                'status'            => 'active',
                'email_verified_at' => now(),
            ]);

            $imagePath = null;
            if ($request->hasFile('profile_img')) {
                $imagePath = $request->file('profile_img')->store('profiles', 'public');
            }

            $profile = Profile::create([
                'user_id'      => $user->id,
                'company_name' => $request->company_name,
                'client_type'  => $request->client_type ?: 'insurance_company',
                'phone'        => $request->phone,
                'address'      => $request->address,
                'profile_img'  => $imagePath,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Client account created successfully.',
                'data'    => $this->formatClient($user->load('profile'))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create client: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✏️ UPDATE CLIENT
     */
    public function update(Request $request, $id)
    {
        $user = User::where('user_type', 'client')->with('profile')->findOrFail($id);

        $request->validate([
            'first_name'   => 'sometimes|required|string|max:255',
            'last_name'    => 'sometimes|required|string|max:255',
            'email'        => 'sometimes|required|email|unique:users,email,' . $user->id,
            'password'     => 'nullable|string|min:6',
            'company_name' => 'nullable|string|max:255',
            'client_type'  => 'nullable|string|in:insurance_company,realtor,broker,agency,other',
            'phone'        => 'nullable|string|max:25',
            'address'      => 'nullable|string|max:255',
            'profile_img'  => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        DB::beginTransaction();
        try {
            $userData = [
                'first_name' => $request->get('first_name', $user->first_name),
                'last_name'  => $request->get('last_name', $user->last_name),
                'email'      => $request->get('email', $user->email),
            ];

            if ($request->filled('password')) {
                $userData['password'] = Hash::make($request->password);
            }

            $user->update($userData);

            $profile = $user->profile ?: new Profile(['user_id' => $user->id]);

            if ($request->hasFile('profile_img')) {
                if ($profile->profile_img) {
                    Storage::disk('public')->delete($profile->profile_img);
                }
                $profile->profile_img = $request->file('profile_img')->store('profiles', 'public');
            }

            if ($request->has('company_name')) {
                $profile->company_name = $request->company_name;
            }
            if ($request->has('client_type')) {
                $profile->client_type = $request->client_type;
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
                'message' => 'Client updated successfully.',
                'data'    => $this->formatClient($user->fresh(['profile']))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update client: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ⏸️ SUSPEND CLIENT
     */
    public function suspend($id)
    {
        $user = User::where('user_type', 'client')->findOrFail($id);
        $user->update(['status' => 'suspended']);

        return response()->json([
            'success' => true,
            'message' => 'Client suspended successfully'
        ]);
    }

    /**
     * 🟢 UNSUSPEND CLIENT
     */
    public function unsuspend($id)
    {
        $user = User::where('user_type', 'client')->findOrFail($id);
        $user->update(['status' => 'active']);

        return response()->json([
            'success' => true,
            'message' => 'Client activated successfully'
        ]);
    }

    /**
     * 🗑️ DELETE CLIENT
     */
    public function destroy($id)
    {
        $user = User::where('user_type', 'client')->with('profile')->findOrFail($id);

        if ($user->profile && $user->profile->profile_img) {
            Storage::disk('public')->delete($user->profile->profile_img);
        }
        if ($user->profile) {
            $user->profile->delete();
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Client deleted successfully'
        ]);
    }

    /**
     * 🔗 LINK BOOKING(S) TO CLIENT
     */
    public function linkBookings(Request $request, $id)
    {
        $client = User::where('user_type', 'client')->findOrFail($id);

        $request->validate([
            'booking_ids'   => 'required|array',
            'booking_ids.*' => 'integer|exists:inspection_bookings,id',
        ]);

        InspectionBooking::whereIn('id', $request->booking_ids)->update([
            'client_id' => $client->id
        ]);

        return response()->json([
            'success' => true,
            'message' => count($request->booking_ids) . ' inspection booking(s) linked to ' . ($client->profile?->company_name ?: $client->first_name),
        ]);
    }

    /**
     * Helper: Format client model for JSON output
     */
    private function formatClient($user)
    {
        return [
            'id'           => $user->id,
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'full_name'    => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            'email'        => $user->email,
            'company_name' => $user->profile?->company_name,
            'client_type'  => $user->profile?->client_type,
            'phone'        => $user->profile?->phone,
            'address'      => $user->profile?->address,
            'image'        => $user->profile?->profile_img ? asset('storage/' . $user->profile->profile_img) : null,
            'status'       => $user->status,
            'user_type'    => $user->user_type,

            // Stats
            'total_inspections'       => $user->total_inspections ?? $user->clientBookings()->count(),
            'completed_inspections'   => $user->completed_inspections ?? $user->clientBookings()->where('status', 'completed')->count(),
            'pending_inspections'     => $user->pending_inspections ?? $user->clientBookings()->where('status', 'pending')->count(),
            'in_progress_inspections' => $user->in_progress_inspections ?? $user->clientBookings()->whereIn('status', ['assigned', 'started', 'reports'])->count(),
            'cancelled_inspections'   => $user->cancelled_inspections ?? $user->clientBookings()->where('status', 'cancelled')->count(),

            'created_at' => $user->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $user->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Helper: Growth Calculator
     */
    private function calcGrowth($last, $current)
    {
        return $last > 0
            ? round((($current - $last) / $last) * 100, 2)
            : ($current > 0 ? 100 : 0);
    }
}
