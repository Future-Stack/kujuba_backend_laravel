<?php

namespace App\Http\Controllers;

use App\Models\InspectionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InspectionTypeController extends Controller
{
    /**
     * GET ALL
     */
    public function index()
    {
        $data = InspectionType::latest()->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'short_desc' => $item->short_desc,
                'price' => (float) $item->price,
                'status' => $item->status,

                // FULL IMAGE URL
                'img' => $item->img ? asset('storage/' . $item->img) : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * CREATE
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
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
            'data' => $inspection
        ]);
    }

    /**
     * SHOW
     */
    public function show($id)
    {
        $item = InspectionType::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $item->id,
                'title' => $item->title,
                'short_desc' => $item->short_desc,
                'price' => (float) $item->price,
                'status' => $item->status,
                'img' => $item->img ? asset('storage/' . $item->img) : null,
            ]
        ]);
    }

    /**
     * UPDATE
     */
    public function update(Request $request, $id)
    {
        $inspection = InspectionType::findOrFail($id);

        $request->validate([
            'title' => 'sometimes|string',
            'short_desc' => 'sometimes|string',
            'price' => 'sometimes|numeric',
            'img' => 'nullable|image|mimes:jpg,png,jpeg',
            'status' => 'nullable|boolean'
        ]);

        if ($request->hasFile('img')) {

            if ($inspection->img && Storage::disk('public')->exists($inspection->img)) {
                Storage::disk('public')->delete($inspection->img);
            }

            $inspection->img = $request->file('img')->store('inspection_types', 'public');
        }

        $inspection->update($request->only([
            'title',
            'short_desc',
            'price',
            'status'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Inspection type updated successfully',
            'data' => $inspection
        ]);
    }

    /**
     * DELETE
     */
    public function destroy($id)
    {
        $inspection = InspectionType::findOrFail($id);

        if ($inspection->img && Storage::disk('public')->exists($inspection->img)) {
            Storage::disk('public')->delete($inspection->img);
        }

        $inspection->delete();

        return response()->json([
            'success' => true,
            'message' => 'Inspection type deleted successfully'
        ]);
    }
}