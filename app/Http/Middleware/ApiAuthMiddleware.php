<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
class ApiAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if(!$token){
            return response()->json([
'error' => 'Unauthorized',
'message' => 'Token not provided'
            ],401);
        }
        if (!Auth::guard('api')->check()) {
            return response()->json(['error' => 'Unauthorized', 'message' => 'Invalid Token'], 401);
        }

        $user = Auth::guard('api')->user();

// connect the authenticated user to the request
$request->merge(['auth' =>$user]);

         return $next($request);
    }
}
