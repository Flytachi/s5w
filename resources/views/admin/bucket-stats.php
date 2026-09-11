<?php

use Main\Web\Fmt;
use Main\Web\TrafficChart;
use Main\Web\Ui;

$base = '/admin/ui/buckets/' . $bucket->id;

$bytes = TrafficChart::of($series, 'bytes');
$hits = TrafficChart::of($series, 'hits');

$days = count($series);
$preset = static function (int $back) use ($timezone): array {
    $tz = new DateTimeZone($timezone);
    $to = new DateTimeImmutable('now', $tz);

    return [$to->modify('-' . ($back - 1) . ' days')->format('Y-m-d'), $to->format('Y-m-d')];
};
$presets = [7 => $preset(7), 30 => $preset(30), 90 => $preset(90)];
$isActive = static fn(array $range): bool => $range[0] === $from && $range[1] === $to;
?>

<?php /* Без карточки: строка из полей и кнопок — это управление, а не содержимое,
         и рамка вокруг неё только съедала высоту у графиков ниже. */ ?>
<form class="drange mb-3" method="get" action="<?= $base ?>/stats">
    <span class="drange__label from-md" data-i18n="stats.date">Date</span>
    <input class="input drange__date" type="date" name="from" value="<?= Fmt::e($from) ?>" aria-label="From" data-i18n-attr="aria-label:stats.from">
    <span class="drange__dash from-md">—</span>
    <input class="input drange__date" type="date" name="to" value="<?= Fmt::e($to) ?>" aria-label="To" data-i18n-attr="aria-label:stats.to">
    <button class="btn btn--primary" type="submit" data-i18n="stats.show">Show</button>

    <span class="drange__spacer"></span>

    <div class="drange__presets">
        <?php foreach ($presets as $span => $range): ?>
            <a class="btn btn--sm <?= $isActive($range) ? 'btn--primary' : 'btn--ghost' ?>"
               href="<?= $base ?>/stats?from=<?= $range[0] ?>&amp;to=<?= $range[1] ?>"<?= $isActive($range) ? ' aria-current="true"' : '' ?>
               data-i18n="stats.preset" data-i18n-vars="<?= Fmt::e((string) json_encode(['n' => $span])) ?>"><?= $span ?> d</a>
        <?php endforeach; ?>
    </div>
</form>

<div class="grid grid--4 metrics-row mb-3">
    <div class="card">
        <div class="text-sm text-muted">Egress <span class="legend__hint">· <span data-i18n="stats.egressHint">outbound</span></span></div>
        <div class="stat-tile" data-fmt="bytes" data-value="<?= $totals->egress ?>"><?= Fmt::bytes($totals->egress) ?></div>
    </div>
    <div class="card">
        <div class="text-sm text-muted">Ingress <span class="legend__hint">· <span data-i18n="stats.ingressHint">inbound</span></span></div>
        <div class="stat-tile" data-fmt="bytes" data-value="<?= $totals->ingress ?>"><?= Fmt::bytes($totals->ingress) ?></div>
    </div>
    <div class="card">
        <div class="text-sm text-muted">Delivery requests</div>
        <div class="stat-tile" data-fmt="num" data-value="<?= $totals->deliveries ?>"><?= Fmt::num($totals->deliveries) ?></div>
    </div>
    <div class="card">
        <div class="text-sm text-muted">API requests</div>
        <div class="stat-tile" data-fmt="num" data-value="<?= $totals->api ?>"><?= Fmt::num($totals->api) ?></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card__header">
        <div class="card__title" data-i18n="stats.trafficTitle">Traffic by day</div>
        <div class="card__spacer"></div>
        <span class="text-sm text-muted">
            <?= Fmt::e($from) ?> — <?= Fmt::e($to) ?>, <span data-i18n-plural="stats.days" data-count="<?= $days ?>"><?= $days ?> days</span>
        </span>
    </div>

    <?= Ui::chart($bytes, 240, 'stats.emptyTraffic', 'No traffic in this period') ?>
    <?= Ui::legend($bytes, $bytes->isEmpty() ? [] : [
        [
            'key' => 'stats.peak',
            'label' => 'daily peak',
            'value' => sprintf(
                '<span data-fmt="bytes" data-value="%d">%s</span>',
                $bytes->peak,
                Fmt::e($bytes->peakLabel()),
            ),
        ],
        [
            'key' => 'stats.avgPerRequest',
            'label' => 'average per request',
            'value' => sprintf(
                '<span data-fmt="bytes" data-value="%d">%s</span>',
                $totals->averageServed(),
                Fmt::e(Fmt::bytes($totals->averageServed())),
            ),
        ],
    ]) ?>
</div>

<div class="card">
    <div class="card__header">
        <div class="card__title" data-i18n="stats.hitsTitle">Requests by day</div>
        <div class="card__spacer"></div>
        <span class="text-sm text-muted row" data-i18n-attr="title:stats.hitsHintTitle"
              title="A request counts even when no body is sent: 304, HEAD and a rejected range are requests too">
            <svg class="icon icon--sm"><use href="#i-info"/></svg> <span data-i18n="stats.hitsHint">responses without a body count too</span>
        </span>
    </div>

    <?= Ui::chart($hits, 170, 'stats.emptyHits', 'No requests in this period') ?>
    <?= Ui::legend($hits) ?>
</div>
