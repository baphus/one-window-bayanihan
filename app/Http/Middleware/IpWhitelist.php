<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IpWhitelist
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = config('auth.ip_whitelist.enabled', false);

        if (! $enabled) {
            return $next($request);
        }

        $whitelist = config('auth.ip_whitelist.addresses', []);
        $ip = $request->ip();

        foreach ($whitelist as $allowed) {
            if ($this->ipMatches($ip, $allowed)) {
                return $next($request);
            }
        }

        abort(403, 'Access denied from this IP address.');
    }

    private function ipMatches(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = -1 << (32 - $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
