<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers for a production-facing install, plus HTTPS enforcement.
 *
 * Only the safe, always-on headers are sent in every environment. The strict
 * parts (HSTS, forced HTTPS redirect, Content-Security-Policy) are applied in
 * production only, so local development and the in-memory test suite still work
 * without configuration tweaks.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Behind a reverse proxy this is secure when TRUSTED_PROXIES is set.
        $isProduction = app()->environment('production');
        $isSecure = $request->isSecure();

        if ($isProduction && ! $isSecure && ! $request->is('up')) {
            // 301 so search engines / browsers cache the HTTPS hop.
            return redirect()->secure($request->getRequestUri(), 301);
        }

        $response = $next($request);

        // Never break anything: these headers only add restrictions.
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=()'
        );

        // Strict parts, production only.
        if ($isProduction) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );

            // Permissive enough not to break the app's inline scripts and Vite
            // bundles, strict enough to block cross-origin script/style injection
            // and clickjacking. No eval restriction is relaxed because Alpine
            // and some helpers rely on it.
            $csp = "default-src 'self'; "
                 . "script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
                 . "style-src 'self' 'unsafe-inline'; "
                 . "img-src 'self' data: blob:; "
                 . "font-src 'self' data:; "
                 . "connect-src 'self'; "
                 . "frame-ancestors 'self'; "
                 . "base-uri 'self'; "
                 . "form-action 'self';";

            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }
}
