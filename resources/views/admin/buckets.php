<?php

use Main\Web\Fmt;
use Main\Web\Sorting;
use Main\Web\Ui;

/** @var \Main\Request\BucketListRequest $query */
$sorting = new Sorting(
    [
        'created' => ['sort.by.created', 'by creation date'],
        'name' => ['sort.by.name', 'by name'],
        'used' => ['sort.by.used', 'by space used'],
    ],
    $query->sort,
    $query->dir === 'desc',
    static fn(array $override) => $query->url($override),
);

$searching = ($query->search ?? '') !== '';
?>

<div class="grid grid--4 metrics-row mb-3">
    <div class="card stat stat--ok">
        <span class="stat__icon"><svg class="icon"><use href="#i-database"/></svg></span>
        <span class="stat__value<?= $counts->active === 0 ? ' is-zero' : '' ?>" data-counter="buckets-active"><?= Fmt::num($counts->active) ?></span>
        <span class="stat__body">
            <span class="stat__label" data-i18n="buckets.stat.active">Active</span>
            <span class="stat__note"><span data-i18n="common.total">total</span> <span data-counter="buckets-total"><?= Fmt::num($counts->total) ?></span></span>
        </span>
    </div>

    <div class="card stat stat--brand">
        <span class="stat__icon"><svg class="icon"><use href="#i-layers"/></svg></span>
        <span class="stat__value" data-fmt="bytes" data-value="<?= $counts->used ?>"><?= Fmt::bytes($counts->used) ?></span>
        <span class="stat__body">
            <span class="stat__label" data-i18n="buckets.stat.used">Used</span>
            <span class="stat__note"><span data-i18n="quota.of">of</span> <span data-fmt="bytes" data-value="<?= $counts->quota ?>"><?= Fmt::bytes($counts->quota) ?></span></span>
        </span>
    </div>

    <div class="card stat stat--warn">
        <span class="stat__icon"><svg class="icon"><use href="#i-alert-triangle"/></svg></span>
        <span class="stat__value<?= $counts->full === 0 ? ' is-zero' : '' ?>"><?= Fmt::num($counts->full) ?></span>
        <span class="stat__body">
            <span class="stat__label" data-i18n="buckets.stat.full">Full</span>
            <span class="stat__note" data-i18n="buckets.stat.fullNote">90% of quota and above</span>
        </span>
    </div>

    <div class="card stat stat--mute">
        <span class="stat__icon"><svg class="icon"><use href="#i-clock"/></svg></span>
        <span class="stat__value<?= $counts->pending === 0 ? ' is-zero' : '' ?>" data-counter="buckets-pending"><?= Fmt::num($counts->pending) ?></span>
        <span class="stat__body">
            <span class="stat__label" data-i18n="buckets.stat.pending">Not ready</span>
            <span class="stat__note" data-i18n="buckets.stat.pendingNote">being created or deleted</span>
        </span>
    </div>
</div>

<div class="card panel panel--buckets">
    <div class="card__header">
        <div>
            <h2 class="card__title" data-i18n="page.buckets.title">Buckets</h2>
            <div class="card__subtitle">
                <span data-i18n-plural="buckets.count" data-count="<?= $page->meta->total ?>"><?= $page->meta->total ?> buckets</span>
                <?php if ($searching): ?>
                    <span data-i18n="buckets.inSearch">matching the search</span>
                <?php else: ?>
                    <span data-i18n="buckets.inStorage">in the storage</span>
                <?php endif ?>
            </div>
        </div>
        <div class="card__spacer"></div>

        <div class="panel__tools">
            <?= Ui::search('/admin/ui/buckets', 'buckets.searchPlaceholder', 'Name or description', $query->search, ['sort' => $query->sort, 'dir' => $query->dir]) ?>
            <?php if ($searching): ?>
                <?= Ui::searchReset($query->url(['search' => null, 'page' => 1])) ?>
            <?php endif ?>
            <?= $sorting->menu('only-md') ?>
            <button type="button" class="btn btn--primary btn--sm" data-modal-open="modal-bucket">
                <svg class="icon icon--sm"><use href="#i-plus"/></svg> <span data-i18n="buckets.new">New bucket</span>
            </button>
        </div>
    </div>

    <div class="panel__scroll">
        <table class="table table--cards">
            <thead>
            <tr>
                <?= $sorting->th('name', 'table.bucket', 'Bucket') ?>
                <?= $sorting->th('', 'table.status', 'Status') ?>
                <?= $sorting->th('used', 'table.quota', 'Quota', 'col-quota') ?>
                <?= $sorting->th('', 'table.filesBlobs', 'Files / blobs', 'num nowrap') ?>
                <?= $sorting->th('', 'table.cache', 'Cache') ?>
                <?= $sorting->th('created', 'table.created', 'Created') ?>
                <th><span class="visually-hidden" data-i18n="common.actions">Actions</span></th>
            </tr>
            </thead>
            <tbody data-rows="buckets">
            <?php foreach ($page->data as $bucket): ?>
                <tr data-row="bucket" data-id="<?= Fmt::e($bucket->id) ?>" data-name="<?= Fmt::e($bucket->name) ?>">
                    <td data-primary>
                        <a class="fileline" href="/admin/ui/buckets/<?= Fmt::e($bucket->id) ?>">
                            <span class="ftype ftype--image"><svg class="icon"><use href="#i-database"/></svg></span>
                            <span class="fileline__body">
                                <span class="fileline__name" data-bucket-name><?= Fmt::e($bucket->name) ?></span>
                                <span class="fileline__meta"><?= Fmt::e($bucket->description) ?></span>
                            </span>
                        </a>
                    </td>
                    <td data-label="Status" data-i18n-attr="data-label:table.status" data-half data-cell="status">
                        <span class="tone tone--<?= $bucket->statusTone() ?>">
                            <span class="status-dot status-dot--current"></span>
                            <span data-i18n="status.<?= Fmt::e($bucket->status) ?>"><?= Fmt::e($bucket->status) ?></span>
                        </span>
                    </td>
                    <td data-label="Quota" data-i18n-attr="data-label:table.quota" class="col-quota">
                        <div class="quota <?= $bucket->quotaState() ?>">
                            <div class="quota__bar"><div class="quota__fill" style="width: <?= $bucket->percent() ?>%"></div></div>
                            <div class="quota__meta">
                                <span><b data-fmt="bytes" data-value="<?= $bucket->used ?>"><?= Fmt::bytes($bucket->used) ?></b> <span data-i18n="quota.of">of</span> <span data-fmt="bytes" data-value="<?= $bucket->quota ?>"><?= Fmt::bytes($bucket->quota) ?></span></span>
                                <span><?= round($bucket->percent()) ?>%</span>
                            </div>
                        </div>
                    </td>
                    <td data-label="Files / blobs" data-i18n-attr="data-label:table.filesBlobs" data-half class="num nowrap">
                        <?= Fmt::num($bucket->files) ?>
                        <span class="text-muted">/ <?= Fmt::num($bucket->blobs) ?></span>
                    </td>
                    <td data-label="Cache" data-i18n-attr="data-label:table.cache">
                        <span class="tone tone--<?= $bucket->cacheVisibility->tone() ?>">
                            <span data-i18n="<?= Fmt::e($bucket->cacheVisibility->labelKey()) ?>"><?= Fmt::e($bucket->cacheVisibility->label()) ?></span>
                            · <?php if ($bucket->cacheMaxAge === null): ?><span data-i18n="cache.default">default</span><?php else: ?><?= $bucket->cacheMaxAge ?>s<?php endif ?>
                        </span>
                    </td>
                    <td data-label="Created" data-i18n-attr="data-label:table.created" class="text-muted text-sm nowrap"><?= Fmt::date($bucket->createdAt) ?></td>
                    <td data-actions>
                        <div class="dropdown">
                            <?= Ui::rowMenu() ?>
                            <div class="dropdown__menu" role="menu">
                                <a class="dropdown__item" href="/admin/ui/buckets/<?= Fmt::e($bucket->id) ?>">
                                    <span data-i18n="common.open">Open</span> <svg class="icon"><use href="#i-arrow-right"/></svg>
                                </a>
                                <button type="button" class="dropdown__item" data-action="bucket:edit"
                                        data-id="<?= Fmt::e($bucket->id) ?>"
                                        data-name="<?= Fmt::e($bucket->name) ?>"
                                        data-description="<?= Fmt::e($bucket->description) ?>"
                                        data-quota="<?= $bucket->quota ?>">
                                    <span data-i18n="common.edit">Edit</span> <svg class="icon"><use href="#i-edit"/></svg>
                                </button>
                                <button type="button" class="dropdown__item" data-action="bucket:cache"
                                        data-id="<?= Fmt::e($bucket->id) ?>"
                                        data-max-age="<?= $bucket->cacheMaxAge ?? '' ?>"
                                        data-visibility="<?= Fmt::e($bucket->cacheVisibility->name) ?>">
                                    <span data-i18n="bucket.cachePolicy">Cache policy</span> <svg class="icon"><use href="#i-clock"/></svg>
                                </button>
                                <button type="button" class="dropdown__item" data-action="bucket:delete"
                                        data-id="<?= Fmt::e($bucket->id) ?>" data-name="<?= Fmt::e($bucket->name) ?>">
                                    <span data-i18n="common.delete">Delete</span> <svg class="icon"><use href="#i-trash"/></svg>
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <div class="empty" data-empty="buckets"<?= $page->data === [] ? '' : ' hidden' ?>>
        <svg class="icon"><use href="#i-database"/></svg>
        <?php if ($searching): ?>
            <div class="empty__title" data-i18n="empty.nothing">Nothing found</div>
            <div class="text-sm" data-i18n="buckets.empty.search" data-i18n-vars="<?= Fmt::e((string) json_encode(['query' => $query->search])) ?>">No buckets match “<?= Fmt::e($query->search) ?>”</div>
        <?php else: ?>
            <div class="empty__title" data-i18n="buckets.empty.title">No buckets yet</div>
            <div class="text-sm" data-i18n="buckets.empty.hint">Create the first one</div>
        <?php endif ?>
    </div>

    <?php wrImport('admin/_pagination') ?>
</div>
