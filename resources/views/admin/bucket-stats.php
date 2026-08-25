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
$presets = [
    '7 д' => $preset(7),
    '30 д' => $preset(30),
    '90 д' => $preset(90),
];
$isActive = static fn(array $range): bool => $range[0] === $from && $range[1] === $to;
?>

<?php /* Без карточки: строка из полей и кнопок — это управление, а не содержимое,
         и рамка вокруг неё только съедала высоту у графиков ниже. */ ?>
<form class="drange mb-3" method="get" action="<?= $base ?>/stats">
    <span class="drange__label from-md">Дата</span>
    <input class="input drange__date" type="date" name="from" value="<?= Fmt::e($from) ?>" aria-label="С">
    <span class="drange__dash from-md">—</span>
    <input class="input drange__date" type="date" name="to" value="<?= Fmt::e($to) ?>" aria-label="По">
    <button class="btn btn--primary" type="submit">Показать</button>

    <span class="drange__spacer"></span>

    <div class="drange__presets">
        <?php foreach ($presets as $label => $range): ?>
            <a class="btn btn--sm <?= $isActive($range) ? 'btn--primary' : 'btn--ghost' ?>"
               href="<?= $base ?>/stats?from=<?= $range[0] ?>&amp;to=<?= $range[1] ?>"<?= $isActive($range) ? ' aria-current="true"' : '' ?>><?= Fmt::e($label) ?></a>
        <?php endforeach; ?>
    </div>
</form>

<div class="grid grid--4 metrics-row mb-3">
    <div class="card">
        <div class="text-sm text-muted">Egress <span class="legend__hint">· исходящий</span></div>
        <div class="stat-tile"><?= Fmt::bytes($totals->egress) ?></div>
    </div>
    <div class="card">
        <div class="text-sm text-muted">Ingress <span class="legend__hint">· входящий</span></div>
        <div class="stat-tile"><?= Fmt::bytes($totals->ingress) ?></div>
    </div>
    <div class="card">
        <div class="text-sm text-muted">Delivery requests</div>
        <div class="stat-tile"><?= Fmt::num($totals->deliveries) ?></div>
    </div>
    <div class="card">
        <div class="text-sm text-muted">API requests</div>
        <div class="stat-tile"><?= Fmt::num($totals->api) ?></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card__header">
        <div class="card__title">Трафик по суткам</div>
        <div class="card__spacer"></div>
        <span class="text-sm text-muted">
            <?= Fmt::e($from) ?> — <?= Fmt::e($to) ?>, <?= Fmt::num($days) ?> <?= Fmt::plural($days, 'день', 'дня', 'дней') ?>
        </span>
    </div>

    <?= Ui::chart($bytes, 240, 'За этот период трафика не было') ?>
    <?= Ui::legend($bytes, $bytes->isEmpty() ? [] : [
        'пик за сутки — ' . $bytes->peakLabel(),
        'в среднем на запрос — ' . Fmt::bytes($totals->averageServed()),
    ]) ?>
</div>

<div class="card">
    <div class="card__header">
        <div class="card__title">Запросы по суткам</div>
        <div class="card__spacer"></div>
        <span class="text-sm text-muted row" title="Запрос засчитывается, даже если тело не пошло: 304, HEAD и отвергнутый диапазон — это тоже запросы">
            <svg class="icon icon--sm"><use href="#i-info"/></svg> считаются и ответы без тела
        </span>
    </div>

    <?= Ui::chart($hits, 170, 'За этот период запросов не было') ?>
    <?= Ui::legend($hits) ?>
</div>
