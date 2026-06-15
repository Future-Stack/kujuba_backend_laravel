<?php

namespace App\Http\Controllers\Reviews;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InspectorReviewsController extends Controller
{
    // GET /api/reviews?filter=all|1|2|3|4|5&page=1
    public function index(Request $request)
    {
        try {

            $filter = $request->query('filter', 'all');
            $perPage = (int)$request->query('per_page', 10);

            $query = Review::with([
                'inspectionAssign:id,inspection_booking_id,inspector_id',
                'inspectionAssign.inspector:id,first_name,last_name',
                'inspectionAssign.inspectionBooking:id,property_address,property_type',
                'inspectionAssign.inspectionBooking.inspectionTypes:id,title,price',
            ])
                ->select('id', 'homeowner_id', 'inspection_assign_id', 'rating', 'description', 'status', 'created_at')
                ->whereHas('inspectionAssign', function ($q) {
                    $q->where('inspector_id', Auth::id());
                })
                ->latest();

            if ($filter !== 'all' && is_numeric($filter)) {
                $query->whereBetween('rating', [(int)$filter, (int)$filter + 0.99]);
            }

            $paginated = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $paginated->items(),
                'links' => [
                    'first' => $paginated->url(1),
                    'last' => $paginated->url($paginated->lastPage()),
                    'prev' => $paginated->previousPageUrl(),
                    'next' => $paginated->nextPageUrl(),
                ],
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Reviews index failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch reviews'], 500);
        }
    }

    // GET /api/reviews/summary
    public function reviewMatrics()
    {
        try {
            $inspector_id = Auth::id();

            $data = Review::with([
                'inspectionAssign:id,inspection_booking_id,inspector_id',
                'inspectionAssign.inspector:id,first_name,last_name',
                ])
                ->whereHas('inspectionAssign', function ($q) {
                    $q->where('inspector_id', Auth::id());
                });

            $avg = $data->avg('rating');

            $total = $data->count();


            $distributionRows = $data
                ->selectRaw('rating, COUNT(*) as count')
                ->groupBy('rating')
                ->orderByDesc('rating')
                ->get();

            $distribution = [];
            foreach ($distributionRows as $row) {
                $distribution[(int)$row->rating] = (int)$row->count;
            }

            // ensure keys 5..1 exist
            for ($i = 5; $i >= 1; $i--) {
                if (!isset($distribution[$i])) {
                    $distribution[$i] = 0;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'average_rating' => $avg !== null ? round((float)$avg, 1) : null,
                    'total_ratings' => $total,
                    'distribution' => $distribution,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Reviews summary failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to fetch summary'], 500);
        }
    }


}
