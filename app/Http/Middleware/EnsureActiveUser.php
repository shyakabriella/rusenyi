<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$user->is_active || $user->status !== 'active') {

            $user->tokens()->delete();

            return response()->json([
                'success' => false,
                'message' => 'Your account has been disabled. Please contact the administrator.',
            ], 403);
        }

        return $next($request);
    }
}
