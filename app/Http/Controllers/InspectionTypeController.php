<?php

namespace App\Http\Controllers;

use App\Models\InspectionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class InspectionTypeController extends Controller
{
    public function index()
{
    try {

        $data = InspectionType::orderBy('id', 'asc')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'short_desc' => $item->short_desc,
                'price' => (float) $item->price,
                'status' => (int) $item->status,
                'img' => $item->img ? asset('storage/' . $item->img) : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Inspection types fetched successfully',
            'data' => $data
        ], 200);

    } catch (\Exception $e) {

        Log::error('InspectionType Index Error: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Something went wrong'
        ], 500);
    }
}
    /**
     * STORE
     */
    public function store(Request $request)
    {
        try {

            $request->validate([
                'title' => 'required|string|max:255',
                'short_desc' => 'required|string',
                'price' => 'required|numeric',
                'img' => 'nullable|image|mimes:jpg,png,jpeg',
                'status' => 'nullable|boolean'
            ]);

            $imgPath = null;

            if ($request->hasFile('img')) {
                $imgPath = $request->file('img')->store('inspection_types', 'public');
            }

            $inspection = InspectionType::create([
                'title' => $request->title,
                'short_desc' => $request->short_desc,
                'price' => $request->price,
                'status' => $request->status ?? 1,
                'img' => $imgPath,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Inspection type created successfully',
                'data' => [
                    'id' => $inspection->id,
                    'title' => $inspection->title,
                    'short_desc' => $inspection->short_desc,
                    'price' => (float) $inspection->price,
                    'status' => (int) $inspection->status,
                    'img' => $inspection->img ? asset('storage/' . $inspection->img) : null,
                ]
            ], 201);

        } catch (\Exception $e) {

            Log::error('InspectionType Store Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create inspection type'
            ], 500);
        }
    }

    /**
     * SHOW
     */
    public function show($id)
    {
        try {

            $item = InspectionType::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'short_desc' => $item->short_desc,
                    'price' => (float) $item->price,
                    'status' => (int) $item->status,
                    'img' => $item->img ? asset('storage/' . $item->img) : null,
                ]
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Inspection type not found'
            ], 404);
        }
    }

    /**
     * UPDATE
     */
    public function update(Request $request, $id)
    {
        try {

            $inspection = InspectionType::findOrFail($id);

            $request->validate([
                'title' => 'sometimes|string|max:255',
                'short_desc' => 'sometimes|string',
                'price' => 'sometimes|numeric',
                'img' => 'nullable|image|mimes:jpg,png,jpeg',
                'status' => 'nullable|boolean'
            ]);

            // UPDATE IMAGE
            if ($request->hasFile('img')) {

                if ($inspection->img && Storage::disk('public')->exists($inspection->img)) {
                    Storage::disk('public')->delete($inspection->img);
                }

                $inspection->img = $request->file('img')->store('inspection_types', 'public');
            }

            $inspection->update([
                'title' => $request->title ?? $inspection->title,
                'short_desc' => $request->short_desc ?? $inspection->short_desc,
                'price' => $request->price ?? $inspection->price,
                'status' => $request->status ?? $inspection->status,
                'img' => $inspection->img,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Inspection type updated successfully',
                'data' => [
                    'id' => $inspection->id,
                    'title' => $inspection->title,
                    'short_desc' => $inspection->short_desc,
                    'price' => (float) $inspection->price,
                    'status' => (int) $inspection->status,
                    'img' => $inspection->img ? asset('storage/' . $inspection->img) : null,
                ]
            ], 200);

        } catch (\Exception $e) {

            Log::error('InspectionType Update Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update inspection type'
            ], 500);
        }
    }

    /**
     * DELETE
     */
    public function destroy($id)
    {
        try {

            $inspection = InspectionType::findOrFail($id);

            if ($inspection->img && Storage::disk('public')->exists($inspection->img)) {
                Storage::disk('public')->delete($inspection->img);
            }

            $inspection->delete();

            return response()->json([
                'success' => true,
                'message' => 'Inspection type deleted successfully'
            ], 200);

        } catch (\Exception $e) {

            Log::error('InspectionType Delete Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete inspection type'
            ], 500);
        }
    }
}