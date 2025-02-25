<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\TokenBlacklist;

class CheckTokenBlacklist
{
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();

        if ($token && TokenBlacklist::where('token', $token)->exists()) {
            return response()->json(['message' => 'Token is invalidated. Please log in again.'], 401);
        }

        return $next($request);
    }
}
