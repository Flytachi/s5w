<?php

use Main\Web\Fmt;

?>

<?php wrImport('admin/_crumbs') ?>

<div class="card">
    <div class="card__header">
        <div>
            <div class="card__title">Токены доступа</div>
            <div class="card__subtitle">ключи клиента к каналу <span class="mono">/p</span> этого бакета</div>
        </div>
        <div class="card__spacer"></div>
        <button class="btn btn--dark btn--sm" data-modal-open="modal-token">
            <svg class="icon icon--sm"><use href="#i-plus"/></svg> Выпустить
        </button>
    </div>

    <div class="alert mt-2">
        <svg class="icon"><use href="#i-info"/></svg>
        <div class="alert__body">
            <div class="alert__title">Значение показывается один раз</div>
            <div class="alert__text">
                В базе лежит только sha256-хеш. Потеряли — ротируйте: строка останется той же, ключ станет новым.
                Бакет клиент не указывает, он определяется по токену.
            </div>
        </div>
    </div>

    <div class="table-wrap mt-2">
        <table class="table">
            <thead>
            <tr>
                <th>Название</th>
                <th>Статус</th>
                <th>Срок</th>
                <th>Последнее обращение</th>
                <th>Выпущен</th>
                <th></th>
            </tr>
            </thead>
            <tbody data-rows="tokens">
            <?php foreach ($tokens as $token): ?>
                <tr data-row="token" data-id="<?= (int) $token['id'] ?>" data-name="<?= Fmt::e($token['name']) ?>"
                    data-status="<?= Fmt::e($token['status']['name']) ?>">
                    <td>
                        <div class="fileline">
                            <span class="ftype ftype--<?= $token['expired'] ? 'arch' : 'image' ?>">
                                <svg class="icon"><use href="#i-key"/></svg>
                            </span>
                            <span class="fileline__body">
                                <span class="fileline__name"><?= Fmt::e($token['name']) ?></span>
                                <span class="fileline__meta">id <?= (int) $token['id'] ?></span>
                            </span>
                        </div>
                    </td>
                    <td data-cell="status">
                        <?php if ($token['expired']): ?>
                            <span class="tone tone--danger">просрочен</span>
                        <?php elseif ($token['status']['name'] === 'ACTIVE'): ?>
                            <span class="tone tone--ok"><span class="status-dot" style="background:currentColor"></span> активен</span>
                        <?php else: ?>
                            <span class="tone tone--mute">выключен</span>
                        <?php endif ?>
                    </td>
                    <td class="text-sm nowrap"><?= Fmt::left($token['expiresAt']) ?></td>
                    <td class="text-sm text-muted nowrap"><?= Fmt::ago($token['lastUsedAt']) ?></td>
                    <td class="text-sm text-muted nowrap"><?= Fmt::date($token['createdAt']) ?></td>
                    <td>
                        <div class="row" style="justify-content:flex-end; flex-wrap:nowrap">
                            <button class="btn btn--ghost btn--sm" data-action="token:rotate"
                                    data-id="<?= (int) $token['id'] ?>" data-name="<?= Fmt::e($token['name']) ?>">
                                <svg class="icon icon--sm"><use href="#i-refresh"/></svg> Ротация
                            </button>
                            <div class="dropdown">
                                <button class="icon-btn icon-btn--ghost icon-btn--sm" data-dropdown-toggle aria-label="Действия">
                                    <svg class="icon icon--sm"><use href="#i-more-h"/></svg>
                                </button>
                                <div class="dropdown__menu">
                                    <button class="dropdown__item" data-action="token:toggle" data-id="<?= (int) $token['id'] ?>">
                                        <?= $token['status']['name'] === 'ACTIVE' ? 'Выключить' : 'Включить' ?>
                                        <svg class="icon"><use href="#i-lock"/></svg>
                                    </button>
                                    <button class="dropdown__item" data-action="token:delete"
                                            data-id="<?= (int) $token['id'] ?>" data-name="<?= Fmt::e($token['name']) ?>">
                                        Удалить <svg class="icon"><use href="#i-trash"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <div class="empty" data-empty="tokens"<?= $tokens === [] ? '' : ' hidden' ?>>
        <svg class="icon"><use href="#i-key"/></svg>
        <div class="empty__title">Токенов нет</div>
        <div class="text-sm">Без токена клиент не заберёт приватный файл</div>
    </div>
</div>

<div class="card card--dark mt-3">
    <div class="card__header"><div class="card__title" style="color:#fff">Проверить руками</div></div>
    <div class="secret mt-2" style="background: rgba(255,255,255,.08)">
        <span style="flex:1">curl -H "Authorization: Bearer s5w_…" http://localhost:9090/p/&lt;slug&gt;</span>
        <button class="icon-btn icon-btn--sm" aria-label="Копировать"
                data-copy='curl -H "Authorization: Bearer s5w_…" http://localhost:9090/p/slug'>
            <svg class="icon icon--sm"><use href="#i-copy"/></svg>
        </button>
    </div>
    <p class="text-sm mt-2" style="color: var(--gray-4)">
        Без заголовка — 401. С выключенным или просроченным токеном — тоже 401.
        Slug чужого бакета — 404, не 403.
    </p>
</div>

<?php wrImport('admin/_modals') ?>
