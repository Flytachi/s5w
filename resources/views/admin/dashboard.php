<?php

use Main\Dto\TrafficBucket;
use Main\Web\BucketView;
use Main\Web\Fmt;
use Main\Web\TrafficChart;
use Main\Web\Trend;
use Main\Web\Ui;

$chart = TrafficChart::of($series, 'bytes');

$egressTrend = Trend::of($series, static fn($day) => $day->egress);
$hitsTrend = Trend::of($series, static fn($day) => $day->deliveries);

[$usedPercent, $usedState] = Fmt::quotaState($counts->used, $counts->quota);
$free = max(0, $counts->quota - $counts->used);

$topEgress = $top === [] ? 0 : max(array_map(static fn(TrafficBucket $row) => $row->egress, $top));

$quietShown = array_slice($quiet, 0, 4);
$quietRest = count($quiet) - count($quietShown);

$days = $window . ' ' . Fmt::plural($window, 'день', 'дня', 'дней');

$delta = static function (Trend $trend): string {
    if (!$trend->known) {
        return '<span class="metric__delta metric__delta--flat">' . Fmt::e($trend->label()) . '</span>';
    }

    return '<span class="metric__delta metric__delta--' . ($trend->up() ? 'up' : 'down') . '">'
        . '<svg class="icon icon--sm"><use href="#i-trending"/></svg> ' . Fmt::e($trend->label()) . '</span>';
};
?>

<div class="dashtop mb-3">
    <div class="card card--brand">
        <div class="card__header">
            <div class="card__title">Место</div>
            <div class="card__spacer"></div>
            <span class="tone"><?= round($usedPercent) ?>% квоты</span>
        </div>

        <div class="usage">
            <div class="usage__part">
                <span class="usage__label">Занято</span>
                <span class="usage__value"><?= Fmt::bytes($counts->used) ?></span>
            </div>
            <div class="usage__sep"></div>
            <div class="usage__part">
                <span class="usage__label">Свободно</span>
                <span class="usage__value usage__value--free"><?= Fmt::bytes($free) ?></span>
            </div>
        </div>

        <div class="quota <?= $usedState ?>">
            <div class="quota__bar"><div class="quota__fill" style="width: <?= $usedPercent ?>%"></div></div>
            <div class="quota__meta">
                <span>выделено <?= Fmt::bytes($counts->quota) ?></span>
                <span><?= Fmt::num($counts->total) ?> <?= Fmt::plural($counts->total, 'бакет', 'бакета', 'бакетов') ?></span>
            </div>
        </div>

        <?php if ($counts->full > 0): ?>
            <div class="mt-2">
                <span class="tone">
                    <svg class="icon"><use href="#i-alert-triangle"/></svg>
                    <?= Fmt::num($counts->full) ?> <?= Fmt::plural($counts->full, 'бакет', 'бакета', 'бакетов') ?> у предела
                </span>
            </div>
        <?php endif; ?>

        <?php /* Экономия дедупликацией — про место, а не про трафик, и стоять ей
                 рядом с занятым, а не в ряду с egress. */ ?>
        <div class="text-sm mt-3">
            Дедупликация бережёт <b><?= Fmt::bytes($blobs->saved) ?></b>
            — <?= round($blobs->ratio()) ?>% от того, что легло бы без неё
        </div>
    </div>

    <div class="grid grid--2">
        <div class="card">
            <div class="metric">
                <span class="metric__label">Egress за <?= Fmt::e($days) ?></span>
                <span class="metric__value"><?= Fmt::bytes($totals->egress) ?></span>
                <?= $delta($egressTrend) ?>
            </div>
        </div>

        <div class="card">
            <div class="metric">
                <span class="metric__label">Запросов к раздаче за <?= Fmt::e($days) ?></span>
                <span class="metric__value"><?= Fmt::num($totals->deliveries) ?></span>
                <?= $delta($hitsTrend) ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card__header">
        <div>
            <div class="card__title">Трафик</div>
            <div class="card__subtitle">все бакеты, <?= Fmt::e($from) ?> — <?= Fmt::e($to) ?></div>
        </div>
    </div>

    <?= Ui::chart($chart, 240, 'За этот период трафика не было') ?>
    <?= Ui::legend($chart, $chart->isEmpty() ? [] : [
        'пик за сутки — ' . $chart->peakLabel(),
        'в среднем на отдачу — ' . Fmt::bytes($totals->averageServed()),
        'запросов с токеном — ' . Fmt::num($totals->api),
    ]) ?>
</div>

<div class="grid grid--2 mb-3">
    <div class="card card--flex">
        <div class="card__header">
            <div>
                <div class="card__title">Кто ест канал</div>
                <div class="card__subtitle">по исходящему за <?= Fmt::e($days) ?><?php if ($topTotal > count($top)): ?>,
                    первые <?= Fmt::num(count($top)) ?> из <?= Fmt::num($topTotal) ?><?php endif; ?></div>
            </div>
        </div>

        <?php if ($top === []): ?>
            <p class="text-sm text-muted">За этот период ни один бакет ничего не отдавал.</p>
        <?php else: ?>
            <div class="tbrank">
                <?php foreach ($top as $row): ?>
                    <a class="tbrank__row" href="/admin/ui/buckets/<?= Fmt::e($row->bucket_id) ?>/stats">
                        <span class="tbrank__name"><?= Fmt::e($row->name) ?></span>
                        <span class="tbrank__value"><?= Fmt::bytes($row->egress) ?></span>
                        <span class="tbrank__meter">
                            <i style="width: <?= $topEgress > 0 ? round($row->egress / $topEgress * 100, 2) : 0 ?>%"></i>
                        </span>
                        <span class="tbrank__meta">
                            <?= Fmt::num($row->deliveries) ?> <?= Fmt::plural($row->deliveries, 'отдача', 'отдачи', 'отдач') ?>
                            <span class="dot-sep"></span><?= Fmt::bytes($row->averageServed()) ?> на отдачу
                            <span class="dot-sep"></span>принято <?= Fmt::bytes($row->ingress) ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($quiet !== []): ?>
            <div class="text-sm text-muted quiet">
                Молчали: <?= Fmt::e(implode(', ', $quietShown)) ?><?php if ($quietRest > 0): ?>
                    и ещё <?= Fmt::num($quietRest) ?><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card__header">
            <div>
                <div class="card__title">Заполнение квоты</div>
                <div class="card__subtitle">самые полные
                    <?= Fmt::num(count($fill)) ?> из <?= Fmt::num($fillTotal) ?></div>
            </div>
            <div class="card__spacer"></div>
            <a class="btn btn--ghost btn--sm" href="/admin/ui/buckets">
                все <svg class="icon icon--sm"><use href="#i-arrow-right"/></svg>
            </a>
        </div>

        <?php if ($fill === []): ?>
            <p class="text-sm text-muted">Ни одному бакету не выделена квота.</p>
        <?php else: ?>
            <div class="tbrank">
                <?php foreach ($fill as $card): ?>
                    <a class="tbrank__row <?= $card->quotaState() ?>" href="/admin/ui/buckets/<?= Fmt::e($card->id) ?>">
                        <span class="tbrank__name"><?= Fmt::e($card->name) ?></span>
                        <span class="tbrank__value"><?= round($card->percent()) ?>%</span>
                        <span class="tbrank__meter"><i style="width: <?= $card->percent() ?>%"></i></span>
                        <span class="tbrank__meta">
                            <?= Fmt::bytes($card->used) ?> из <?= Fmt::bytes($card->quota) ?>
                            <span class="dot-sep"></span><?= Fmt::num($card->files) ?> <?= Fmt::plural($card->files, 'файл', 'файла', 'файлов') ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid--3">
    <div class="card">
        <div class="card__header">
            <div class="card__title">Токены</div>
            <div class="card__spacer"></div>
            <span class="tone tone--brand"><svg class="icon"><use href="#i-key"/></svg> <?= Fmt::num($tokenCounts['active']) ?></span>
        </div>
        <dl class="kv">
            <dt>Всего выпущено</dt><dd><?= Fmt::num($tokenCounts['total']) ?></dd>
            <dt>Действуют</dt><dd><?= Fmt::num($tokenCounts['active']) ?></dd>
            <dt>С полным доступом</dt><dd><?= Fmt::num($tokenCounts['full']) ?></dd>
            <dt>Истекли</dt>
            <dd><?= $tokenCounts['expired'] > 0
                ? '<span class="tone tone--warn">' . Fmt::num($tokenCounts['expired']) . '</span>'
                : '0' ?></dd>
            <dt>Отключены</dt><dd><?= Fmt::num($tokenCounts['inactive']) ?></dd>
        </dl>
    </div>

    <div class="card">
        <div class="card__header">
            <div class="card__title">Временные ссылки</div>
            <div class="card__spacer"></div>
            <span class="tone tone--temp"><svg class="icon"><use href="#i-link"/></svg> <?= Fmt::num($linkCounts['active']) ?></span>
        </div>
        <dl class="kv">
            <dt>Всего выпущено</dt><dd><?= Fmt::num($linkCounts['total']) ?></dd>
            <dt>Живы</dt><dd><?= Fmt::num($linkCounts['active']) ?></dd>
            <dt>Истекли</dt><dd><?= Fmt::num($linkCounts['expired']) ?></dd>
            <dt>Отозваны</dt><dd><?= Fmt::num($linkCounts['revoked']) ?></dd>
        </dl>
        <div class="text-sm text-muted mt-3">Погашенные ссылки убираются уборщиком, место они не занимают.</div>
    </div>

    <div class="card">
        <div class="card__header">
            <div class="card__title">Незавершённые загрузки</div>
            <div class="card__spacer"></div>
            <span class="tone <?= $uploadCounts->expired > 0 ? 'tone--warn' : 'tone--mute' ?>">
                <svg class="icon"><use href="#i-upload"/></svg> <?= Fmt::num($uploadCounts->total) ?>
            </span>
        </div>
        <dl class="kv">
            <dt>В работе</dt><dd><?= Fmt::num($uploadCounts->total - $uploadCounts->expired) ?></dd>
            <dt>Просрочены</dt><dd><?= Fmt::num($uploadCounts->expired) ?></dd>
            <dt>Занято в staging</dt><dd><?= Fmt::bytes($uploadCounts->staged) ?></dd>
        </dl>
        <div class="text-sm text-muted mt-3">Брошенный кусок держит место, но в квоту бакета не входит — до квоты
            он доедет только на завершении.</div>
    </div>
</div>
