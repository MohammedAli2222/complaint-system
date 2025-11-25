<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCitizen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $userRole = auth()->user()->role;

        if ($userRole === 'citizen') {
            return $next($request);
        }

        return response()->json([
            'status' => false,
            'message' => 'You do not have permission to access this resource.'
        ], 403);
    }
}
