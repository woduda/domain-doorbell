<?php

declare(strict_types=1);

use Doorbell\Config;
use Doorbell\Html;

/**
 * @var Config $config
 * @var array{hits:int,bots:int,direct:int,humans:int,first_day:?string,days:int,direct_per_week:float} $totals
 * @var list<array{day:string,direct_uniques:int,uniques:int,bot_hits:int}> $daily
 * @var list<array{value:string,hits:int}> $referers
 * @var list<array{value:string,hits:int}> $paths
 * @var array<int,int> $hours
 * @var string $token
 */

$scale = max(1, ...array_map(static fn (array $r): int => $r['uniques'], $daily ?: [['uniques' => 1]]));
$hourScale = max(1, ...array_values($hours));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Doorbell — <?= Html::esc($config->domain) ?></title>
    <style>
        :root {
            color-scheme: dark;
            --bg: #0f1218; --panel: #151a23; --line: #212836;
            --text: #d5dae3; --dim: #6b7a90; --bright: #f1f4f9;
            --key: #6ee7a8; --key-dim: #2f6f52;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 2.5rem 1.25rem 4rem; background: var(--bg); color: var(--text);
            font: 14px/1.55 ui-monospace, "SF Mono", Menlo, Consolas, monospace;
        }
        main { max-width: 62rem; margin: 0 auto; }
        header { margin-bottom: 2rem; }
        h1 { font-size: 1rem; font-weight: 600; margin: 0 0 .3rem; color: var(--bright); }
        header p { margin: 0; color: var(--dim); font-size: .8rem; }
        h2 {
            font-size: .72rem; letter-spacing: .14em; text-transform: uppercase;
            color: var(--dim); font-weight: 500; margin: 2.75rem 0 .8rem;
        }
        .cards {
            display: grid; gap: 1px; background: var(--line); border: 1px solid var(--line);
            grid-template-columns: repeat(auto-fit, minmax(8.5rem, 1fr));
        }
        .card { background: var(--panel); padding: .95rem 1.05rem; }
        .card b { display: block; font-size: 1.7rem; font-weight: 600; color: var(--bright); }
        .card span { font-size: .68rem; letter-spacing: .07em; text-transform: uppercase; color: var(--dim); }
        .card.key b { color: var(--key); }
        table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        th, td { text-align: left; padding: .34rem .7rem .34rem 0; border-bottom: 1px solid #1a2029; }
        th { color: var(--dim); font-weight: 500; }
        td.num { text-align: right; width: 6rem; color: var(--bright); font-variant-numeric: tabular-nums; }
        td.day { width: 6.5rem; color: var(--dim); }
        .bar { height: 8px; background: var(--key-dim); border-radius: 1px; min-width: 1px; }
        .bar.direct { background: var(--key); margin-bottom: 2px; }
        .trunc { display: block; max-width: 34rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .empty { color: var(--dim); }
        .hours { display: flex; align-items: flex-end; gap: 2px; height: 4.5rem; }
        .hours div { flex: 1; background: var(--key-dim); border-radius: 1px 1px 0 0; min-height: 1px; }
        .hours-axis { display: flex; justify-content: space-between; color: var(--dim); font-size: .68rem; margin-top: .3rem; }
        footer { margin-top: 3rem; color: var(--dim); font-size: .78rem; }
        a { color: var(--key); }
    </style>
</head>
<body>
<main>
    <header>
        <h1><?= Html::esc($config->domain) ?></h1>
        <p>
            Measuring since <?= Html::esc($totals['first_day'] ?? 'today') ?>
            (<?= $totals['days'] ?> day<?= $totals['days'] === 1 ? '' : 's' ?>).
            All times UTC.
        </p>
    </header>

    <div class="cards">
        <div class="card key">
            <b><?= $totals['direct_per_week'] ?></b>
            <span>direct visits / week</span>
        </div>
        <div class="card"><b><?= $totals['direct'] ?></b><span>direct total</span></div>
        <div class="card"><b><?= $totals['humans'] ?></b><span>unique humans</span></div>
        <div class="card"><b><?= $totals['hits'] ?></b><span>requests</span></div>
        <div class="card"><b><?= $totals['bots'] ?></b><span>bots filtered</span></div>
    </div>

    <h2>Daily unique visitors — bright bar is direct</h2>
    <table>
        <?php foreach ($daily as $row): ?>
            <tr>
                <td class="day"><?= Html::esc($row['day']) ?></td>
                <td class="num"><?= $row['direct_uniques'] ?> / <?= $row['uniques'] ?></td>
                <td>
                    <div class="bar direct" style="width: <?= round($row['direct_uniques'] / $scale * 100, 1) ?>%"></div>
                    <div class="bar" style="width: <?= round($row['uniques'] / $scale * 100, 1) ?>%"></div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($daily === []): ?>
            <tr><td class="empty">No requests recorded yet.</td></tr>
        <?php endif; ?>
    </table>

    <h2>Hour of day — human requests</h2>
    <div class="hours">
        <?php foreach ($hours as $hour => $hits): ?>
            <div style="height: <?= round($hits / $hourScale * 100, 1) ?>%" title="<?= $hour ?>:00 — <?= $hits ?>"></div>
        <?php endforeach; ?>
    </div>
    <div class="hours-axis"><span>00</span><span>06</span><span>12</span><span>18</span><span>23</span></div>

    <h2>Referrers</h2>
    <table>
        <tr><th>Source</th><th class="num">Hits</th></tr>
        <?php foreach ($referers as $row): ?>
            <tr>
                <td><span class="trunc"><?= Html::esc($row['value']) ?></span></td>
                <td class="num"><?= $row['hits'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($referers === []): ?>
            <tr><td colspan="2" class="empty">None — every human request arrived without a referrer.</td></tr>
        <?php endif; ?>
    </table>

    <h2>Requested paths</h2>
    <table>
        <tr><th>Path</th><th class="num">Hits</th></tr>
        <?php foreach ($paths as $row): ?>
            <tr>
                <td><span class="trunc"><?= Html::esc($row['value']) ?></span></td>
                <td class="num"><?= $row['hits'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($paths === []): ?>
            <tr><td colspan="2" class="empty">Nothing recorded yet.</td></tr>
        <?php endif; ?>
    </table>

    <footer>
        <a href="?stats=<?= Html::esc($token) ?>&amp;csv=1">Download CSV</a> —
        attach it to the listing instead of claiming numbers you cannot show.
        Paths other than <code>/</code> mean someone is typing remembered URLs;
        check what used to live there on web.archive.org.
    </footer>
</main>
</body>
</html>
