<?php

use Main\Web\Fmt;
use Main\Web\Sorting;
use Main\Web\Ui;

/** @var \Main\Request\TokenListRequest $query */
$base = '/admin/ui/buckets/' . $bucket->id . '/tokens';

$sorting = new Sorting(
    ['created' => 'по дате выпуска', 'name' => 'по имени', 'access' => 'по доступу', 'state' => 'по состоянию', 'used' => 'по использованию'],
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
            <span class="stat__label">Активные</span>
            <span class="stat__note">всего <?= Fmt::num($counts['total']) ?></span>
        </span>
    </div>

    <div class="card stat stat--warn">
        <span class="stat__icon"><svg class="icon"><use href="#i-zap"/></svg></span>
        <span class="stat__value<?= $counts['full'] === 0 ? ' is-zero' : '' ?>" data-counter="tokens-full"><?= Fmt::num($counts['full']) ?></span>
        <span class="stat__body">
            <span class="stat__label">Полный доступ</span>
            <span class="stat__note">пишут в бакет</span>
        </span>
    </div>

    <div class="card stat stat--mute">
        <span class="stat__icon"><svg class="icon"><use href="#i-lock"/></svg></span>
        <span class="stat__value<?= $counts['inactive'] === 0 ? ' is-zero' : '' ?>" data-counter="tokens-inactive"><?= Fmt::num($counts['inactive']) ?></span>
        <span class="stat__body">
            <span class="stat__label">Выключены</span>
            <span class="stat__note">отвечают 403</span>
        </span>
    </div>

    <div class="card stat stat--danger">
        <span class="stat__icon"><svg class="icon"><use href="#i-clock"/></svg></span>
        <span class="stat__value<?= $counts['expired'] === 0 ? ' is-zero' : '' ?>" data-counter="tokens-expired"><?= Fmt::num($counts['expired']) ?></span>
        <span class="stat__body">
            <span class="stat__label">Просрочены</span>
            <span class="stat__note">срок истёк</span>
        </span>
    </div>
</div>

<div class="card panel panel--tokens">
    <div class="card__header">
        <div>
            <h2 class="card__title">Токены доступа</h2>
        </div>
        <div class="card__spacer"></div>

        <div class="panel__tools">
            <?= Ui::search($base, 'Имя токена', $query->search, ['sort' => $query->sort, 'dir' => $query->dir]) ?>
            <?php if ($searching): ?>
                <?= Ui::searchReset($query->url($bucket->id, ['search' => null, 'page' => 1])) ?>
            <?php endif ?>
            <?= $sorting->menu('only-md') ?>
            <button type="button" class="btn btn--primary btn--sm" data-modal-open="modal-token">
                <svg class="icon icon--sm"><use href="#i-plus"/></svg> Выпустить
            </button>
        </div>
    </div>

    <div class="panel__scroll">
        <table class="table table--cards">
            <thead>
            <tr>
                <?= $sorting->th('name', 'Токен') ?>
                <?= $sorting->th('access', 'Доступ') ?>
                <?= $sorting->th('state', 'Состояние') ?>
                <?= $sorting->th('', 'Срок') ?>
                <?= $sorting->th('used', 'Использован') ?>
                <?= $sorting->th('created', 'Выпущен') ?>
                <th><span class="visually-hidden">Действия</span></th>
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
                    <td data-label="Доступ" data-half data-cell="access">
                        <span class="tone tone--<?= $full ? 'warn' : 'mute' ?>"><?= Fmt::e($token->accessLabel) ?></span>
                    </td>
                    <td data-label="Состояние" data-half data-cell="status">
                        <?php if ($token->status['name'] !== 'ACTIVE'): ?>
                            <span class="tone tone--mute">выключен</span>
                        <?php elseif ($token->expired): ?>
                            <span class="tone tone--danger">просрочен</span>
                        <?php else: ?>
                            <span class="tone tone--ok"><span class="status-dot status-dot--current"></span> активен</span>
                        <?php endif ?>
                    </td>
                    <td data-label="Срок" data-half class="text-sm nowrap">
                        <?= $token->expiresAt === null ? '<span class="text-muted">бессрочно</span>' : Fmt::left($token->expiresAt) ?>
                    </td>
                    <td data-label="Использован" data-half class="text-sm text-muted nowrap">
                        <?= $token->lastUsedAt === null ? 'не использовался' : Fmt::ago($token->lastUsedAt) ?>
                    </td>
                    <td data-label="Выпущен" class="text-sm text-muted nowrap" title="<?= Fmt::e(Fmt::date($token->createdAt)) ?>">
                        <?= Fmt::ago($token->createdAt) ?>
                    </td>
                    <td data-actions>
                        <div class="dropdown">
                            <?= Ui::rowMenu() ?>
                            <div class="dropdown__menu" role="menu">
                                <button type="button" class="dropdown__item" data-action="token:rotate"
                                        data-id="<?= $token->id ?>" data-name="<?= Fmt::e($token->name) ?>">
                                    Ротация <svg class="icon"><use href="#i-refresh"/></svg>
                                </button>
                                <button type="button" class="dropdown__item" data-action="token:toggle" data-id="<?= $token->id ?>">
                                    <span data-toggle-label><?= $token->status['name'] === 'ACTIVE' ? 'Выключить' : 'Включить' ?></span>
                                    <svg class="icon"><use href="#i-lock"/></svg>
                                </button>
                                <button type="button" class="dropdown__item" data-action="token:delete"
                                        data-id="<?= $token->id ?>" data-name="<?= Fmt::e($token->name) ?>">
                                    Удалить <svg class="icon"><use href="#i-trash"/></svg>
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
                <div class="empty__title">Ничего не нашлось</div>
                <div class="text-sm">По запросу «<?= Fmt::e($query->search) ?>» токенов нет</div>
            <?php else: ?>
                <div class="empty__title">Токенов нет</div>
                <div class="text-sm">Выпустите первый</div>
            <?php endif ?>
        </div>
    </div>

    <?php wrImport('admin/_pagination') ?>
</div>
