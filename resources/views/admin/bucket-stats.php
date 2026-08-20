<?php

use Main\Web\Fmt;
use Main\Web\TrafficChart;

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
    <span class="drange__label">Дата</span>
    <input class="input drange__date" type="date" name="from" value="<?= Fmt::e($from) ?>" aria-label="С">
    <span class="drange__dash">—</span>
    <input class="input drange__date" type="date" name="to" value="<?= Fmt::e($to) ?>" aria-label="По">
    <button class="btn btn--primary" type="submit">Показать</button>

    <span class="drange__spacer"></span>

    <?php foreach ($presets as $label => $range): ?>
        <a class="btn btn--sm <?= $isActive($range) ? 'btn--primary' : 'btn--ghost' ?>"
           href="<?= $base ?>/stats?from=<?= $range[0] ?>&amp;to=<?= $range[1] ?>"><?= Fmt::e($label) ?></a>
    <?php endforeach; ?>
</form>

<div class="grid grid--4 mb-3">
    <div class="card">
        <div class="card__title text-sm text-muted">Egress <span class="legend__hint">· исходящий</span></div>
        <div class="stat-tile"><b><?= Fmt::bytes($totals->egress) ?></b></div>
    </div>
    <div class="card">
        <div class="card__title text-sm text-muted">Ingress <span class="legend__hint">· входящий</span></div>
        <div class="stat-tile"><b><?= Fmt::bytes($totals->ingress) ?></b></div>
    </div>
    <div class="card">
        <div class="card__title text-sm text-muted">Delivery requests</div>
        <div class="stat-tile"><b><?= Fmt::num($totals->deliveries) ?></b></div>
    </div>
    <div class="card">
        <div class="card__title text-sm text-muted">API requests</div>
        <div class="stat-tile"><b><?= Fmt::num($totals->api) ?></b></div>
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

    <?php if ($bytes->isEmpty()): ?>
        <div class="tchart-empty" style="--tchart-h: 240px">За этот период трафика не было</div>
    <?php else: ?>
        <div class="tchart-wrap" style="--tchart-h: 240px">
            <div class="tchart-axis">
                <?php foreach ($bytes->grid as $line): ?><span><?= Fmt::e($line) ?></span><?php endforeach; ?>
                <span>0</span>
            </div>
            <div class="tchart" data-tchart
                 data-a-label="<?= Fmt::e($bytes->topLabel) ?>" data-a-color="var(--brand)"
                 data-b-label="<?= Fmt::e($bytes->bottomLabel) ?>" data-b-color="var(--chart-4)">
                <?php foreach ($bytes->columns as $col): ?>
                    <div class="tchart__col" data-title="<?= Fmt::e($col->dayTitle) ?>"
                         data-a-value="<?= Fmt::e($col->topValue) ?>" data-b-value="<?= Fmt::e($col->bottomValue) ?>">
                        <div class="tchart__stack">
                            <?php if ($col->isEmpty): ?>
                                <div class="tchart__zero"></div>
                            <?php else: ?>
                                <?php if ($col->bottomPercent > 0): ?>
                                    <div class="tchart__bar tchart__bar--in tchart__bar--set" style="height: <?= $col->bottomPercent ?>%"></div>
                                <?php endif; ?>
                                <?php if ($col->topPercent > 0): ?>
                                    <div class="tchart__bar tchart__bar--out tchart__bar--set" style="height: <?= $col->topPercent ?>%"></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <span class="tchart__label"><?= Fmt::e($col->label) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="legend mt-2">
        <span class="legend__item"><span class="legend__swatch" style="background: var(--brand)"></span>
            <?= Fmt::e($bytes->topLabel) ?> <span class="legend__hint">· <?= Fmt::e($bytes->topHint) ?></span></span>
        <span class="legend__item"><span class="legend__swatch" style="background: var(--chart-4)"></span>
            <?= Fmt::e($bytes->bottomLabel) ?> <span class="legend__hint">· <?= Fmt::e($bytes->bottomHint) ?></span></span>
        <?php if (!$bytes->isEmpty()): ?>
            <span class="legend__item legend__hint">пик за сутки — <?= Fmt::e($bytes->peakLabel()) ?></span>
            <span class="legend__item legend__hint">в среднем на запрос — <?= Fmt::bytes($totals->averageServed()) ?></span>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card__header">
        <div class="card__title">Запросы по суткам</div>
        <div class="card__spacer"></div>
        <span class="text-sm text-muted" title="Запрос засчитывается, даже если тело не пошло: 304, HEAD и отвергнутый диапазон — это тоже запросы">
            <svg class="icon"><use href="#i-info"/></svg> считаются и ответы без тела
        </span>
    </div>

    <?php if ($hits->isEmpty()): ?>
        <div class="tchart-empty" style="--tchart-h: 170px">За этот период запросов не было</div>
    <?php else: ?>
        <div class="tchart-wrap" style="--tchart-h: 170px">
            <div class="tchart-axis">
                <?php foreach ($hits->grid as $line): ?><span><?= Fmt::e($line) ?></span><?php endforeach; ?>
                <span>0</span>
            </div>
            <div class="tchart" data-tchart
                 data-a-label="<?= Fmt::e($hits->topLabel) ?>" data-a-color="var(--brand)"
                 data-b-label="<?= Fmt::e($hits->bottomLabel) ?>" data-b-color="var(--chart-4)">
                <?php foreach ($hits->columns as $col): ?>
                    <div class="tchart__col" data-title="<?= Fmt::e($col->dayTitle) ?>"
                         data-a-value="<?= Fmt::e($col->topValue) ?>" data-b-value="<?= Fmt::e($col->bottomValue) ?>">
                        <div class="tchart__stack">
                            <?php if ($col->isEmpty): ?>
                                <div class="tchart__zero"></div>
                            <?php else: ?>
                                <?php if ($col->bottomPercent > 0): ?>
                                    <div class="tchart__bar tchart__bar--in tchart__bar--set" style="height: <?= $col->bottomPercent ?>%"></div>
                                <?php endif; ?>
                                <?php if ($col->topPercent > 0): ?>
                                    <div class="tchart__bar tchart__bar--out tchart__bar--set" style="height: <?= $col->topPercent ?>%"></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <span class="tchart__label"><?= Fmt::e($col->label) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="legend mt-2">
        <span class="legend__item"><span class="legend__swatch" style="background: var(--brand)"></span>
            <?= Fmt::e($hits->topLabel) ?> <span class="legend__hint">· <?= Fmt::e($hits->topHint) ?></span></span>
        <span class="legend__item"><span class="legend__swatch" style="background: var(--chart-4)"></span>
            <?= Fmt::e($hits->bottomLabel) ?> <span class="legend__hint">· <?= Fmt::e($hits->bottomHint) ?></span></span>
    </div>
</div>
