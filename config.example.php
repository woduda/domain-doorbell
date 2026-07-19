<?php

declare(strict_types=1);

/**
 * Copy this file to config.php and edit it. config.php is git-ignored.
 *
 * Generate secrets with:
 *   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
 */

return [
    // The domain you are measuring. Shown on the parked page and in exports.
    'domain' => 'example.com',

    // Where offers should be sent. Set to null to hide the contact button.
    'contact_email' => 'you@example.com',

    // Secret used to hash visitor IP addresses. Never commit it.
    // Changing it resets unique-visitor counting from that moment on.
    'salt' => 'CHANGE_ME_at_least_32_random_characters',

    // Dashboard access token: https://example.com/?stats=<token>
    'stats_token' => 'CHANGE_ME_too',

    // Absolute path to the SQLite database file.
    'database' => __DIR__ . '/var/doorbell.sqlite',

    // Set to true ONLY if the site really sits behind Cloudflare.
    // When true, CF-Connecting-IP is trusted over REMOTE_ADDR.
    'behind_cloudflare' => false,
];
