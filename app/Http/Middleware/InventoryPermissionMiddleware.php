<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InventoryPermissionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission = 'view'): Response
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Define permission mappings
        $permissions = [
            'view' => ['inventory.view', 'inventory.manage'],
            'create' => ['inventory.create', 'inventory.manage'],
            'update' => ['inventory.update', 'inventory.manage'],
            'delete' => ['inventory.delete', 'inventory.manage'],
            'adjust' => ['inventory.adjust', 'inventory.manage'],
            'manage' => ['inventory.manage']
        ];

        $requiredPermissions = $permissions[$permission] ?? [$permission];

        // Check if user has any of the required permissions
        foreach ($requiredPermissions as $perm) {
            if ($user->hasPermission($perm)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Insufficient permissions for inventory management'
        ], 403);
    }
}
