<?php

use Main\Dto\ExtUsage;
use Main\Web\Fmt;
use Main\Web\TrafficChart;
use Main\Web\Ui;

$base = '/admin/ui/buckets/' . $bucket->id;

$kinds = array_map(static fn(ExtUsage $row) => [$row->ext, $row->bytes, $row->total], $usage);
$extLabel = static fn(string $name): string => $name === '' ? 'без расширения' : '.' . $name;

$where = [
    ['Корень', 1, $placement->root, null],
    ['Папки', 3, $placement->in_folders, $folderCounts->total - $folderCounts->temp],
    ['Временные папки', 4, $placement->in_temp, $folderCounts->temp],
];

$kindsTotal = array_sum(array_column($kinds, 1));
$dedup = max(0, $bucket->files - $bucket->blobs);

$circle = 2 * M_PI * 54;
$offset = 0;

$chart = TrafficChart::of($series);
$monthName = ['', 'январь', 'февраль', 'март', 'апрель', 'май', 'июнь',
    'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'][(int) date('n')];
?>

<div class="grid grid--3 mb-3">
    <div class="card card--brand">
        <div class="card__header">
            <div class="card__title">Место</div>
            <div class="card__spacer"></div>
            <span class="tone"><?= round($bucket->percent()) ?>%</span>
        </div>

        <div class="usage">
            <div class="usage__part">
                <span class="usage__label">Занято</span>
                <span class="usage__value"><?= Fmt::bytes($bucket->used) ?></span>
            </div>
            <div class="usage__sep"></div>
            <div class="usage__part">
                <span class="usage__label">Свободно</span>
                <span class="usage__value usage__value--free"><?= Fmt::bytes($bucket->free) ?></span>
            </div>
        </div>

        <div class="quota <?= $bucket->quotaState() ?> mt-2">
            <div class="quota__bar"><div class="quota__fill" style="width: <?= $bucket->percent() ?>%"></div></div>
            <div class="quota__meta">
                <span>квота <?= Fmt::bytes($bucket->quota) ?></span>
                <span><?= Fmt::num($bucket->files) ?> <?= Fmt::plural($bucket->files, 'файл', 'файла', 'файлов') ?></span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <div class="card__title">Бакет</div>
            <div class="card__spacer"></div>
            <button type="button" class="btn btn--ghost btn--sm"
                    data-action="bucket:edit" data-id="<?= Fmt::e($bucket->id) ?>"
                    data-name="<?= Fmt::e($bucket->name) ?>" data-description="<?= Fmt::e($bucket->description) ?>"
                    data-quota="<?= $bucket->quota ?>">
                <svg class="icon icon--sm"><use href="#i-edit"/></svg> Изменить
            </button>
        </div>

        <dl class="kv">
            <dt>Статус</dt>
            <dd><span class="tone tone--<?= $bucket->statusTone() ?>"><?= Fmt::e($bucket->status) ?></span></dd>
            <dt>Создан</dt><dd><?= Fmt::date($bucket->createdAt) ?></dd>
            <dt>Описание</dt>
            <dd><?= $bucket->description === '' ? '<span class="text-muted">нет</span>' : Fmt::e($bucket->description) ?></dd>
        </dl>

        <div class="text-sm text-muted mt-3">Идентификатор в адресах <span class="mono">/o</span></div>
        <div class="row mt-1">
            <button type="button" class="copyable copyable--wide mono" data-copy="<?= Fmt::e($bucket->id) ?>" aria-label="Копировать идентификатор">
                <?= Fmt::e($bucket->id) ?>
                <svg class="icon"><use href="#i-copy"/></svg>
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <div class="card__title">Кэш по умолчанию</div>
            <div class="card__spacer"></div>
            <button type="button" class="btn btn--ghost btn--sm"
                    data-action="bucket:cache" data-id="<?= Fmt::e($bucket->id) ?>"
                    data-max-age="<?= $bucket->cacheMaxAge ?? '' ?>"
                    data-visibility="<?= Fmt::e($bucket->cacheVisibility->name) ?>">
                <svg class="icon icon--sm"><use href="#i-edit"/></svg> Изменить
            </button>
        </div>

        <dl class="kv">
            <dt>Видимость</dt>
            <dd><span class="tone tone--<?= $bucket->cacheVisibility->tone() ?>">
                <?= Fmt::e($bucket->cacheVisibility->label()) ?>
            </span></dd>
            <dt>max-age</dt>
            <dd><?= $bucket->cacheMaxAge === null
                ? Fmt::e($bucket->cacheVisibility->defaultMaxAge() . ' с — по умолчанию')
                : $bucket->cacheMaxAge . ' с' ?></dd>
        </dl>

        <div class="text-sm text-muted mt-3">Действует на <span class="mono">/o</span>; на <span class="mono">/p</span>
            и <span class="mono">/t</span> отдаётся <span class="mono">private</span>.</div>
    </div>
</div>

<div class="card mb-3">
    <div class="card__header">
        <div class="card__title">Расход за <?= Fmt::e($monthName) ?></div>
        <div class="card__spacer"></div>
        <a class="btn btn--ghost btn--sm" href="<?= $base ?>/stats">за период <svg class="icon icon--sm"><use href="#i-arrow-right"/></svg></a>
    </div>

    <?= Ui::chart($chart, 220, 'В этом месяце бакет ещё ничего не отдавал и не принимал') ?>

    <div class="legend mt-2">
        <span class="legend__item"><span class="legend__swatch legend__swatch--out"></span>
            Egress <span class="legend__hint">· исходящий</span> <b><?= Fmt::bytes($totals->egress) ?></b></span>
        <span class="legend__item"><span class="legend__swatch legend__swatch--in"></span>
            Ingress <span class="legend__hint">· входящий</span> <b><?= Fmt::bytes($totals->ingress) ?></b></span>
        <span class="legend__item legend__hint"><?= Fmt::num($totals->deliveries) ?> <?= Fmt::plural($totals->deliveries, 'запрос', 'запроса', 'запросов') ?> к раздаче</span>
    </div>
</div>

<div class="grid grid--overview mb-3">
    <div class="card">
        <div class="card__header">
            <div>
                <div class="card__title">Что занимает место</div>
                <div class="card__subtitle">по расширениям</div>
            </div>
        </div>

        <?php if ($kinds === []): ?>
            <div class="empty-inline">
                <svg class="icon"><use href="#i-file"/></svg>
                <div>
                    <div class="empty-inline__title">Пусто</div>
                    <div class="text-sm text-muted">Загрузите первый файл.</div>
                </div>
            </div>
        <?php else: ?>
        <div class="ring-row">
            <div class="ring-box">
                <svg class="ring" viewBox="0 0 140 140" data-donut role="img" aria-label="Доля расширений на диске">
                    <circle class="ring__track" cx="70" cy="70" r="54"></circle>
                    <?php foreach ($kinds as $i => [$name, $size, $count]): ?>
                        <?php
                        $share = $kindsTotal > 0 ? $size / $kindsTotal : 0;
                        $length = $circle * $share;
                        ?>
                        <circle class="ring__slice" style="stroke: var(--chart-<?= $i % 8 + 1 ?>)"
                                cx="70" cy="70" r="54" data-slice="<?= $i ?>"
                                data-name="<?= Fmt::e($extLabel($name)) ?>"
                                data-size="<?= Fmt::e(Fmt::bytes($size)) ?>"
                                data-count="<?= $count ?>"
                                data-share="<?= round($share * 100, 1) ?>"
                                stroke-dasharray="<?= round($length, 2) ?> <?= round($circle - $length, 2) ?>"
                                stroke-dashoffset="<?= round(-$offset, 2) ?>"></circle>
                        <?php $offset += $length ?>
                    <?php endforeach ?>
                    <text class="ring__total" x="70" y="67"><?= Fmt::bytes($kindsTotal) ?></text>
                    <text class="ring__cap" x="70" y="84">на диске</text>
                </svg>
            </div>

            <div class="kinds-scroll">
                <table class="kinds">
                    <?php foreach ($kinds as $i => [$name, $size, $count]): ?>
                        <tr data-slice="<?= $i ?>">
                            <td class="kinds__name">
                                <span class="kinds__dot" style="background: var(--chart-<?= $i % 8 + 1 ?>)"></span>
                                <span class="<?= $name === '' ? 'text-muted' : 'mono' ?>"><?= Fmt::e($extLabel($name)) ?></span>
                                <span class="kinds__count"><?= Fmt::num($count) ?> шт</span>
                            </td>
                            <td class="kinds__value"><?= Fmt::bytes($size) ?></td>
                            <td class="kinds__share"><?= $kindsTotal > 0 ? round($size / $kindsTotal * 100) : 0 ?>%</td>
                        </tr>
                    <?php endforeach ?>
                </table>
            </div>
        </div>
        <?php endif ?>
    </div>

    <div class="card">
        <div class="card__header">
            <div class="card__title">Файлы</div>
            <div class="card__spacer"></div>
            <a class="btn btn--ghost btn--sm" href="<?= $base ?>/files">Открыть</a>
        </div>

        <div class="pair">
            <div class="pair__side">
                <span class="pair__value"><?= Fmt::num($bucket->files) ?></span>
                <span class="pair__label">файлов</span>
            </div>
            <span class="pair__vs">против</span>
            <div class="pair__side">
                <span class="pair__value"><?= Fmt::num($bucket->blobs) ?></span>
                <span class="pair__label">блобов</span>
            </div>
        </div>

        <div class="bar-mini mt-2">
            <div class="bar-mini__fill" style="width: <?= $bucket->files > 0 ? round($bucket->blobs / $bucket->files * 100) : 0 ?>%"></div>
        </div>
        <div class="text-sm text-muted mt-1">
            Дублей свёрнуто: <b><?= Fmt::num($dedup) ?></b> — место занято один раз.
        </div>

        <div class="text-sm text-muted mt-3">Где лежат</div>

        <div class="share-bar mt-1">
            <?php foreach ($where as [$label, $color, $count, $dirs]): ?>
                <span class="share-bar__part"
                      style="width: <?= $bucket->files > 0 ? round($count / $bucket->files * 100, 2) : 0 ?>%; background: var(--chart-<?= $color ?>)"></span>
            <?php endforeach ?>
        </div>

        <?php foreach ($where as [$label, $color, $count, $dirs]): ?>
            <div class="kv-row">
                <span>
                    <span class="kinds__dot" style="background: var(--chart-<?= $color ?>)"></span>
                    <?= Fmt::e($label) ?>
                    <?php if ($dirs !== null): ?>
                        <span class="kinds__count"><?= Fmt::num($dirs) ?> шт</span>
                    <?php endif ?>
                </span>
                <b><?= Fmt::num($count) ?></b>
            </div>
        <?php endforeach ?>
    </div>
</div>

<div class="grid grid--4 metrics-row">
    <a class="card stat stat--ok access-tile" href="<?= $base ?>/tokens">
        <span class="stat__icon"><svg class="icon"><use href="#i-key"/></svg></span>
        <span class="stat__value<?= $tokenCounts['active'] === 0 ? ' is-zero' : '' ?>"><?= $tokenCounts['active'] ?></span>
        <span class="stat__body">
            <span class="stat__label">Токены</span>
            <span class="stat__note">активны</span>
        </span>
    </a>

    <a class="card stat stat--danger access-tile" href="<?= $base ?>/tokens">
        <span class="stat__icon"><svg class="icon"><use href="#i-clock"/></svg></span>
        <span class="stat__value<?= $tokenCounts['expired'] === 0 ? ' is-zero' : '' ?>"><?= $tokenCounts['expired'] ?></span>
        <span class="stat__body">
            <span class="stat__label">Токены</span>
            <span class="stat__note">просрочены</span>
        </span>
    </a>

    <a class="card stat stat--brand access-tile" href="<?= $base ?>/links">
        <span class="stat__icon"><svg class="icon"><use href="#i-link"/></svg></span>
        <span class="stat__value<?= $linkCounts['active'] === 0 ? ' is-zero' : '' ?>"><?= $linkCounts['active'] ?></span>
        <span class="stat__body">
            <span class="stat__label">Ссылки</span>
            <span class="stat__note">активные</span>
        </span>
    </a>

    <a class="card stat stat--warn access-tile" href="<?= $base ?>/links">
        <span class="stat__icon"><svg class="icon"><use href="#i-clock"/></svg></span>
        <span class="stat__value<?= $linkCounts['expired'] === 0 ? ' is-zero' : '' ?>"><?= $linkCounts['expired'] ?></span>
        <span class="stat__body">
            <span class="stat__label">Ссылки</span>
            <span class="stat__note">истекли</span>
        </span>
    </a>
</div>
