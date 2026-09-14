<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        // Super Admin has full access to everything
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Check if In-House Admin has the required permission
        if ($user->isInHouseAdmin() && $user->hasPermission($permission)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => "Access denied. You do not have permission to access the '{$permission}' module."
        ], 403);
    }
}
