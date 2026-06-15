<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckHierarchyAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Allow if user is in HR department OR is the CEO
        if ($user->department === 'HR' || $user->position === 'CEO') {
            return $next($request);
        }

        return response()->json([
            'message' => 'Forbidden. Only HR department or CEO can modify the hierarchy.'
        ], 403);
    }
}
