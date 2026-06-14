<?php

namespace App\Http\Controllers\Reviews;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReviewsController extends Controller
{
    /**
     * List all reviews (optional: filter by homeowner).
     */
    public function index(Request $request)
    {
        try {
            $filter = $request->query('filter');


            $reviews = Review::with(
                [
                    'inspectionAssign:id,inspection_booking_id,inspector_id',
                    'inspectionAssign.inspector:id,first_name,last_name',
                    'inspectionAssign.inspectionBooking:id',
                    'inspectionAssign.inspectionBooking.inspectionTypes:id,title',
                    'homeowner:id,first_name,last_name',
                ])
                ->select('id','homeowner_id','inspection_assign_id','rating','description','status','suspendInspector')
                ->latest();


            // Apply filters 'all','positive','negative','flagged'
            if ($filter === 'positive') {
                $reviews->where('rating', '>=', 3.5);
            } elseif ($filter === 'negative') {
                $reviews->where('rating', '<=', 2.5);
            } elseif ($filter === 'flagged') {
                $reviews->where('status', 'flagged');
            }
            // "all" means no extra condition

            $data = $reviews->get();


            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            Log::error('Review index failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch reviews'
            ], 500);
        }
    }

    public function toggleStatusAdmin($id)
    {
        try {
            $review = Review::findOrFail($id);

            // Toggle between active and flagged
            $review->status = $review->status === 'active' ? 'flagged' : 'active';
            $review->save();

            return response()->json([
                'success' => true,
                'data'    => $review,
                'message' => 'Review status toggled successfully'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Review toggle failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle review status'
            ], 500);
        }
    }

    public function suspendReviewInspector($id)
    {
        try {
            $review_id = $id;

            // Find the review
            $review = Review::with('inspectionAssign.inspector')->findOrFail($review_id);

            // Get the inspector linked to this review
            $inspector = $review->inspectionAssign->inspector;

            // Toggle suspendInspector flag
            $review->suspendInspector = $review->suspendInspector ? 1 : 0;
            $inspector->status = $inspector->status === 'active' ? 'suspended' : 'active';
            $review->save();

            return response()->json([
                'success'   => true,
                'review_id' => $review->id,
                'inspector' => [
                    'id'         => $inspector->id,
                    'first_name' => $inspector->first_name,
                    'last_name'  => $inspector->last_name,
                    'status'     => $inspector->status,

                ],
                'message'   => 'Inspector suspension status updated successfully'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Suspend inspector failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function reviewMatrics()
    {
        $avg_review = Review::avg('rating');
        $total_reviews = Review::count();
        $low_ratings = Review::where('rating', '<=',2.5)->count();
        $flagged_ratings = Review::where('status', 'flagged')->count();

       return response()->json([
           'avg_review' => round($avg_review,2),
           'total_reviews' => $total_reviews,
           'low_ratings' => $low_ratings,
           'flagged_ratings' => $flagged_ratings,
       ]);
    }
    /**
     * Store a new review (homeowner_id from Sanctum).
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'rating' => 'required|integer|min:1|max:5',
                'inspection_assign_id' => 'required|exists:inspection_assigns,id',
                'description' => 'nullable|string',
            ]);

            $validated['homeowner_id'] = Auth::id(); // Sanctum user ID

            $review = Review::create($validated);

            return response()->json([
                'success' => true,
                'data' => $review,
                'message' => 'Review created successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Review store failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create review'
            ], 500);
        }
    }

    /**
     * Show a single review.
     */
    public function show($id)
    {
        try {
            $review = Review::with(['inspectionAssign', 'inspectionAssign.inspector'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $review
            ], 200);

        } catch (\Exception $e) {
            Log::error('Review show failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Review not found'
            ], 404);
        }
    }

    /**
     * Update an existing review (only by the owner).
     */
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'rating' => 'sometimes|integer|min:1|max:5',
                'description' => 'nullable|string',
            ]);

            $review = Review::where('id', $id)
                ->where('homeowner_id', Auth::id()) // ensure ownership
                ->firstOrFail();

            $review->update($validated);

            return response()->json([
                'success' => true,
                'data' => $review,
                'message' => 'Review updated successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Review update failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update review'
            ], 500);
        }
    }

    /**
     * Delete a review (only by the owner).
     */
    public function destroy($id)
    {
        try {
            $review = Review::where('id', $id)
                ->where('homeowner_id', Auth::id())
                ->firstOrFail();

            $review->delete();

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Review delete failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review'
            ], 500);
        }
    }
}
