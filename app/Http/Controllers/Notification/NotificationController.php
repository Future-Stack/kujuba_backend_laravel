<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class NotificationController extends Controller
{
    public function store(Request $request)
    {
        try {
            // Validate input
            $validated = $request->validate([
                'type'    => 'required|string|in:announcement,approval,alert,cancellation,update',
                'title'   => 'required|string|max:255',
                'message' => 'required|string',
                'send_to' => 'required|string', // e.g. 'all_users', 'all_inspectors', 'all_homeowners','all_clients','custom'
                'user_ids'    => 'required_if:send_to,custom|array',
                'user_ids.*'  => 'exists:users,id',
            ]);

            $sender = Auth::user();

            // Determine recipients
            $recipients = match ($validated['send_to']) {
                'all_users' => User::all(),
                'all_inspectors' => User::where('user_type', 'inspector')->get(),
                'all_homeowners' => User::where('user_type', 'homeowner')->get(),
                'all_clients' => User::where('user_type', 'client')->get(),
                'custom'         => User::whereIn('id', $validated['user_ids'])->get(),
                default => User::where('id', str_replace('user_', '', $validated['send_to']))->get(),
            };

            // Send notification to group or single user
            Notification::send($recipients, new PlatformNotification([
                'type'      => $validated['type'],
                'title'     => $validated['title'],
                'message'   => $validated['message'],
                'sender_id' => $sender->id,
                'sent_to_label' => $validated['send_to'],
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Notification sent successfully.',
                'recipients_count' => $recipients->count(),
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Notification send failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification.',
            ], 500);
        }
    }

    public function fetchUserNotification(Request $request)
    {
        try {
            $user = Auth::user();

            // Fetch notifications of the current user
            $unreadCount = $user->unreadNotifications()->count();

            $notifications = $user->notifications()
                ->latest()
                ->get()
                ->map(function ($notification) {
                    $data = $notification->data;
                    return [
                        'id'          => $notification->id,
                        'title'       => $data['title'] ?? '',
                        'message'     => $data['message'] ?? '',
                        'type'        => ucfirst($data['type'] ?? 'Announcement'),
                        'recipients'  => $data['recipients_count'] ?? 1,
                        'sent_to'     => $data['sent_to_label'] ?? 'All Users',
                        'sent_at'     => $notification->created_at->format('Y-m-d h:i A'),
                        'read_at'     => $notification->read_at ? $notification->read_at->format('Y-m-d h:i A') : null,
                        'is_read'     => !is_null($notification->read_at),
                        'status'      => 'delivered',
                    ];
                });

            return response()->json([
                'success'      => true,
                'total'        => $notifications->count(),
                'unread_count' => $unreadCount,
                'data'         => $notifications,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Notification fetch failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notifications.'
            ], 500);
        }
    }

    public function fetchAllNotification(Request $request)
    {
        try {
            // Group notifications by title, message, and type
            $notifications = DB::table('notifications')
                ->select(
                    DB::raw('MIN(id) as id'),
                    DB::raw('MAX(created_at) as sent_at'),
                    DB::raw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.title")) as title'),
                    DB::raw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.message")) as message'),
                    DB::raw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.type")) as type'),
                    DB::raw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.sent_to_label")) as sent_to'),
                    DB::raw('COUNT(*) as recipients')
                )
                ->groupByRaw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.title")),
                  JSON_UNQUOTE(JSON_EXTRACT(data, "$.message")),
                  JSON_UNQUOTE(JSON_EXTRACT(data, "$.type")),
                  JSON_UNQUOTE(JSON_EXTRACT(data, "$.sent_to_label"))')
                ->orderBy('sent_at', 'desc')
                ->get()
                ->map(function ($n) {
                    return [
                        'id'          => $n->id,
                        'title'       => $n->title,
                        'message'     => $n->message,
                        'type'        => ucfirst($n->type),
                        'recipients'  => $n->recipients,
                        'sent_to'     => $n->sent_to ?? 'All Users',
                        'sent_at'     => date('Y-m-d h:i A', strtotime($n->sent_at)),
                        'status'      => 'delivered',
                    ];
                });

            return response()->json([
                'success' => true,
                'total'   => $notifications->count(),
                'data'    => $notifications,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Notification grouping failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function fetchAdminNotification(Request $request)
    {
        try {
            $user = Auth::user();

            $notifications = $user->notifications()
                ->where('type', 'App\Notifications\AdminIconNotification')
                ->latest()
                ->get()
                ->map(function ($notification) {
                    $data = $notification->data;
                    return [
                        'id'          => $notification->id,
                        'title'       => $data['title'] ?? '',
                        'message'     => $data['message'] ?? '',
                        'type'        => ucfirst($data['type'] ?? 'Announcement'),
                        'sent_at'     => $notification->created_at->format('Y-m-d h:i A'),
                        'read_at'     => $notification->read_at ? $notification->read_at->format('Y-m-d h:i A') : null,
                        'is_read'     => !is_null($notification->read_at),
                        'status'      => 'delivered',
                    ];
                });

            $unreadCount = $user->unreadNotifications()
                ->where('type', 'App\Notifications\AdminIconNotification')
                ->count();

            return response()->json([
                'success'      => true,
                'total'        => $notifications->count(),
                'unread_count' => $unreadCount,
                'data'         => $notifications,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Notification fetch failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function markAsRead(Request $request, $id)
    {
        try {
            $user = Auth::user();

            $notification = $user->notifications()->whereKey($id)->firstOrFail();

            if (is_null($notification->read_at)) {
                $notification->markAsRead();
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read.',
                'is_read' => true,
                'read_at' => $notification->fresh()->read_at->format('Y-m-d h:i A'),
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Notification mark as read failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read.'
            ], 500);
        }
    }

    public function markAsUnread(Request $request, $id)
    {
        try {
            $user = Auth::user();

            $notification = $user->notifications()->whereKey($id)->firstOrFail();

            if (!is_null($notification->read_at)) {
                $notification->markAsUnread();
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as unread.',
                'is_read' => false,
                'read_at' => null,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Notification mark as unread failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as unread.'
            ], 500);
        }
    }

    public function markAllAdminAsRead(Request $request)
    {
        try {
            $user = Auth::user();

            $updated = $user->notifications()
                ->where('type', 'App\Notifications\AdminIconNotification')
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'All admin notifications marked as read.',
                'updated' => $updated,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Notification mark all admin as read failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all admin notifications as read.'
            ], 500);
        }
    }

    public function adminUnreadNotificationCount(Request $request)
    {
        try {
            $user = Auth::user();

            $unreadCount = $user->unreadNotifications()
                ->where('type', 'App\Notifications\AdminIconNotification')
                ->count();

            return response()->json([
                'success'      => true,
                'unread_count' => $unreadCount,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Admin unread notification count failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve unread notification count.'
            ], 500);
        }
    }

    public function markAllAsRead(Request $request)
    {
        try {
            $user = Auth::user();

            $updated = $user->unreadNotifications()->update(['read_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read.',
                'updated' => $updated,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Notification mark all as read failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read.'
            ], 500);
        }
    }

    public function unreadNotificationCount(Request $request)
    {
        try {
            $user = Auth::user();

            return response()->json([
                'success'      => true,
                'unread_count' => $user->unreadNotifications()->count(),
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Unread notification count failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve unread notification count.'
            ], 500);
        }
    }


}
