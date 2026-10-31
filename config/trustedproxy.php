<?php

/**
 * Trusted proxies for the framework's global TrustProxies middleware.
 *
 * Leave TRUSTED_PROXIES blank when PHP serves requests directly. Set it to the
 * reverse proxy / load balancer addresses (comma-separated), or "*" to trust
 * the immediate caller, so X-Forwarded-Proto/Host are honoured behind TLS
 * termination. The middleware reads this lazily at request time, so it keeps
 * working when the config is cached.
 */
return [
    'proxies' => env('TRUSTED_PROXIES') ?: null,
];
