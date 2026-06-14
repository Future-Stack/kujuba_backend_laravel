<?php

namespace App\Http\Controllers\Page;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PageController extends Controller
{
    /**
     * List all pages.
     */
    public function index()
    {
        try {
            $pages = Page::all();

            return response()->json([
                'success' => true,
                'data' => $pages
            ], 200);

        } catch (\Exception $e) {
            Log::error('Page index failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pages'
            ], 500);
        }
    }

    /**
     * Store a new page with system-generated slug.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'status' => 'boolean'
            ]);

            // Generate slug from title
            $validated['slug'] = Str::slug($validated['title']);

            // Ensure uniqueness
            $count = Page::where('slug', 'LIKE', $validated['slug'].'%')->count();
            if ($count > 0) {
                $validated['slug'] .= '-' . ($count + 1);
            }

            $page = Page::create($validated);

            return response()->json([
                'success' => true,
                'data' => $page,
                'message' => 'Page created successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Page store failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create page'
            ], 500);
        }
    }

    /**
     * Show a single page.
     */
    public function show($id)
    {
        try {
            $page = Page::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $page
            ], 200);

        } catch (\Exception $e) {
            Log::error('Page show failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Page not found'
            ], 404);
        }
    }

    /**
     * Update an existing page (slug auto-regenerated if title changes).
     */
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'content' => 'sometimes|string',
                'status' => 'boolean'
            ]);

//            return $validated;

            $page = Page::findOrFail($id);

            // If title is updated, regenerate slug
            if (isset($validated['title'])) {
                $slug = Str::slug($validated['title']);
                $count = Page::where('slug', 'LIKE', $slug.'%')->where('id', '!=', $id)->count();
                if ($count > 0) {
                    $slug .= '-' . ($count + 1);
                }
                $validated['slug'] = $slug;
            }

            $page->update($validated);

            return response()->json([
                'success' => true,
                'data' => $page,
                'message' => 'Page updated successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Page update failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update page'
            ], 500);
        }
    }

    /**
     * Delete a page.
     */
    public function destroy($id)
    {
        try {
            $page = Page::findOrFail($id);
            $page->delete();

            return response()->json([
                'success' => true,
                'message' => 'Page deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Page delete failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete page'
            ], 500);
        }
    }
}
