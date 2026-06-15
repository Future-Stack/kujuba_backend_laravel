<?php

namespace App\Http\Controllers\Reviews;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class HomeownerReviewsController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'overall_rating' => 'required|integer|min:1|max:5',
                'service_rating' => 'required|integer|min:1|max:5',
                'timeliness_rating' => 'required|integer|min:1|max:5',
                'value_rating' => 'required|integer|min:1|max:5',
                'inspection_assign_id' => 'required|exists:inspection_assigns,id',
                'description' => 'nullable|string',
            ]);

            $rating = (
                    $validated['overall_rating']
                    + $validated['service_rating']
                    + $validated['timeliness_rating']
                    + $validated['value_rating']
                ) / 4;

            $data = [
                'rating'              => $rating,
                'inspection_assign_id' => $validated['inspection_assign_id'],
                'description'          => $validated['description'] ?? null,
                'homeowner_id'         => Auth::id(), // Sanctum user ID
            ];
            // Sanctum user ID
            $hasReview = Review::find($validated['inspection_assign_id']);


            $review = Review::create($data);

            return response()->json([
                'success' => true,
                'data' => $review,
                'message' => 'Review created successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Review store failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
