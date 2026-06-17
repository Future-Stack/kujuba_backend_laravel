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

           

            'total_started_reports' => InspectionReport::where('status', 'started')->count(),

            'total_completed_reports' => InspectionReport::where('status', 'completed')->count(),

            

            // 🔥 archived = favorite true
            'total_archived_reports' => InspectionReport::where('is_favorite', true)->count(),
        ]
        ]);
    }

    /**
     * 📋 LIST
     */
    public function index(Request $request)
{
    $query = InspectionReport::with(
        'inspectionAssign.inspectionBooking.user',
        'inspectionAssign.inspector'
    );

    // ✅ STATUS FILTER (SAFE + LOWERCASE NORMALIZATION)
    if ($request->filled('status')) {

        $status = strtolower($request->status);

        $query->where('status', $status);
    }

    // 🔍 SEARCH
    if ($request->filled('search')) {
        $search = $request->search;

        $query->where(function ($q) use ($search) {
            $q->where('inspection_assign_id', 'like', "%{$search}%")
              ->orWhere('notes', 'like', "%{$search}%");
        });
    }

    $reports = $query->latest()->paginate(10);

    return response()->json([
        'success' => true,
        'data' => [
            'reports' => $reports->getCollection()->map(fn ($report) => $this->formatReport($report)),

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
        $report = InspectionReport::with(
            'inspectionAssign.inspectionBooking.user',
            'inspectionAssign.inspector'
        )->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->formatReport($report)
        ]);
    }

    /**
     * 📥 DOWNLOAD REPORT FILE
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
     * 📦 ARCHIVE REPORT
     */
    public function archive($id)
    {
         $report = InspectionReport::findOrFail($id);

            $report->update([
                'is_favorite' => !$report->is_favorite
            ]);

            return response()->json([
                'success' => true,
                'data' => $report
            ]);
     }

    /**
     * ⭐ FAVORITE TOGGLE
     */
    public function toggleFavorite($id)
    {
        $report = InspectionReport::findOrFail($id);

        $report->update([
            'is_favorite' => !$report->is_favorite
        ]);

        return response()->json([
            'success' => true,
            'message' => $report->is_favorite
                ? 'Added to favorites'
                : 'Removed from favorites',
            'data' => [
                'id' => $report->id,
                'is_favorite' => $report->is_favorite
            ]
        ]);
    }

    /**
     * 🔥 FORMAT REPORT
     */
    private function formatReport($report)
{
    $user = $report->inspectionAssign?->inspectionBooking?->user;

    return [
        'id' => $report->id,

        // ✅ ONLY USER NAME (NO ADDRESS)
        'user_name' =>
            trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: null,

        'location' =>
            $report->inspectionAssign?->inspectionBooking?->location ?? null,

        'inspection_id' =>
            'INS:' . str_pad($report->inspection_assign_id, 10, '0', STR_PAD_LEFT),

        'report_id' =>
            'RPT:' . str_pad($report->id, 4, '0', STR_PAD_LEFT),

        'inspector_email' =>
            $report->inspectionAssign?->inspector?->email ?? null,

        'created_date' =>
            $report->created_at?->format('d M Y'),

        'status' =>
            ucfirst($report->status),

        'is_favorite' =>
            (bool) $report->is_favorite,

        'homeowner_feedback' =>
            $report->homeowner_feedback ?? null,

        'report_details' => [
            'notes' => $report->notes,

            'media' => [
                'photos' => collect($report->media['photos'] ?? [])
                    ->map(fn ($path) => asset('storage/' . $path))
                    ->values()
                    ->all(),

                'videos' => collect($report->media['videos'] ?? [])
                    ->map(fn ($path) => asset('storage/' . $path))
                    ->values()
                    ->all(),
            ],

            'report_file' =>
                $report->report_file
                    ? asset('storage/' . $report->report_file)
                    : null,
        ],
    ];
}
}