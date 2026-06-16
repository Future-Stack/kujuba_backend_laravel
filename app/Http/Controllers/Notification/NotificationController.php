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
                'send_to' => 'required|string', // e.g. 'all_users', 'all_inspectors', 'all_homeowners',
            ]);

            $sender = Auth::user();

            // Determine recipients
            $recipients = match ($validated['send_to']) {
                'all_users' => User::all(),
                'all_inspectors' => User::where('user_type', 'inspector')->get(),
                'all_homeowners' => User::where('user_type', 'homeowner')->get(),
                default => User::where('id', str_replace('user_', '', $validated['send_to']))->get(),
            };

            // Send notification to group or single user
            Notification::send($recipients, new PlatformNotification([
                'type'      => $validated['type'],
                'title'     => $validated['title'],
                'message'   => $validated['message'],
                'sender_id' => $sender->id,
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

            // Fetch notifications sent by the current admin
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
                        'status'      => 'delivered',
                    ];
                });

            return response()->json([
                'success' => true,
                'total'   => $notifications->count(),
                'data'    => $notifications,
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
}
