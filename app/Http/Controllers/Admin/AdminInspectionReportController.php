<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InspectionReport;
use Illuminate\Support\Facades\Storage;

class AdminInspectionReportController extends Controller
{
    /**
     * 📊 STATS
     */
    public function stats()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_reports' => InspectionReport::count(),
                'total_pending_reports' => InspectionReport::where('status', 'pending')->count(),
                'total_completed_reports' => InspectionReport::where('status', 'completed')->count(),
                'total_cancelled_reports' => InspectionReport::where('status', 'cancelled')->count(),
                'total_archived_reports' => InspectionReport::where('status', 'archived')->count(),
            ]
        ]);
    }

    /**
     * 📋 LIST
     */
    public function index(Request $request)
    {
        $query = InspectionReport::with('inspectionAssign.inspectionBooking.user', 'inspectionAssign.inspector')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('inspection_assign_id', 'like', '%' . $request->search . '%')
                  ->orWhere('notes', 'like', '%' . $request->search . '%');
        }

        $reports = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => [
                'reports' => collect($reports->items())->map(function ($report) {
                    return $this->formatReport($report);
                }),
                'pagination' => [
                    'current_page' => $reports->currentPage(),
                    'last_page' => $reports->lastPage(),
                    'total' => $reports->total(),
                    'next_page_url' => $reports->nextPageUrl(),
                    'prev_page_url' => $reports->previousPageUrl(),
                ]
            ]
        ]);
    }

    /**
     * 👁 SHOW
     */
    public function show($id)
    {
        $report = InspectionReport::with('inspectionAssign.inspectionBooking.user', 'inspectionAssign.inspector')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->formatReport($report)
        ]);
    }

    /**
     * 📥 DOWNLOAD
     */
    public function download($id)
    {
        $report = InspectionReport::findOrFail($id);

        if (!$report->report_file) {
            return response()->json([
                'success' => false,
                'message' => 'No file found'
            ], 404);
        }

        return Storage::disk('public')->download($report->report_file);
    }

    /**
     * 📦 ARCHIVE
     */
    public function archive($id)
    {
        $report = InspectionReport::findOrFail($id);

        if ($report->status === 'archived') {
            return response()->json([
                'success' => false,
                'message' => 'Already archived'
            ], 400);
        }

        $report->update([
            'status' => 'archived'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report archived successfully'
        ]);
    }




//favorite toggle
    public function toggleFavorite($id)
{
    $report = InspectionReport::findOrFail($id);

    $report->update([
        'is_favorite' => !$report->is_favorite
    ]);

    return response()->json([
        'success' => true,
        'message' => $report->is_favorite
            ? 'Report added to favorites'
            : 'Report removed from favorites',
        'data' => [
            'id' => $report->id,
            'is_favorite' => $report->is_favorite
        ]
    ]);
}



    /**
     *  FORMAT (MODEL NAME FOLLOWED EXACTLY)
     */
    private function formatReport($report)
{
    return [
        'id' => $report->id,

        'user_name' =>
            ($report->inspectionAssign->inspectionBooking->user->first_name ?? '') . ' ' .
            ($report->inspectionAssign->inspectionBooking->user->last_name ?? ''),

        'location' =>
            $report->inspectionAssign->inspectionBooking->location ?? null,

        'inspection_id' => 'INS:' . str_pad(
            $report->inspection_assign_id,
            10,
            '0',
            STR_PAD_LEFT
        ),

        'report_id' => 'RPT:' . str_pad(
            $report->id,
            4,
            '0',
            STR_PAD_LEFT
        ),

        'inspector_email' =>
            $report->inspectionAssign->inspector->email ?? null,

        'created_date' =>
            $report->created_at?->format('d M Y'),

        'status' =>
            ucfirst($report->status),

        'is_favorite' =>
            (bool) $report->is_favorite,

        'report_details' => [
            'notes' => $report->notes,

            'media' => [
                'photos' => collect($report->media['photos'] ?? [])
                    ->map(fn ($path) => asset('storage/' . $path))
                    ->values(),

                'videos' => collect($report->media['videos'] ?? [])
                    ->map(fn ($path) => asset('storage/' . $path))
                    ->values(),
            ],

            'report_file' => $report->report_file
                ? asset('storage/' . $report->report_file)
                : null,
        ],
    ];
}
}