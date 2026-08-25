<?php

use Main\Web\Fmt;
use Main\Web\Sorting;
use Main\Web\Ui;

/** @var \Main\Request\BucketListRequest $query */
$sorting = new Sorting(
    ['created' => 'по дате создания', 'name' => 'по имени', 'used' => 'по занятому месту'],
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
            <span class="stat__label">Активные</span>
            <span class="stat__note">всего <span data-counter="buckets-total"><?= Fmt::num($counts->total) ?></span></span>
        </span>
    </div>

    <div class="card stat stat--brand">
        <span class="stat__icon"><svg class="icon"><use href="#i-layers"/></svg></span>
        <span class="stat__value"><?= Fmt::bytes($counts->used) ?></span>
        <span class="stat__body">
            <span class="stat__label">Занято</span>
            <span class="stat__note">из <?= Fmt::bytes($counts->quota) ?></span>
        </span>
    </div>

    <div class="card stat stat--warn">
        <span class="stat__icon"><svg class="icon"><use href="#i-alert-triangle"/></svg></span>
        <span class="stat__value<?= $counts->full === 0 ? ' is-zero' : '' ?>"><?= Fmt::num($counts->full) ?></span>
        <span class="stat__body">
            <span class="stat__label">Заполнены</span>
            <span class="stat__note">90% квоты и выше</span>
        </span>
    </div>

    <div class="card stat stat--mute">
        <span class="stat__icon"><svg class="icon"><use href="#i-clock"/></svg></span>
        <span class="stat__value<?= $counts->pending === 0 ? ' is-zero' : '' ?>" data-counter="buckets-pending"><?= Fmt::num($counts->pending) ?></span>
        <span class="stat__body">
            <span class="stat__label">Не готовы</span>
            <span class="stat__note">создаются или удаляются</span>
        </span>
    </div>
</div>

<div class="card panel panel--buckets">
    <div class="card__header">
        <div>
            <h2 class="card__title">Бакеты</h2>
            <div class="card__subtitle">
                <?= $page->meta->total ?> <?= Fmt::plural($page->meta->total, 'бакет', 'бакета', 'бакетов') ?>
                <?= $searching ? 'по запросу' : 'в хранилище' ?>
            </div>
        </div>
        <div class="card__spacer"></div>

        <div class="panel__tools">
            <?= Ui::search('/admin/ui/buckets', 'Имя или описание', $query->search, ['sort' => $query->sort, 'dir' => $query->dir]) ?>
            <?php if ($searching): ?>
                <?= Ui::searchReset($query->url(['search' => null, 'page' => 1])) ?>
            <?php endif ?>
            <?= $sorting->menu('only-md') ?>
            <button type="button" class="btn btn--primary btn--sm" data-modal-open="modal-bucket">
                <svg class="icon icon--sm"><use href="#i-plus"/></svg> Новый бакет
            </button>
        </div>
    </div>

    <div class="panel__scroll">
        <table class="table table--cards">
            <thead>
            <tr>
                <?= $sorting->th('name', 'Бакет') ?>
                <?= $sorting->th('', 'Статус') ?>
                <?= $sorting->th('used', 'Квота', 'col-quota') ?>
                <?= $sorting->th('', 'Файлы / блобы', 'num nowrap') ?>
                <?= $sorting->th('', 'Кэш') ?>
                <?= $sorting->th('created', 'Создан') ?>
                <th><span class="visually-hidden">Действия</span></th>
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
                    <td data-label="Статус" data-half data-cell="status">
                        <span class="tone tone--<?= $bucket->statusTone() ?>">
                            <span class="status-dot status-dot--current"></span>
                            <?= Fmt::e($bucket->status) ?>
                        </span>
                    </td>
                    <td data-label="Квота" class="col-quota">
                        <div class="quota <?= $bucket->quotaState() ?>">
                            <div class="quota__bar"><div class="quota__fill" style="width: <?= $bucket->percent() ?>%"></div></div>
                            <div class="quota__meta">
                                <span><b><?= Fmt::bytes($bucket->used) ?></b> из <?= Fmt::bytes($bucket->quota) ?></span>
                                <span><?= round($bucket->percent()) ?>%</span>
                            </div>
                        </div>
                    </td>
                    <td data-label="Файлы / блобы" data-half class="num nowrap">
                        <?= Fmt::num($bucket->files) ?>
                        <span class="text-muted">/ <?= Fmt::num($bucket->blobs) ?></span>
                    </td>
                    <td data-label="Кэш">
                        <span class="tone tone--<?= $bucket->cacheVisibility->tone() ?>">
                            <?= Fmt::e($bucket->cacheVisibility->label()) ?>
                            · <?= $bucket->cacheMaxAge === null ? 'по умолчанию' : $bucket->cacheMaxAge . 's' ?>
                        </span>
                    </td>
                    <td data-label="Создан" class="text-muted text-sm nowrap"><?= Fmt::date($bucket->createdAt) ?></td>
                    <td data-actions>
                        <div class="dropdown">
                            <?= Ui::rowMenu() ?>
                            <div class="dropdown__menu" role="menu">
                                <a class="dropdown__item" href="/admin/ui/buckets/<?= Fmt::e($bucket->id) ?>">
                                    Открыть <svg class="icon"><use href="#i-arrow-right"/></svg>
                                </a>
                                <button type="button" class="dropdown__item" data-action="bucket:edit"
                                        data-id="<?= Fmt::e($bucket->id) ?>"
                                        data-name="<?= Fmt::e($bucket->name) ?>"
                                        data-description="<?= Fmt::e($bucket->description) ?>"
                                        data-quota="<?= $bucket->quota ?>">
                                    Изменить <svg class="icon"><use href="#i-edit"/></svg>
                                </button>
                                <button type="button" class="dropdown__item" data-action="bucket:cache"
                                        data-id="<?= Fmt::e($bucket->id) ?>"
                                        data-max-age="<?= $bucket->cacheMaxAge ?? '' ?>"
                                        data-visibility="<?= Fmt::e($bucket->cacheVisibility->name) ?>">
                                    Политика кэша <svg class="icon"><use href="#i-clock"/></svg>
                                </button>
                                <button type="button" class="dropdown__item" data-action="bucket:delete"
                                        data-id="<?= Fmt::e($bucket->id) ?>" data-name="<?= Fmt::e($bucket->name) ?>">
                                    Удалить <svg class="icon"><use href="#i-trash"/></svg>
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
            <div class="empty__title">Ничего не нашлось</div>
            <div class="text-sm">По запросу «<?= Fmt::e($query->search) ?>» бакетов нет</div>
        <?php else: ?>
            <div class="empty__title">Бакетов пока нет</div>
            <div class="text-sm">Создайте первый</div>
        <?php endif ?>
    </div>

    <?php wrImport('admin/_pagination') ?>
</div>
