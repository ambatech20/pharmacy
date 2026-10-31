<?php

return [

    /*
    |---------------------------------------------------------------------------
    | The pharmacy's public host name
    |---------------------------------------------------------------------------
    |
    | Optional. When set, preflight checks that APP_URL uses the same host, so
    | a copy/paste mistake in .env is caught before staff hit it. Leave blank
    | on a fresh install or a developer machine: the app never depends on this
    | value being correct, and APP_URL is what generates absolute links.
    |
    */

    'domain' => env('INSTANCE_DOMAIN', ''),

    /*
    |---------------------------------------------------------------------------
    | Trusted proxies
    |---------------------------------------------------------------------------
    |
    | Anything that terminates the connection in front of PHP - a reverse proxy,
    | a CDN, or the host's own front end. Without this, every request looks like
    | plain HTTP even when the browser used HTTPS, and the HTTPS redirect in
    | SecurityHeaders loops forever.
    |
    | Set to "*" ONLY when nothing untrusted can reach PHP directly. On shared
    | hosting the safe answer is the single proxy IP; find it with:
    |
    |   php artisan pmas:preflight --json
    |
    | which echoes the address it saw in REMOTE_ADDR.
    |
    | Read here rather than from bootstrap/app.php so the value is available
    | after the configuration is cached, where env() returns null.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES', ''),

    /*
    |---------------------------------------------------------------------------
    | Fingerprint of a known-leaked APP_KEY
    |---------------------------------------------------------------------------
    |
    | If this install's repository ever committed .env, the APP_KEY is public:
    | anyone who can read the repository can decrypt session cookies and forge
    | signed URLs against it. Set LEAKED_KEY_FINGERPRINT to the SHA-256 of that
    | key so preflight can recognise it without writing the secret into a
    | second file. Empty means "no known leak", and the check is skipped.
    |
    */

    'leaked_key_fingerprint' => env('LEAKED_KEY_FINGERPRINT', ''),

];
