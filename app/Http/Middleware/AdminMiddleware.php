<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminMiddleware
{
    public function handle($request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Token is invalid or missing'
                ], 401);
            }

            // If not expecting JSON, redirect or show page
            return response('Unauthorized', 400);
        }

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Access denied. Admins only.'
            ], 403);
        }

        return $next($request);
    }
}

