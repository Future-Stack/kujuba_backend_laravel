<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InspectionReport;
use Illuminate\Support\Facades\Storage;

class InspectionReportController extends Controller
{
    /**
     * START INSPECTION
     */
    public function start($id)
    {
        $report = InspectionReport::firstOrCreate(
            ['inspection_assign_id' => $id]
        );

        $report->update([
            'status' => 'started',
            'started_at' => $report->started_at ?? now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inspection started',
            'data' => $this->formatReport($report)
        ]);
    }

    /**
     * SHOW REPORT
     */
    public function show($id)
    {
        $report = InspectionReport::where('inspection_assign_id', $id)->first();

        if (!$report) {
            return response()->json([
                'success' => true,
                'message' => 'No report found yet',
                'data' => [
                    'inspection_assign_id' => $id,
                    'notes' => null,
                    'media' => ['photos' => [], 'videos' => []],
                    'report_file' => null,
                    'status' => 'pending'
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatReport($report)
        ]);
    }

    /**
     * SAVE REPORT
     */
    public function save(Request $request, $id)
    {
        $report = InspectionReport::firstOrCreate(
            ['inspection_assign_id' => $id]
        );

        if (in_array($report->status, ['completed', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Report is locked'
            ], 403);
        }

        // NOTES
        if ($request->filled('notes')) {
            $report->notes = $request->notes;
        }

        // MEDIA
        if ($request->hasFile('photos') || $request->hasFile('videos')) {

            $media = $report->media ?? ['photos' => [], 'videos' => []];

            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $media['photos'][] = $photo->store('inspection/photos', 'public');
                }
            }

            if ($request->hasFile('videos')) {
                foreach ($request->file('videos') as $video) {
                    $media['videos'][] = $video->store('inspection/videos', 'public');
                }
            }

            $report->media = $media;
        }

        // REPORT FILE
        if ($request->hasFile('report_file')) {

            if ($report->report_file) {
                Storage::disk('public')->delete($report->report_file);
            }

            $report->report_file = $request->file('report_file')
                ->store('inspection/reports', 'public');
        }

        $report->save();

        return response()->json([
            'success' => true,
            'message' => 'Saved successfully',
            'data' => $this->formatReport($report)
        ]);
    }

    /**
     * FINAL SUBMIT
     */
    public function submit($id)
    {
        $report = InspectionReport::firstOrCreate(
            ['inspection_assign_id' => $id]
        );

        if ($report->started_at && now()->greaterThan($report->started_at->copy()->addHours(48))) {
            return response()->json([
                'success' => false,
                'message' => '48 hours expired'
            ], 403);
        }

        if (empty($report->notes)) {
            return response()->json([
                'success' => false,
                'message' => 'Notes is required'
            ], 400);
        }

        $media = $report->media ?? [];
        if (empty($media['photos']) && empty($media['videos'])) {
            return response()->json([
                'success' => false,
                'message' => 'At least one photo or video is required'
            ], 400);
        }

        if (empty($report->report_file)) {
            return response()->json([
                'success' => false,
                'message' => 'Report file is required'
            ], 400);
        }

        $report->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inspection completed successfully',
            'data' => $this->formatReport($report)
        ]);
    }

    /**
     * CANCEL
     */
    public function cancel($id)
    {
        $report = InspectionReport::firstOrCreate(
            ['inspection_assign_id' => $id]
        );

        if ($report->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel completed report'
            ], 403);
        }

        $report->update([
            'status' => 'cancelled',
            'cancelled_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inspection cancelled successfully',
            'data' => $this->formatReport($report)
        ]);
    }

    /**
     * FORMAT
     */
    private function formatReport($report)
    {
        return [
            'id' => $report->id,
            'inspection_assign_id' => $report->inspection_assign_id,
            'notes' => $report->notes,
            'homeowner_feedback' => $report->homeowner_feedback ?? null,

            'media' => [
                'photos' => collect($report->media['photos'] ?? [])
                    ->map(fn ($p) => asset('storage/' . $p))->values(),

                'videos' => collect($report->media['videos'] ?? [])
                    ->map(fn ($v) => asset('storage/' . $v))->values(),
            ],

            'report_file' => $report->report_file
                ? asset('storage/' . $report->report_file)
                : null,

            'status' => $report->status,
            'started_at' => $report->started_at,
            'completed_at' => $report->completed_at,
            'cancelled_at' => $report->cancelled_at,
        ];
    }

    /**
     * HOMEOWNER REPORT
     */
  public function homeownerReport($id)
{
    $report = InspectionReport::with([
        'inspectionAssign.inspector',
        'inspectionAssign.inspectionBooking.inspectionTypes',
        'inspectionAssign.inspectionBooking.payment'
    ])->findOrFail($id);

    // ================= AUTH CHECK =================
    if ($report->inspectionAssign->inspectionBooking->user_id !== auth()->id()) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 403);
    }

    // ================= STATUS CHECK =================
    if ($report->status !== 'completed') {
        return response()->json([
            'success' => false,
            'message' => 'Not completed'
        ], 403);
    }

    $assign = $report->inspectionAssign;
    $booking = $assign?->inspectionBooking;

    return response()->json([
        'success' => true,

        'data' => [

            // ================= REPORT =================
            'report' => $this->formatReport($report),

            // ================= INSPECTOR =================
            'inspector' => [
                'id' => $assign?->inspector?->id,
                'name' => trim(
                    ($assign?->inspector?->first_name ?? '') . ' ' .
                    ($assign?->inspector?->last_name ?? '')
                ),
                'email' => $assign?->inspector?->email,
            ],

            // ================= BOOKING =================
            'booking' => [
                'id' => $booking?->id,
                'booking_uid' => $booking ? 'INS-' . (1000 + $booking->id) : null,
                'property_address' => $booking?->property_address,
                'property_type' => $booking?->property_type,
                'property_size' => $booking?->property_size,
                'note' => $booking?->note,
                'scheduled_date' => $booking?->scheduled_date,
                'scheduled_time' => $booking?->scheduled_time,
                'scheduled_shift' => $booking?->scheduled_shift,
                'urgent_status' => (bool) ($booking?->urgent_status ?? 0),
                'status' => $booking?->status,
                'property_img' => $booking?->property_img
                    ? asset('storage/' . $booking->property_img)
                    : null,
            ],

            // ================= INSPECTION TYPES =================
            'inspection_types' => $booking?->inspectionTypes->map(function ($type) {
                return [
                    'id' => $type->id,
                    'title' => $type->title,
                    'short_desc' => $type->short_desc,
                    'price' => (float) $type->price,
                    'img' => $type->img
                        ? asset('storage/' . $type->img)
                        : null,
                ];
            })->values(),

            // ================= PAYMENT =================
            // 'payment' => [
            //     'subtotal' => $booking?->payment?->subtotal,
            //     'platform_fee' => $booking?->payment?->platform_fee,
            //     'total' => $booking?->payment?->total,
            //     'trx_id' => $booking?->payment?->trx_id,
            //     'status' => $booking?->payment?->status,
            // ],
        ]
    ]);
}

    /**
     * HOMEOWNER FEEDBACK
     */
    public function homeownerNote(Request $request, $id)
    {
        $request->validate([
            'homeowner_feedback' => 'required|string'
        ]);

        $report = InspectionReport::with('inspectionAssign.inspectionBooking')->findOrFail($id);

        if ($report->status !== 'completed') {
            return response()->json(['success' => false, 'message' => 'Not completed'], 400);
        }

        if ($report->inspectionAssign->inspectionBooking->user_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($report->homeowner_feedback) {
            return response()->json(['success' => false, 'message' => 'Already submitted'], 400);
        }

        $report->update([
            'homeowner_feedback' => $request->homeowner_feedback
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Feedback submitted',
            'data' => $this->formatReport($report)
        ]);
    }

    /**
     * SHARE REPORT
     */
    public function shareReport($id)
    {
        $report = InspectionReport::findOrFail($id);

        return response()->json([
            'success' => true,
            'share_url' => $report->report_file
                ? asset('storage/' . $report->report_file)
                : null
        ]);
    }





 public function inspectorReportHistory($inspectorId)
{
    $reports = InspectionReport::with([
            'inspectionAssign.inspector',
            'inspectionAssign.inspectionBooking.payment',
            'inspectionAssign.inspectionBooking.inspectionTypes',
            'inspectionAssign.review' // 👈 ADD THIS
        ])
        ->whereHas('inspectionAssign', function ($q) use ($inspectorId) {
            $q->where('inspector_id', $inspectorId);
        })
        ->where('status', 'completed')
        ->orderBy('completed_at', 'desc')
        ->get();

    return response()->json([
        'success' => true,
        'count' => $reports->count(),
        'data' => $reports->map(function ($report) {

            $assign = $report->inspectionAssign;
            $booking = $assign?->inspectionBooking;
            $review  = $assign?->review; // 👈 NEW

            return [

                // ================= REPORT =================
                'id' => $report->id,
                'inspection_assign_id' => $report->inspection_assign_id,
                'notes' => $report->notes,
                'status' => $report->status,
                'completed_at' => $report->completed_at,

                'report_file' => $report->report_file
                    ? asset('storage/' . $report->report_file)
                    : null,

                // ================= INSPECTOR =================
                'inspector' => [
                    'id' => $assign?->inspector?->id,
                    'name' => trim(
                        ($assign?->inspector?->first_name ?? '') . ' ' .
                        ($assign?->inspector?->last_name ?? '')
                    ),
                    'email' => $assign?->inspector?->email,
                ],

                // ================= BOOKING =================
                'booking' => [
                    'id' => $booking?->id,
                    'booking_uid' => $booking ? 'INS-' . (1000 + $booking->id) : null,
                    'property_address' => $booking?->property_address,
                    'property_type' => $booking?->property_type,
                    'property_size' => $booking?->property_size,
                    'note' => $booking?->note,
                    'scheduled_date' => $booking?->scheduled_date,
                    'scheduled_time' => $booking?->scheduled_time,
                    'scheduled_shift' => $booking?->scheduled_shift,
                    'urgent_status' => (bool) ($booking?->urgent_status ?? 0),
                    'status' => $booking?->status,
                    'property_img' => $booking?->property_img
                        ? asset('storage/' . $booking->property_img)
                        : null,
                ],

                // ================= PAYMENT =================
                'payment' => [
                    'subtotal' => $booking?->payment?->subtotal,
                    'platform_fee' => $booking?->payment?->platform_fee,
                    'total' => $booking?->payment?->total,
                    'trx_id' => $booking?->payment?->trx_id,
                    'status' => $booking?->payment?->status,
                ],

                // ================= INSPECTION TYPES =================
                'inspection_types' => $booking?->inspectionTypes
                    ? $booking->inspectionTypes->map(function ($type) {
                        return [
                            'id' => $type->id,
                            'title' => $type->title,
                            'short_desc' => $type->short_desc,
                            'price' => (float) $type->price,
                            'img' => $type->img
                                ? asset('storage/' . $type->img)
                                : null,
                        ];
                    })->values()
                    : [],

                // ================= REVIEW (NEW) =================
                'review' => $review ? [
                    'rating' => $review->rating,
                    'description' => $review->description,
                    'homeowner_id' => $review->homeowner_id,
                ] : null,

                // ================= EXTRA FEEDBACK =================
                'homeowner_feedback' => $report->homeowner_feedback ?? null,
            ];
        })
    ]);
}
}