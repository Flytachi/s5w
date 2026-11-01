<?php

use Main\Web\Fmt;
use Main\Web\Sorting;
use Main\Web\Ui;

/** @var \Main\Controllers\Requests\TokenListRequest $query */
$base = '/admin/ui/buckets/' . $bucket->id . '/tokens';

$sorting = new Sorting(
    [
        'created' => ['sort.by.issued', 'by issue date'],
        'name' => ['sort.by.name', 'by name'],
        'access' => ['sort.by.access', 'by access'],
        'state' => ['sort.by.state', 'by state'],
        'used' => ['sort.by.used_at', 'by last use'],
    ],
    $query->sort,
    $query->dir === 'desc',
    static fn(array $override) => $query->url($bucket->id, $override),
);

$searching = ($query->search ?? '') !== '';
?>

<div class="grid grid--4 metrics-row mb-3">
    <div class="card stat stat--ok">
        <span class="stat__icon"><svg class="icon"><use href="#i-key"/></svg></span>
        <span class="stat__value<?= $counts['active'] === 0 ? ' is-zero' : '' ?>" data-counter="tokens-active"><?= Fmt::num($counts['active']) ?></span>
        <span class="stat__body">
            <span class="stat__label" data-i18n="tokens.stat.active">Active</span>
            <span class="stat__note"><span data-i18n="common.total">total</span> <?= Fmt::num($counts['total']) ?></span>
        </span>
    </div>

    <div class="card stat stat--warn">
        <span class="stat__icon"><svg class="icon"><use href="#i-zap"/></svg></span>
        <span class="stat__value<?= $counts['full'] === 0 ? ' is-zero' : '' ?>" data-counter="tokens-full"><?= Fmt::num($counts['full']) ?></span>
        <span class="stat__body">
            <span class="stat__label" data-i18n="tokens.stat.full">Full access</span>
            <span class="stat__note" data-i18n="tokens.stat.fullNote">can write to the bucket</span>
        </span>
    </div>

    <div class="card stat stat--mute">
        <span class="stat__icon"><svg class="icon"><use href="#i-lock"/></svg></span>
        <span class="stat__value<?= $counts['inactive'] === 0 ? ' is-zero' : '' ?>" data-counter="tokens-inactive"><?= Fmt::num($counts['inactive']) ?></span>
        <span class="stat__body">
            <span class="stat__label" data-i18n="tokens.stat.off">Disabled</span>
            <span class="stat__note" data-i18n="tokens.stat.offNote">answer with 403</span>
        </span>
    </div>

    <div class="card stat stat--danger">
        <span class="stat__icon"><svg class="icon"><use href="#i-clock"/></svg></span>
        <span class="stat__value<?= $counts['expired'] === 0 ? ' is-zero' : '' ?>" data-counter="tokens-expired"><?= Fmt::num($counts['expired']) ?></span>
        <span class="stat__body">
            <span class="stat__label" data-i18n="tokens.stat.expired">Expired</span>
            <span class="stat__note" data-i18n="time.expired">expired</span>
        </span>
    </div>
</div>

<div class="card panel panel--tokens">
    <div class="card__header">
        <div>
            <h2 class="card__title" data-i18n="tokens.title">Access tokens</h2>
        </div>
        <div class="card__spacer"></div>

        <div class="panel__tools">
            <?= Ui::search($base, 'tokens.searchPlaceholder', 'Token name', $query->search, ['sort' => $query->sort, 'dir' => $query->dir]) ?>
            <?php if ($searching): ?>
                <?= Ui::searchReset($query->url($bucket->id, ['search' => null, 'page' => 1])) ?>
            <?php endif ?>
            <?= $sorting->menu('only-md') ?>
            <button type="button" class="btn btn--primary btn--sm" data-modal-open="modal-token">
                <svg class="icon icon--sm"><use href="#i-plus"/></svg> <span data-i18n="tokens.issue">Issue</span>
            </button>
        </div>
    </div>

    <div class="panel__scroll">
        <table class="table table--cards">
            <thead>
            <tr>
                <?= $sorting->th('name', 'table.token', 'Token') ?>
                <?= $sorting->th('access', 'table.access', 'Access') ?>
                <?= $sorting->th('state', 'table.state', 'State') ?>
                <?= $sorting->th('', 'table.expiry', 'Expiry') ?>
                <?= $sorting->th('used', 'table.lastUsed', 'Last used') ?>
                <?= $sorting->th('created', 'table.issued', 'Issued') ?>
                <th><span class="visually-hidden" data-i18n="common.actions">Actions</span></th>
            </tr>
            </thead>
            <tbody data-rows="tokens">
            <?php foreach ($tokens as $token): ?>
                <?php
                $active = $token->status['name'] === 'ACTIVE' && !$token->expired;
                $full = $token->access['name'] === 'FULL';
                ?>
                <tr data-row="token" data-id="<?= $token->id ?>" data-name="<?= Fmt::e($token->name) ?>"
                    data-status="<?= Fmt::e($token->status['name']) ?>" data-access="<?= Fmt::e($token->access['name']) ?>"
                    <?= $token->expired ? 'data-expired="1" ' : '' ?>class="<?= $active ? '' : 'is-dimmed' ?>">
                    <td data-primary>
                        <div class="fileline">
                            <span class="ftype ftype--<?= $full ? 'doc' : 'arch' ?>">
                                <svg class="icon"><use href="#i-key"/></svg>
                            </span>
                            <span class="fileline__body">
                                <span class="fileline__name" title="<?= Fmt::e($token->name) ?>"><?= Fmt::e($token->name) ?></span>
                                <span class="fileline__meta mono">
                                    <?= $token->tail === '' ? '—' : 's5w_…' . Fmt::e($token->tail) ?>
                                </span>
                            </span>
                        </div>
                    </td>
                    <td data-label="Access" data-i18n-attr="data-label:table.access" data-half data-cell="access">
                        <span class="tone tone--<?= $full ? 'warn' : 'mute' ?>" data-i18n="access.<?= Fmt::e($token->access['name']) ?>"><?= Fmt::e($token->accessLabel) ?></span>
                    </td>
                    <td data-label="State" data-i18n-attr="data-label:table.state" data-half data-cell="status">
                        <?php if ($token->status['name'] !== 'ACTIVE'): ?>
                            <span class="tone tone--mute" data-i18n="token.state.off">disabled</span>
                        <?php elseif ($token->expired): ?>
                            <span class="tone tone--danger" data-i18n="token.state.expired">expired</span>
                        <?php else: ?>
                            <span class="tone tone--ok"><span class="status-dot status-dot--current"></span> <span data-i18n="token.state.active">active</span></span>
                        <?php endif ?>
                    </td>
                    <td data-label="Expiry" data-i18n-attr="data-label:table.expiry" data-half class="text-sm nowrap">
                        <?php if ($token->expiresAt === null): ?>
                            <span class="text-muted" data-i18n="time.forever">permanent</span>
                        <?php else: ?>
                            <span data-fmt="left" data-value="<?= Fmt::e($token->expiresAt) ?>"><?= Fmt::e(Fmt::left($token->expiresAt)) ?></span>
                        <?php endif ?>
                    </td>
                    <td data-label="Last used" data-i18n-attr="data-label:table.lastUsed" data-half class="text-sm text-muted nowrap">
                        <?php if ($token->lastUsedAt === null): ?>
                            <span data-i18n="token.neverUsed">never used</span>
                        <?php else: ?>
                            <span data-fmt="ago" data-value="<?= Fmt::e($token->lastUsedAt) ?>"><?= Fmt::e(Fmt::ago($token->lastUsedAt)) ?></span>
                        <?php endif ?>
                    </td>
                    <td data-label="Issued" data-i18n-attr="data-label:table.issued" class="text-sm text-muted nowrap" title="<?= Fmt::e(Fmt::date($token->createdAt)) ?>">
                        <span data-fmt="ago" data-value="<?= Fmt::e($token->createdAt) ?>"><?= Fmt::e(Fmt::ago($token->createdAt)) ?></span>
                    </td>
                    <td data-actions>
                        <div class="dropdown">
                            <?= Ui::rowMenu() ?>
                            <div class="dropdown__menu" role="menu">
                                <button type="button" class="dropdown__item" data-action="token:rotate"
                                        data-id="<?= $token->id ?>" data-name="<?= Fmt::e($token->name) ?>">
                                    <span data-i18n="token.rotate">Rotate</span> <svg class="icon"><use href="#i-refresh"/></svg>
                                </button>
                                <button type="button" class="dropdown__item" data-action="token:toggle" data-id="<?= $token->id ?>">
                                    <?php $toggleKey = $token->status['name'] === 'ACTIVE' ? 'token.disable' : 'token.enable' ?>
                                    <span data-toggle-label data-i18n="<?= $toggleKey ?>"><?= $toggleKey === 'token.disable' ? 'Disable' : 'Enable' ?></span>
                                    <svg class="icon"><use href="#i-lock"/></svg>
                                </button>
                                <button type="button" class="dropdown__item" data-action="token:delete"
                                        data-id="<?= $token->id ?>" data-name="<?= Fmt::e($token->name) ?>">
                                    <span data-i18n="common.delete">Delete</span> <svg class="icon"><use href="#i-trash"/></svg>
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>

        <div class="empty" data-empty="tokens"<?= $tokens === [] ? '' : ' hidden' ?>>
            <svg class="icon"><use href="#i-key"/></svg>
            <?php if ($searching): ?>
                <div class="empty__title" data-i18n="empty.nothing">Nothing found</div>
                <div class="text-sm" data-i18n="tokens.empty.search" data-i18n-vars="<?= Fmt::e((string) json_encode(['query' => $query->search])) ?>">No tokens match “<?= Fmt::e($query->search) ?>”</div>
            <?php else: ?>
                <div class="empty__title" data-i18n="tokens.empty.title">No tokens</div>
                <div class="text-sm" data-i18n="tokens.empty.hint">Issue the first one</div>
            <?php endif ?>
        </div>
    </div>

    <?php wrImport('admin/_pagination') ?>
</div>
