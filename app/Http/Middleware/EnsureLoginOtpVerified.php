<?php

namespace App\Http\Middleware;

use App\Services\LoginOtp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLoginOtpVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->session()->get(LoginOtp::SESSION_PENDING)) {
            if ($request->routeIs('login.otp.*', 'logout')) {
                return $next($request);
            }

            return $request->expectsJson()
                ? response()->json(['message' => 'Email verification required.'], 403)
                : redirect()->route('login.otp.show');
        }

        return $next($request);
    }
}