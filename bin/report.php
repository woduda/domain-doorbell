<?php

declare(strict_types=1);

namespace Doorbell;

/**
 * CLI report — useful over SSH or from cron.
 *
 *   php bin/report.php            summary + last 14 days
 *   php bin/report.php --days=60  longer window
 *   php bin/report.php --csv      raw CSV to stdout, for piping
 */

if (PHP_SAPI !== 'cli') {
    exit("This script is meant to be run from the command line.\n");
}

/** @var Config $config */
$config = require dirname(__DIR__) . '/bootstrap.php';

$options = getopt('', ['days::', 'csv']);
$days = isset($options['days']) ? max(1, (int) $options['days']) : 14;

$stats = new Stats(new Storage($config->database));

if (isset($options['csv'])) {
    echo (new CsvExport($stats))->render($config->domain);
    exit(0);
}

$totals = $stats->totals();

printf("%s — measuring since %s (%d days)\n\n", $config->domain, $totals['first_day'] ?? 'today', $totals['days']);
printf("  direct visits per week : %s\n", $totals['direct_per_week']);
printf("  direct total           : %d\n", $totals['direct']);
printf("  unique humans          : %d\n", $totals['humans']);
printf("  requests               : %d\n", $totals['hits']);
printf("  bots filtered          : %d\n\n", $totals['bots']);

$daily = $stats->daily($days);
$scale = max(1, ...array_map(static fn (array $row): int => $row['uniques'], $daily ?: [['uniques' => 1]]));

foreach ($daily as $row) {
    printf(
        "  %s  %3d / %3d  %s\n",
        $row['day'],
        $row['direct_uniques'],
        $row['uniques'],
        str_repeat('#', (int) round($row['direct_uniques'] / $scale * 40))
        . str_repeat('.', (int) round(($row['uniques'] - $row['direct_uniques']) / $scale * 40)),
    );
}

if ($daily === []) {
    echo "  No requests recorded yet.\n";
}

echo "\n  Columns: direct uniques / all human uniques. '#' direct, '.' referred.\n";
