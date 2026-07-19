<?php

declare(strict_types=1);

use Doorbell\Config;
use Doorbell\Html;

/** @var Config $config */

$subject = rawurlencode('Offer for ' . $config->domain);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= Html::esc($config->domain) ?> — domain for sale</title>
    <style>
        :root { --ink: #14161a; --paper: #f6f4ef; --muted: #6a6862; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100svh; padding: 2rem;
            display: grid; place-items: center;
            background: var(--paper); color: var(--ink);
            font: 16px/1.6 ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
        }
        main { max-width: 32rem; text-align: center; }
        h1 {
            margin: 0 0 .35rem; letter-spacing: -.03em; font-weight: 600;
            font-size: clamp(1.9rem, 8vw, 3.25rem); overflow-wrap: anywhere;
        }
        p { margin: 0 0 1.75rem; color: var(--muted); }
        a {
            display: inline-block; padding: .7rem 1.5rem; border-radius: 2px;
            background: var(--ink); color: var(--paper); text-decoration: none;
            transition: background .15s ease;
        }
        a:hover { background: #363a41; }
        a:focus-visible { outline: 2px solid var(--ink); outline-offset: 3px; }
        @media (prefers-reduced-motion: reduce) { a { transition: none; } }
    </style>
</head>
<body>
<main>
    <h1><?= Html::esc($config->domain) ?></h1>
    <p>This domain is available for purchase.</p>
    <?php if ($config->contactEmail !== null): ?>
        <a href="mailto:<?= Html::esc($config->contactEmail) ?>?subject=<?= $subject ?>">Make an offer</a>
    <?php endif; ?>
</main>
</body>
</html>
