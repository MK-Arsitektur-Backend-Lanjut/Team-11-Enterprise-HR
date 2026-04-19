<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class VerifyMicroserviceToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthorized. Token not provided.'], 401);
        }

        // Call the Attendance Module Auth endpoint using the bearer token
        $attendanceBaseUrl = config('services.attendance.url');
        $authServiceUrl = rtrim($attendanceBaseUrl, '/') . '/auth/me';

        try {
            $response = Http::withToken($token)->get($authServiceUrl);

            if ($response->successful()) {
                $data = $response->json();

                // Add authenticated user employee details into the request
                // By doing this, the controllers can retrieve: $request->attributes->get('employee_id')
                if (isset($data['success']) && $data['success'] && isset($data['data']['employee']['id'])) {
                    $request->attributes->add([
                        'user_data' => $data['data'],
                        'employee_id' => $data['data']['employee']['id']
                    ]);
                    return $next($request);
                }
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to authenticate token with auth service.'], 500);
        }

        return response()->json(['message' => 'Unauthorized. Invalid token.'], 401);
    }
}
