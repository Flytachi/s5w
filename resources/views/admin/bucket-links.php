<?php

use Main\Web\Fmt;
use Main\Web\Sorting;
use Main\Web\Ui;

/** @var \Main\Controllers\Requests\LinkListRequest $query */
$base = '/admin/ui/buckets/' . $bucket->id . '/links';

$sorting = new Sorting(
    [
        'created' => ['sort.by.created', 'by creation date'],
        'file' => ['sort.by.file', 'by file'],
        'mode' => ['sort.by.mode', 'by mode'],
        'state' => ['sort.by.state', 'by state'],
    ],
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
            <span class="stat__label" data-i18n="links.stat.active">Active</span>
            <span class="stat__note" data-i18n="links.stat.activeNote">working right now</span>
        </span>
    </div>

    <div class="card stat stat--brand">
        <span class="stat__icon"><svg class="icon"><use href="#i-layers"/></svg></span>
        <span class="stat__value"><?= Fmt::num($counts['total']) ?></span>
        <span class="stat__body">
            <span class="stat__label" data-i18n="links.stat.issued">Issued</span>
            <span class="stat__note" data-i18n="links.stat.issuedNote">all time</span>
        </span>
    </div>

    <div class="card stat stat--danger">
        <span class="stat__icon"><svg class="icon"><use href="#i-x-circle"/></svg></span>
        <span class="stat__value<?= $counts['revoked'] === 0 ? ' is-zero' : '' ?>" data-counter="links-revoked"><?= Fmt::num($counts['revoked']) ?></span>
        <span class="stat__body">
            <span class="stat__label" data-i18n="links.stat.revoked">Revoked</span>
            <span class="stat__note" data-i18n="links.stat.revokedNote">closed by hand</span>
        </span>
    </div>

    <div class="card stat stat--warn">
        <span class="stat__icon"><svg class="icon"><use href="#i-clock"/></svg></span>
        <span class="stat__value<?= $counts['expired'] === 0 ? ' is-zero' : '' ?>" data-counter="links-expired"><?= Fmt::num($counts['expired']) ?></span>
        <span class="stat__body">
            <span class="stat__label" data-i18n="links.stat.expired">Expired</span>
            <span class="stat__note" data-i18n="time.expired">expired</span>
        </span>
    </div>
</div>

<div class="card panel panel--links">
    <div class="card__header">
        <div>
            <h2 class="card__title" data-i18n="links.title">Temporary links</h2>
            <div class="card__subtitle"><span data-i18n="links.subtitleBefore">channel</span> <span class="mono">/t</span> <span data-i18n="links.subtitleAfter">— links you can revoke</span></div>
        </div>
        <div class="card__spacer"></div>

        <div class="panel__tools">
            <?= Ui::search($base, 'links.searchPlaceholder', 'File or note', $query->search, ['sort' => $query->sort, 'dir' => $query->dir]) ?>
            <?php if ($searching): ?>
                <?= Ui::searchReset($query->url($bucket->id, ['search' => null, 'page' => 1])) ?>
            <?php endif ?>
            <?= $sorting->menu('only-md') ?>

            <?php if ($counts['revoked'] > 0): ?>
                <button type="button" class="btn btn--danger-soft btn--sm" data-action="links:purge" data-state="revoked">
                    <svg class="icon icon--sm"><use href="#i-trash"/></svg>
                    <span data-i18n="links.purgeRevoked">Delete revoked</span> <span class="badge badge--danger"><?= Fmt::num($counts['revoked']) ?></span>
                </button>
            <?php endif ?>

            <?php if ($counts['expired'] > 0): ?>
                <button type="button" class="btn btn--danger-soft btn--sm" data-action="links:purge" data-state="expired">
                    <svg class="icon icon--sm"><use href="#i-clock"/></svg>
                    <span data-i18n="links.purgeExpired">Delete expired</span> <span class="badge badge--danger"><?= Fmt::num($counts['expired']) ?></span>
                </button>
            <?php endif ?>

            <?php if ($counts['active'] > 0): ?>
                <button type="button" class="btn btn--ghost btn--sm" data-action="links:revoke-all">
                    <svg class="icon icon--sm"><use href="#i-x-circle"/></svg> <span data-i18n="links.revokeAll">Revoke all</span>
                </button>
            <?php endif ?>
        </div>
    </div>

    <div class="panel__scroll">
        <table class="table table--cards">
            <thead>
            <tr>
                <?= $sorting->th('file', 'table.file', 'File') ?>
                <?= $sorting->th('mode', 'table.mode', 'Mode') ?>
                <?= $sorting->th('', 'table.downloads', 'Downloads') ?>
                <?= $sorting->th('state', 'table.state', 'State') ?>
                <?= $sorting->th('created', 'table.createdF', 'Created') ?>
                <?= $sorting->th('', 'table.note', 'Note') ?>
                <th><span class="visually-hidden" data-i18n="common.actions">Actions</span></th>
            </tr>
            </thead>
            <tbody data-rows="links">
            <?php foreach ($links as $link): ?>
                <tr data-row="link" data-id="<?= $link->id ?>"<?= $link->isAlive() ? '' : ' data-dead="1" class="is-dimmed"' ?>>
                    <td data-primary>
                        <div class="fileline">
                            <span class="ftype ftype--video"><svg class="icon"><use href="#i-link"/></svg></span>
                            <span class="fileline__body">
                                <span class="fileline__name"<?= $link->fileGone() ? ' data-i18n="link.fileGone"' : '' ?>><?= Fmt::e($link->fileName) ?></span>
                                <span class="fileline__meta mono"><?= Fmt::e($link->fileSlug) ?></span>
                            </span>
                        </div>
                    </td>
                    <td data-label="Mode" data-i18n-attr="data-label:table.mode" data-half>
                        <span class="tone tone--<?= $link->attachment ? 'brand' : 'mute' ?>"
                              data-i18n="<?= $link->attachment ? 'link.mode.download' : 'link.mode.view' ?>">
                            <?= $link->attachment ? 'download' : 'view' ?>
                        </span>
                    </td>
                    <td data-label="Downloads" data-i18n-attr="data-label:table.downloads" data-half>
                        <?php if ($link->maxDownloads === null): ?>
                            <span class="text-sm"><?= $link->downloads ?> <span class="text-muted" data-i18n="link.noLimit">no limit</span></span>
                        <?php else: ?>
                            <?php $rest = $link->maxDownloads - $link->downloads ?>
                            <span class="tone tone--<?= $rest <= 0 ? 'danger' : ($rest <= 1 ? 'warn' : 'mute') ?>">
                                <?= $link->downloads ?> / <?= $link->maxDownloads ?>
                            </span>
                        <?php endif ?>
                    </td>
                    <td data-label="State" data-i18n-attr="data-label:table.state" data-half class="text-sm nowrap" data-cell="expiry">
                        <?php if ($link->deadReason() !== null): ?>
                            <span class="tone tone--danger" data-i18n="<?= Fmt::e($link->deadReasonKey()) ?>"><?= Fmt::e($link->deadReason()) ?></span>
                        <?php else: ?>
                            <span data-fmt="left" data-value="<?= Fmt::e($link->expiresAt) ?>"><?= Fmt::e(Fmt::left($link->expiresAt)) ?></span>
                        <?php endif ?>
                    </td>
                    <td data-label="Created" data-i18n-attr="data-label:table.createdF" data-half class="text-sm nowrap" title="<?= Fmt::e(Fmt::date($link->createdAt)) ?>">
                        <span class="text-muted" data-fmt="ago" data-value="<?= Fmt::e($link->createdAt) ?>"><?= Fmt::e(Fmt::ago($link->createdAt)) ?></span>
                    </td>
                    <td data-label="Note" data-i18n-attr="data-label:table.note" class="text-sm text-muted break"<?= $link->note === '' ? ' data-empty-hidden' : '' ?>><?= Fmt::e($link->note) ?></td>
                    <td data-actions>
                        <?php if ($link->isAlive()): ?>
                            <div class="row-actions">
                                <button type="button" class="icon-btn icon-btn--ghost"
                                        data-copy="<?= Fmt::e($link->url) ?>" data-i18n-attr="aria-label:link.copy;title:link.copy"
                                        aria-label="Copy address" title="Copy address">
                                    <svg class="icon"><use href="#i-copy"/></svg>
                                </button>
                                <a class="icon-btn icon-btn--ghost" href="<?= Fmt::e($link->url) ?>"
                                   target="_blank" rel="noopener" data-i18n-attr="aria-label:common.open;title:link.openNew"
                                   aria-label="Open" title="Open in a new tab">
                                    <svg class="icon"><use href="#i-arrow-right"/></svg>
                                </a>
                                <button type="button" class="icon-btn icon-btn--ghost text-danger"
                                        data-i18n-attr="aria-label:link.revoke;title:link.revoke" aria-label="Revoke" title="Revoke"
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
                <div class="empty__title" data-i18n="empty.nothing">Nothing found</div>
                <div class="text-sm" data-i18n="links.empty.search" data-i18n-vars="<?= Fmt::e((string) json_encode(['query' => $query->search])) ?>">No links match “<?= Fmt::e($query->search) ?>”</div>
            <?php else: ?>
                <div class="empty__title" data-i18n="links.empty.title">No links</div>
                <div class="text-sm" data-i18n="links.empty.hint">Issue one from a file card</div>
            <?php endif ?>
        </div>
    </div>

    <?php wrImport('admin/_pagination') ?>
</div>
