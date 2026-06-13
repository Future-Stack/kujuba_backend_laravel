<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Mail\SupportReplyMail;
use App\Models\SupportRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SupportRequestController extends Controller
{
    /**
     * User Create Support Request
     */
    public function store(Request $request)
    {
        try {

            $request->validate([
                'title' => 'required|string|max:255',
                'explanation' => 'required|string',
            ]);

            $support = SupportRequest::create([
                'user_id' => auth()->id(),
                'title' => $request->title,
                'explanation' => $request->explanation,
                'status' => 'open',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Support request submitted successfully.',
                'data' => $support
            ], 201);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);

        }
    }

    /**
     * Admin - All Support Requests
     */
    public function index()
{
    try {

        $supports = SupportRequest::with(['user.profile'])
            ->latest()
            ->get()
            ->map(function ($item) {

                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'explanation' => $item->explanation,
                    'status' => $item->status,
                    'reply' => $item->reply,

                    'user' => [
                        'id' => $item->user->id,
                        'name' => trim(
                            $item->user->first_name . ' ' . ($item->user->last_name ?? '')
                        ),
                        'email' => $item->user->email,
                        'user_type' => $item->user->user_type,

                        // FULL IMAGE URL FIXED
                        'image' => $item->user->profile && $item->user->profile->profile_img
                            ? asset('storage/' . $item->user->profile->profile_img)
                            : null,

                        'phone' => $item->user->profile->phone ?? null,
                    ],

                    'created_at' => $item->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $supports
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Admin - Single Support Request
     */
    public function show($id)
{
    try {

        $support = SupportRequest::with(['user.profile'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $support->id,
                'title' => $support->title,
                'explanation' => $support->explanation,
                'status' => $support->status,
                'reply' => $support->reply,

                'user' => [
                    'id' => $support->user->id,
                    'name' => trim(
                        $support->user->first_name . ' ' . ($support->user->last_name ?? '')
                    ),
                    'email' => $support->user->email,
                    'user_type' => $support->user->user_type,

                    // FULL IMAGE URL FIXED
                    'image' => $support->user->profile && $support->user->profile->profile_img
                        ? asset('storage/' . $support->user->profile->profile_img)
                        : null,

                    'phone' => $support->user->profile->phone ?? null,
                ],

                'created_at' => $support->created_at,
            ]
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Admin Reply
     */
    public function reply(Request $request, $id)
    {
        try {

            $request->validate([
                'reply' => 'required|string'
            ]);

            $support = SupportRequest::with('user')
                ->findOrFail($id);

            $support->update([
                'reply' => $request->reply,
                'status' => 'closed',
            ]);

            Mail::to($support->user->email)
                ->queue(new SupportReplyMail($support));

            return response()->json([
                'success' => true,
                'message' => 'Reply sent successfully.',
                'data' => $support
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);

        }
    }

    /**
     * Delete Support Request
     */
    public function destroy($id)
    {
        try {

            $support = SupportRequest::findOrFail($id);

            $support->delete();

            return response()->json([
                'success' => true,
                'message' => 'Support request deleted successfully.'
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);

        }
    }
}