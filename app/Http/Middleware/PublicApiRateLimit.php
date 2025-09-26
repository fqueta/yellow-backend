<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

class PublicApiRateLimit
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 5, int $decayMinutes = 1): mixed
    {
        $key = $this->resolveRequestSignature($request);
        
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new ThrottleRequestsException('Muitas tentativas. Tente novamente em alguns minutos.');
        }
        
        RateLimiter::hit($key, $decayMinutes * 60);
        
        return $next($request);
    }
    
    /**
     * Resolve request signature.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        return sha1(
            $request->method() .
            '|' . $request->server('SERVER_NAME') .
            '|' . $request->path() .
            '|' . $request->ip()
        );
    }
}