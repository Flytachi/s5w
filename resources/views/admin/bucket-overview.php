<?php

use Main\Dto\ExtUsage;
use Main\Web\Fmt;
use Main\Web\TrafficChart;
use Main\Web\Ui;

$base = '/admin/ui/buckets/' . $bucket->id;

$kinds = array_map(static fn(ExtUsage $row) => [$row->ext, $row->bytes, $row->total], $usage);
$extLabel = static fn(string $name): string => $name === '' ? 'no extension' : '.' . $name;

$where = [
    ['overview.where.root', 'Root', 1, $placement->root, null],
    ['overview.where.folders', 'Folders', 3, $placement->in_folders, $folderCounts->total - $folderCounts->temp],
    ['overview.where.temp', 'Temporary folders', 4, $placement->in_temp, $folderCounts->temp],
];

$kindsTotal = array_sum(array_column($kinds, 1));
$dedup = max(0, $bucket->files - $bucket->blobs);

$circle = 2 * M_PI * 54;
$offset = 0;

$chart = TrafficChart::of($series);
?>

<div class="grid grid--3 mb-3">
    <div class="card card--brand">
        <div class="card__header">
            <div class="card__title" data-i18n="dash.space">Space</div>
            <div class="card__spacer"></div>
            <span class="tone"><?= round($bucket->percent()) ?>%</span>
        </div>

        <div class="usage">
            <div class="usage__part">
                <span class="usage__label" data-i18n="dash.used">Used</span>
                <span class="usage__value" data-fmt="bytes" data-value="<?= $bucket->used ?>"><?= Fmt::bytes($bucket->used) ?></span>
            </div>
            <div class="usage__sep"></div>
            <div class="usage__part">
                <span class="usage__label" data-i18n="dash.free">Free</span>
                <span class="usage__value usage__value--free" data-fmt="bytes" data-value="<?= $bucket->free ?>"><?= Fmt::bytes($bucket->free) ?></span>
            </div>
        </div>

        <div class="quota <?= $bucket->quotaState() ?> mt-2">
            <div class="quota__bar"><div class="quota__fill" style="width: <?= $bucket->percent() ?>%"></div></div>
            <div class="quota__meta">
                <span><span data-i18n="table.quota">Quota</span> <span data-fmt="bytes" data-value="<?= $bucket->quota ?>"><?= Fmt::bytes($bucket->quota) ?></span></span>
                <span data-i18n-plural="files.count" data-count="<?= $bucket->files ?>"><?= $bucket->files ?> files</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <div class="card__title" data-i18n="table.bucket">Bucket</div>
            <div class="card__spacer"></div>
            <button type="button" class="btn btn--ghost btn--sm"
                    data-action="bucket:edit" data-id="<?= Fmt::e($bucket->id) ?>"
                    data-name="<?= Fmt::e($bucket->name) ?>" data-description="<?= Fmt::e($bucket->description) ?>"
                    data-quota="<?= $bucket->quota ?>">
                <svg class="icon icon--sm"><use href="#i-edit"/></svg> <span data-i18n="common.edit">Edit</span>
            </button>
        </div>

        <dl class="kv">
            <dt data-i18n="table.status">Status</dt>
            <dd><span class="tone tone--<?= $bucket->statusTone() ?>" data-i18n="status.<?= Fmt::e($bucket->status) ?>"><?= Fmt::e($bucket->status) ?></span></dd>
            <dt data-i18n="table.created">Created</dt><dd><?= Fmt::date($bucket->createdAt) ?></dd>
            <dt data-i18n="overview.description">Description</dt>
            <dd><?= $bucket->description === '' ? '<span class="text-muted" data-i18n="overview.noDescription">none</span>' : Fmt::e($bucket->description) ?></dd>
        </dl>

        <div class="text-sm text-muted mt-3"><span data-i18n="overview.idIn">Identifier in</span> <span class="mono">/o</span> <span data-i18n="overview.idAddresses">addresses</span></div>
        <div class="row mt-1">
            <button type="button" class="copyable copyable--wide mono" data-copy="<?= Fmt::e($bucket->id) ?>"
                    data-i18n-attr="aria-label:overview.copyId" aria-label="Copy identifier">
                <?= Fmt::e($bucket->id) ?>
                <svg class="icon"><use href="#i-copy"/></svg>
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <div class="card__title" data-i18n="overview.defaultCache">Default cache</div>
            <div class="card__spacer"></div>
            <button type="button" class="btn btn--ghost btn--sm"
                    data-action="bucket:cache" data-id="<?= Fmt::e($bucket->id) ?>"
                    data-max-age="<?= $bucket->cacheMaxAge ?? '' ?>"
                    data-visibility="<?= Fmt::e($bucket->cacheVisibility->name) ?>">
                <svg class="icon icon--sm"><use href="#i-edit"/></svg> <span data-i18n="common.edit">Edit</span>
            </button>
        </div>

        <dl class="kv">
            <dt data-i18n="overview.visibility">Visibility</dt>
            <dd><span class="tone tone--<?= $bucket->cacheVisibility->tone() ?>" data-i18n="<?= Fmt::e($bucket->cacheVisibility->labelKey()) ?>">
                <?= Fmt::e($bucket->cacheVisibility->label()) ?>
            </span></dd>
            <dt>max-age</dt>
            <dd><?php if ($bucket->cacheMaxAge === null): ?>
                <span data-i18n="overview.maxAgeDefault" data-i18n-vars="<?= Fmt::e((string) json_encode(['n' => $bucket->cacheVisibility->defaultMaxAge()])) ?>"><?= $bucket->cacheVisibility->defaultMaxAge() ?> s — default</span>
            <?php else: ?>
                <?= $bucket->cacheMaxAge ?> s
            <?php endif ?></dd>
        </dl>

        <div class="text-sm text-muted mt-3"><span data-i18n="overview.cacheNoteA">Applies to</span> <span class="mono">/o</span><span data-i18n="overview.cacheNoteB">; on</span> <span class="mono">/p</span>
            <span data-i18n="overview.cacheNoteC">and</span> <span class="mono">/t</span> <span data-i18n="overview.cacheNoteD">it is served as</span> <span class="mono">private</span>.</div>
    </div>
</div>

<div class="card mb-3">
    <div class="card__header">
        <div class="card__title"><span data-i18n="overview.spendFor">Spend for</span>
            <span data-fmt="month" data-value="<?= date('Y-m') ?>"><?= date('F') ?></span></div>
        <div class="card__spacer"></div>
        <a class="btn btn--ghost btn--sm" href="<?= $base ?>/stats"><span data-i18n="overview.forPeriod">by period</span> <svg class="icon icon--sm"><use href="#i-arrow-right"/></svg></a>
    </div>

    <?= Ui::chart($chart, 220, 'overview.emptyMonth', 'This month the bucket has neither served nor received anything') ?>

    <div class="legend mt-2">
        <span class="legend__item"><span class="legend__swatch legend__swatch--out"></span>
            Egress <span class="legend__hint">· <span data-i18n="chart.hint.egress">outbound traffic</span></span> <b data-fmt="bytes" data-value="<?= $totals->egress ?>"><?= Fmt::bytes($totals->egress) ?></b></span>
        <span class="legend__item"><span class="legend__swatch legend__swatch--in"></span>
            Ingress <span class="legend__hint">· <span data-i18n="chart.hint.ingress">inbound traffic</span></span> <b data-fmt="bytes" data-value="<?= $totals->ingress ?>"><?= Fmt::bytes($totals->ingress) ?></b></span>
        <span class="legend__item legend__hint" data-i18n-plural="overview.deliveryRequests" data-count="<?= $totals->deliveries ?>"><?= $totals->deliveries ?> delivery requests</span>
    </div>
</div>

<div class="grid grid--overview mb-3">
    <div class="card">
        <div class="card__header">
            <div>
                <div class="card__title" data-i18n="overview.whatTakesSpace">What takes the space</div>
                <div class="card__subtitle" data-i18n="overview.byExtension">by extension</div>
            </div>
        </div>

        <?php if ($kinds === []): ?>
            <div class="empty-inline">
                <svg class="icon"><use href="#i-file"/></svg>
                <div>
                    <div class="empty-inline__title" data-i18n="files.empty.title">Empty</div>
                    <div class="text-sm text-muted" data-i18n="files.empty.hint">Upload the first file</div>
                </div>
            </div>
        <?php else: ?>
        <div class="ring-row">
            <div class="ring-box">
                <svg class="ring" viewBox="0 0 140 140" data-donut role="img"
                     data-i18n-attr="aria-label:overview.donutAria" aria-label="Share of extensions on disk">
                    <circle class="ring__track" cx="70" cy="70" r="54"></circle>
                    <?php foreach ($kinds as $i => [$name, $size, $count]): ?>
                        <?php
                        $share = $kindsTotal > 0 ? $size / $kindsTotal : 0;
                        $length = $circle * $share;
                        ?>
                        <circle class="ring__slice" style="stroke: var(--chart-<?= $i % 8 + 1 ?>)"
                                cx="70" cy="70" r="54" data-slice="<?= $i ?>"
                                data-name="<?= Fmt::e($extLabel($name)) ?>"
                                <?= $name === '' ? 'data-name-key="overview.noExtension"' : '' ?>
                                data-bytes="<?= $size ?>"
                                data-size="<?= Fmt::e(Fmt::bytes($size)) ?>"
                                data-count="<?= $count ?>"
                                data-share="<?= round($share * 100, 1) ?>"
                                stroke-dasharray="<?= round($length, 2) ?> <?= round($circle - $length, 2) ?>"
                                stroke-dashoffset="<?= round(-$offset, 2) ?>"></circle>
                        <?php $offset += $length ?>
                    <?php endforeach ?>
                    <text class="ring__total" x="70" y="67" data-fmt="bytes" data-value="<?= $kindsTotal ?>"><?= Fmt::bytes($kindsTotal) ?></text>
                    <text class="ring__cap" x="70" y="84" data-i18n="overview.onDisk">on disk</text>
                </svg>
            </div>

            <div class="kinds-scroll">
                <table class="kinds">
                    <?php foreach ($kinds as $i => [$name, $size, $count]): ?>
                        <tr data-slice="<?= $i ?>">
                            <td class="kinds__name">
                                <span class="kinds__dot" style="background: var(--chart-<?= $i % 8 + 1 ?>)"></span>
                                <span class="<?= $name === '' ? 'text-muted' : 'mono' ?>"<?= $name === '' ? ' data-i18n="overview.noExtension"' : '' ?>><?= Fmt::e($extLabel($name)) ?></span>
                                <span class="kinds__count" data-i18n="common.pcs" data-i18n-vars="<?= Fmt::e((string) json_encode(['n' => $count])) ?>"><?= Fmt::num($count) ?> pcs</span>
                            </td>
                            <td class="kinds__value" data-fmt="bytes" data-value="<?= $size ?>"><?= Fmt::bytes($size) ?></td>
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
            <div class="card__title" data-i18n="nav.files">Files</div>
            <div class="card__spacer"></div>
            <a class="btn btn--ghost btn--sm" href="<?= $base ?>/files" data-i18n="common.open">Open</a>
        </div>

        <div class="pair">
            <div class="pair__side">
                <span class="pair__value"><?= Fmt::num($bucket->files) ?></span>
                <span class="pair__label" data-i18n="overview.filesLabel">files</span>
            </div>
            <span class="pair__vs" data-i18n="overview.versus">versus</span>
            <div class="pair__side">
                <span class="pair__value"><?= Fmt::num($bucket->blobs) ?></span>
                <span class="pair__label" data-i18n="overview.blobsLabel">blobs</span>
            </div>
        </div>

        <div class="bar-mini mt-2">
            <div class="bar-mini__fill" style="width: <?= $bucket->files > 0 ? round($bucket->blobs / $bucket->files * 100) : 0 ?>%"></div>
        </div>
        <div class="text-sm text-muted mt-1">
            <span data-i18n="overview.dedupBefore">Duplicates folded:</span> <b><?= Fmt::num($dedup) ?></b>
            <span data-i18n="overview.dedupAfter">— the space is taken once.</span>
        </div>

        <div class="text-sm text-muted mt-3" data-i18n="overview.whereThey">Where they live</div>

        <div class="share-bar mt-1">
            <?php foreach ($where as [$labelKey, $label, $color, $count, $dirs]): ?>
                <span class="share-bar__part"
                      style="width: <?= $bucket->files > 0 ? round($count / $bucket->files * 100, 2) : 0 ?>%; background: var(--chart-<?= $color ?>)"></span>
            <?php endforeach ?>
        </div>

        <?php foreach ($where as [$labelKey, $label, $color, $count, $dirs]): ?>
            <div class="kv-row">
                <span>
                    <span class="kinds__dot" style="background: var(--chart-<?= $color ?>)"></span>
                    <span data-i18n="<?= Fmt::e($labelKey) ?>"><?= Fmt::e($label) ?></span>
                    <?php if ($dirs !== null): ?>
                        <span class="kinds__count" data-i18n="common.pcs" data-i18n-vars="<?= Fmt::e((string) json_encode(['n' => $dirs])) ?>"><?= Fmt::num($dirs) ?> pcs</span>
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
            <span class="stat__label" data-i18n="nav.tokens">Tokens</span>
            <span class="stat__note" data-i18n="token.state.active">active</span>
        </span>
    </a>

    <a class="card stat stat--danger access-tile" href="<?= $base ?>/tokens">
        <span class="stat__icon"><svg class="icon"><use href="#i-clock"/></svg></span>
        <span class="stat__value<?= $tokenCounts['expired'] === 0 ? ' is-zero' : '' ?>"><?= $tokenCounts['expired'] ?></span>
        <span class="stat__body">
            <span class="stat__label" data-i18n="nav.tokens">Tokens</span>
            <span class="stat__note" data-i18n="tokens.stat.expired">Expired</span>
        </span>
    </a>

    <a class="card stat stat--brand access-tile" href="<?= $base ?>/links">
        <span class="stat__icon"><svg class="icon"><use href="#i-link"/></svg></span>
        <span class="stat__value<?= $linkCounts['active'] === 0 ? ' is-zero' : '' ?>"><?= $linkCounts['active'] ?></span>
        <span class="stat__body">
            <span class="stat__label" data-i18n="nav.links">Links</span>
            <span class="stat__note" data-i18n="links.stat.active">Active</span>
        </span>
    </a>

    <a class="card stat stat--warn access-tile" href="<?= $base ?>/links">
        <span class="stat__icon"><svg class="icon"><use href="#i-clock"/></svg></span>
        <span class="stat__value<?= $linkCounts['expired'] === 0 ? ' is-zero' : '' ?>"><?= $linkCounts['expired'] ?></span>
        <span class="stat__body">
            <span class="stat__label" data-i18n="nav.links">Links</span>
            <span class="stat__note" data-i18n="links.stat.expired">Expired</span>
        </span>
    </a>
</div>
