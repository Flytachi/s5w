<?php

use Main\Web\Fmt;
use Main\Web\Sorting;
use Main\Web\Ui;

/** @var \Main\Request\LinkListRequest $query */
$base = '/admin/ui/buckets/' . $bucket->id . '/links';

$sorting = new Sorting(
    ['created' => 'по дате создания', 'file' => 'по файлу', 'mode' => 'по режиму', 'state' => 'по состоянию'],
    $query->sort,
    $query->dir === 'desc',
    static fn(array $override) => $query->url($bucket->id, $override),
);

$searching = ($query->search ?? '') !== '';
?>

<div class="grid grid--4 metrics-row mb-3">
    <div class="card stat stat--ok">
        <span class="stat__icon"><svg class="icon"><use href="#i-link"/></svg></span>
        <span class="stat__value<?= $counts['active'] === 0 ? ' is-zero' : '' ?>" data-counter="links-active"><?= Fmt::num($counts['active']) ?></span>
        <span class="stat__body">
            <span class="stat__label">Активные</span>
            <span class="stat__note">работают сейчас</span>
        </span>
    </div>

    <div class="card stat stat--brand">
        <span class="stat__icon"><svg class="icon"><use href="#i-layers"/></svg></span>
        <span class="stat__value"><?= Fmt::num($counts['total']) ?></span>
        <span class="stat__body">
            <span class="stat__label">Выпущено</span>
            <span class="stat__note">за всё время</span>
        </span>
    </div>

    <div class="card stat stat--danger">
        <span class="stat__icon"><svg class="icon"><use href="#i-x-circle"/></svg></span>
        <span class="stat__value<?= $counts['revoked'] === 0 ? ' is-zero' : '' ?>" data-counter="links-revoked"><?= Fmt::num($counts['revoked']) ?></span>
        <span class="stat__body">
            <span class="stat__label">Отозваны</span>
            <span class="stat__note">закрыты вручную</span>
        </span>
    </div>

    <div class="card stat stat--warn">
        <span class="stat__icon"><svg class="icon"><use href="#i-clock"/></svg></span>
        <span class="stat__value<?= $counts['expired'] === 0 ? ' is-zero' : '' ?>" data-counter="links-expired"><?= Fmt::num($counts['expired']) ?></span>
        <span class="stat__body">
            <span class="stat__label">Истекли</span>
            <span class="stat__note">срок истёк</span>
        </span>
    </div>
</div>

<div class="card panel panel--links">
    <div class="card__header">
        <div>
            <h2 class="card__title">Временные ссылки</h2>
            <div class="card__subtitle">канал <span class="mono">/t</span> — ссылки, которые можно отозвать</div>
        </div>
        <div class="card__spacer"></div>

        <div class="panel__tools">
            <?= Ui::search($base, 'Файл или пометка', $query->search, ['sort' => $query->sort, 'dir' => $query->dir]) ?>
            <?php if ($searching): ?>
                <?= Ui::searchReset($query->url($bucket->id, ['search' => null, 'page' => 1])) ?>
            <?php endif ?>
            <?= $sorting->menu('only-md') ?>

            <?php if ($counts['revoked'] > 0): ?>
                <button type="button" class="btn btn--danger-soft btn--sm" data-action="links:purge" data-state="revoked">
                    <svg class="icon icon--sm"><use href="#i-trash"/></svg>
                    Удалить отозванные <span class="badge badge--danger"><?= Fmt::num($counts['revoked']) ?></span>
                </button>
            <?php endif ?>

            <?php if ($counts['expired'] > 0): ?>
                <button type="button" class="btn btn--danger-soft btn--sm" data-action="links:purge" data-state="expired">
                    <svg class="icon icon--sm"><use href="#i-clock"/></svg>
                    Удалить истёкшие <span class="badge badge--danger"><?= Fmt::num($counts['expired']) ?></span>
                </button>
            <?php endif ?>

            <?php if ($counts['active'] > 0): ?>
                <button type="button" class="btn btn--ghost btn--sm" data-action="links:revoke-all">
                    <svg class="icon icon--sm"><use href="#i-x-circle"/></svg> Отозвать все
                </button>
            <?php endif ?>
        </div>
    </div>

    <div class="panel__scroll">
        <table class="table table--cards">
            <thead>
            <tr>
                <?= $sorting->th('file', 'Файл') ?>
                <?= $sorting->th('mode', 'Режим') ?>
                <?= $sorting->th('', 'Скачиваний') ?>
                <?= $sorting->th('state', 'Состояние') ?>
                <?= $sorting->th('created', 'Создана') ?>
                <?= $sorting->th('', 'Пометка') ?>
                <th><span class="visually-hidden">Действия</span></th>
            </tr>
            </thead>
            <tbody data-rows="links">
            <?php foreach ($links as $link): ?>
                <tr data-row="link" data-id="<?= $link->id ?>"<?= $link->isAlive() ? '' : ' data-dead="1" class="is-dimmed"' ?>>
                    <td data-primary>
                        <div class="fileline">
                            <span class="ftype ftype--video"><svg class="icon"><use href="#i-link"/></svg></span>
                            <span class="fileline__body">
                                <span class="fileline__name"><?= Fmt::e($link->fileName) ?></span>
                                <span class="fileline__meta mono"><?= Fmt::e($link->fileSlug) ?></span>
                            </span>
                        </div>
                    </td>
                    <td data-label="Режим" data-half>
                        <span class="tone tone--<?= $link->attachment ? 'brand' : 'mute' ?>">
                            <?= $link->attachment ? 'скачивание' : 'просмотр' ?>
                        </span>
                    </td>
                    <td data-label="Скачиваний" data-half>
                        <?php if ($link->maxDownloads === null): ?>
                            <span class="text-sm"><?= $link->downloads ?> <span class="text-muted">без лимита</span></span>
                        <?php else: ?>
                            <?php $rest = $link->maxDownloads - $link->downloads ?>
                            <span class="tone tone--<?= $rest <= 0 ? 'danger' : ($rest <= 1 ? 'warn' : 'mute') ?>">
                                <?= $link->downloads ?> / <?= $link->maxDownloads ?>
                            </span>
                        <?php endif ?>
                    </td>
                    <td data-label="Состояние" data-half class="text-sm nowrap" data-cell="expiry">
                        <?php if ($link->deadReason() !== null): ?>
                            <span class="tone tone--danger"><?= Fmt::e($link->deadReason()) ?></span>
                        <?php else: ?>
                            <?= Fmt::left($link->expiresAt) ?>
                        <?php endif ?>
                    </td>
                    <td data-label="Создана" data-half class="text-sm nowrap" title="<?= Fmt::e(Fmt::date($link->createdAt)) ?>">
                        <span class="text-muted"><?= Fmt::ago($link->createdAt) ?></span>
                    </td>
                    <td data-label="Пометка" class="text-sm text-muted break"<?= $link->note === '' ? ' data-empty-hidden' : '' ?>><?= Fmt::e($link->note) ?></td>
                    <td data-actions>
                        <?php if ($link->isAlive()): ?>
                            <div class="row-actions">
                                <button type="button" class="icon-btn icon-btn--ghost"
                                        data-copy="<?= Fmt::e($link->url) ?>" aria-label="Копировать адрес" title="Копировать адрес">
                                    <svg class="icon"><use href="#i-copy"/></svg>
                                </button>
                                <a class="icon-btn icon-btn--ghost" href="<?= Fmt::e($link->url) ?>"
                                   target="_blank" rel="noopener" aria-label="Открыть" title="Открыть в новой вкладке">
                                    <svg class="icon"><use href="#i-arrow-right"/></svg>
                                </a>
                                <button type="button" class="icon-btn icon-btn--ghost text-danger" aria-label="Отозвать" title="Отозвать"
                                        data-action="link:revoke" data-id="<?= $link->id ?>">
                                    <svg class="icon"><use href="#i-x-circle"/></svg>
                                </button>
                            </div>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>

        <div class="empty" data-empty="links"<?= $links === [] ? '' : ' hidden' ?>>
            <svg class="icon"><use href="#i-link"/></svg>
            <?php if ($searching): ?>
                <div class="empty__title">Ничего не нашлось</div>
                <div class="text-sm">По запросу «<?= Fmt::e($query->search) ?>» ссылок нет</div>
            <?php else: ?>
                <div class="empty__title">Ссылок нет</div>
                <div class="text-sm">Выпустите ссылку из карточки файла</div>
            <?php endif ?>
        </div>
    </div>

    <?php wrImport('admin/_pagination') ?>
</div>
