<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menambahkan HTTP security headers pada setiap response (VULN-008).
 * Mengurangi risiko clickjacking, MIME sniffing, kebocoran referrer, dan
 * memaksa HTTPS di production.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Permissions-Policy' => 'geolocation=(self), camera=(), microphone=()',
            // API mengembalikan JSON, jadi CSP ketat aman dipakai.
            'Content-Security-Policy' => "default-src 'self'; frame-ancestors 'none'",
        ];

        // HSTS hanya relevan lewat HTTPS. Diaktifkan saat request sudah secure
        // agar tidak mengganggu development di http lokal.
        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}
