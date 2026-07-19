<?php

declare(strict_types=1);

use Doorbell\Config;
use Doorbell\CsvExport;
use Doorbell\Html;
use Doorbell\Recorder;
use Doorbell\Stats;
use Doorbell\Storage;

/** @var Config $config */
$config = require dirname(__DIR__) . '/bootstrap.php';

$storage = new Storage($config->database);
$token = is_string($_GET['stats'] ?? null) ? $_GET['stats'] : '';

// ---------------------------------------------------------------- dashboard

// hash_equals keeps the comparison constant-time, so the token cannot be
// recovered by timing repeated requests.
if ($token !== '' && hash_equals($config->statsToken, $token)) {
    $stats = new Stats($storage);

    if (isset($_GET['csv'])) {
        $export = new CsvExport($stats);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $export->filename($config->domain) . '"');
        echo $export->render($config->domain);

        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    // Requests to the dashboard are not part of the measurement.
    echo Html::render('dashboard', [
        'config' => $config,
        'totals' => $stats->totals(),
        'daily' => $stats->daily(),
        'referers' => $stats->topReferers(),
        'paths' => $stats->topPaths(),
        'hours' => $stats->hourHistogram(),
        'token' => $token,
    ]);

    exit;
}

// ------------------------------------------------------------- parked page

(new Recorder($config, $storage))->recordQuietly($_SERVER);

// The domain is for sale, not competing for rankings — keep it out of indexes
// so the traffic that shows up is genuinely direct rather than organic.
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Referrer-Policy: no-referrer-when-downgrade');

echo Html::render('parked', ['config' => $config]);
