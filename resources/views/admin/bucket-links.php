<?php

use Main\Web\Fmt;

/** @var \Main\Request\LinkListRequest $query */
$base = '/admin/ui/buckets/' . $bucket->id . '/links';

/** Заголовок-кнопка: клик сортирует, повторный клик переворачивает. */
$th = static function (string $key, string $label) use ($query, $bucket): string {
    $arrow = $query->sortArrow($key);

    return '<a class="th-sort' . ($arrow === null ? '' : ' is-active') . '" href="'
        . Fmt::e($query->sortUrl($bucket->id, $key)) . '">' . Fmt::e($label)
        . ($arrow === null ? '' : ' <span class="th-sort__arrow">' . $arrow . '</span>') . '</a>';
};
?>

<div class="grid grid--4 metrics-row mb-3">
    <div class="card stat stat--ok">
        <span class="stat__icon"><svg class="icon"><use href="#i-link"/></svg></span>
        <span class="stat__value<?= $counts['active'] === 0 ? ' is-zero' : '' ?>" data-counter="links-active"><?= Fmt::num($counts['active']) ?></span>
        <span class="stat__body">
            <span class="stat__label">Живых</span>
            <span class="stat__note">работают сейчас</span>
        </span>
    </div>

    <div class="card stat stat--brand">
        <span class="stat__icon"><svg class="icon"><use href="#i-layers"/></svg></span>
        <span class="stat__value"><?= Fmt::num($counts['total']) ?></span>
        <span class="stat__body">
            <span class="stat__label">Выпущено</span>
            <span class="stat__note">строка в базе</span>
        </span>
    </div>

    <div class="card stat stat--danger">
        <span class="stat__icon"><svg class="icon"><use href="#i-x-circle"/></svg></span>
        <span class="stat__value<?= $counts['revoked'] === 0 ? ' is-zero' : '' ?>" data-counter="links-revoked"><?= Fmt::num($counts['revoked']) ?></span>
        <span class="stat__body">
            <span class="stat__label">Отозвано</span>
            <span class="stat__note">закрыты вручную</span>
        </span>
    </div>

    <div class="card stat stat--warn">
        <span class="stat__icon"><svg class="icon"><use href="#i-clock"/></svg></span>
        <span class="stat__value<?= $counts['expired'] === 0 ? ' is-zero' : '' ?>" data-counter="links-expired"><?= Fmt::num($counts['expired']) ?></span>
        <span class="stat__body">
            <span class="stat__label">Истекло</span>
            <span class="stat__note">вышел срок</span>
        </span>
    </div>
</div>

<div class="card panel">
    <div class="card__header">
        <div>
            <div class="card__title">Временные ссылки</div>
            <div class="card__subtitle">канал <span class="mono">/t</span> — только со строкой в базе</div>
        </div>
        <div class="card__spacer"></div>

        <div class="panel__tools">
        <form class="search-pill" method="get" action="<?= $base ?>">
            <svg class="icon icon--sm"><use href="#i-search"/></svg>
            <input type="search" name="search" placeholder="Файл или пометка" value="<?= Fmt::e($query->search ?? '') ?>">
            <input type="hidden" name="sort" value="<?= Fmt::e($query->sort) ?>">
            <input type="hidden" name="dir" value="<?= Fmt::e($query->dir) ?>">
        </form>

        <?php if (($query->search ?? '') !== ''): ?>
            <a class="icon-btn icon-btn--ghost icon-btn--sm" href="<?= Fmt::e($query->url($bucket->id, ['search' => null, 'page' => 1])) ?>"
               aria-label="Сбросить поиск">
                <svg class="icon icon--sm"><use href="#i-x"/></svg>
            </a>
        <?php endif ?>

        <button class="btn btn--danger-soft btn--sm" data-action="links:purge" data-state="revoked"
                <?= $counts['revoked'] === 0 ? 'hidden' : '' ?>>
            <svg class="icon icon--sm"><use href="#i-trash"/></svg>
            Удалить отозванные <span class="badge badge--danger"><?= Fmt::num($counts['revoked']) ?></span>
        </button>

        <button class="btn btn--danger-soft btn--sm" data-action="links:purge" data-state="expired"
                <?= $counts['expired'] === 0 ? 'hidden' : '' ?>>
            <svg class="icon icon--sm"><use href="#i-clock"/></svg>
            Удалить истёкшие <span class="badge badge--danger"><?= Fmt::num($counts['expired']) ?></span>
        </button>

        <?php if ($counts['active'] > 0): ?>
            <button class="btn btn--ghost btn--sm" data-action="links:revoke-all">
                <svg class="icon icon--sm"><use href="#i-x-circle"/></svg> Отозвать все
            </button>
        <?php endif ?>
        </div>
    </div>

    <div class="panel__scroll mt-2">
        <table class="table">
            <thead>
            <tr>
                <th><?= $th('file', 'Файл') ?></th>
                <th><?= $th('mode', 'Режим') ?></th>
                <th>Скачиваний</th>
                <th><?= $th('state', 'Состояние') ?></th>
                <th><?= $th('created', 'Создана') ?></th>
                <th>Пометка</th>
                <th></th>
            </tr>
            </thead>
            <tbody data-rows="links">
            <?php foreach ($links as $link): ?>
                <tr data-row="link" data-id="<?= $link->id ?>"<?= $link->isAlive() ? '' : ' data-dead="1" style="opacity:.55"' ?>>
                    <td>
                        <div class="fileline">
                            <span class="ftype ftype--video"><svg class="icon"><use href="#i-link"/></svg></span>
                            <span class="fileline__body">
                                <span class="fileline__name"><?= Fmt::e($link->fileName) ?></span>
                                <span class="fileline__meta mono"><?= Fmt::e($link->fileSlug) ?></span>
                            </span>
                        </div>
                    </td>
                    <td>
                        <span class="tone tone--<?= $link->attachment ? 'brand' : 'mute' ?>">
                            <?= $link->attachment ? 'скачивание' : 'просмотр' ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($link->maxDownloads === null): ?>
                            <span class="text-sm"><?= $link->downloads ?> <span class="text-muted">без лимита</span></span>
                        <?php else: ?>
                            <?php $rest = $link->maxDownloads - $link->downloads ?>
                            <span class="tone tone--<?= $rest <= 0 ? 'danger' : ($rest <= 1 ? 'warn' : 'mute') ?>">
                                <?= $link->downloads ?> / <?= $link->maxDownloads ?>
                            </span>
                        <?php endif ?>
                    </td>
                    <td class="text-sm nowrap" data-cell="expiry">
                        <?php if ($link->deadReason() !== null): ?>
                            <span class="tone tone--danger"><?= Fmt::e($link->deadReason()) ?></span>
                        <?php else: ?>
                            <?= Fmt::left($link->expiresAt) ?>
                        <?php endif ?>
                    </td>
                    <td class="text-sm nowrap" title="<?= Fmt::e(Fmt::date($link->createdAt)) ?>">
                        <span class="text-muted"><?= Fmt::ago($link->createdAt) ?></span>
                    </td>
                    <td class="text-sm text-muted"><?= Fmt::e($link->note) ?></td>
                    <td>
                        <div class="row row-actions" style="justify-content:flex-end; flex-wrap:nowrap">
                            <?php if ($link->isAlive()): ?>
                                <button class="icon-btn icon-btn--ghost icon-btn--sm row-actions__hover"
                                        data-copy="<?= Fmt::e($link->url) ?>" aria-label="Копировать адрес" title="Копировать адрес">
                                    <svg class="icon icon--sm"><use href="#i-copy"/></svg>
                                </button>
                                <a class="icon-btn icon-btn--ghost icon-btn--sm row-actions__hover" href="<?= Fmt::e($link->url) ?>"
                                   target="_blank" rel="noopener" aria-label="Открыть" title="Открыть в новой вкладке">
                                    <svg class="icon icon--sm"><use href="#i-arrow-right"/></svg>
                                </a>
                                <button class="icon-btn icon-btn--ghost icon-btn--sm" aria-label="Отозвать" title="Отозвать"
                                        data-action="link:revoke" data-id="<?= $link->id ?>">
                                    <svg class="icon icon--sm"><use href="#i-x-circle"/></svg>
                                </button>
                            <?php endif ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>

        <div class="empty" data-empty="links"<?= $links === [] ? '' : ' hidden' ?>>
            <svg class="icon"><use href="#i-link"/></svg>
            <?php if (($query->search ?? '') !== ''): ?>
                <div class="empty__title">Ничего не нашлось</div>
                <div class="text-sm">По запросу «<?= Fmt::e($query->search) ?>» ссылок нет</div>
            <?php else: ?>
                <div class="empty__title">Ссылок нет</div>
                <div class="text-sm">Выпустите ссылку из карточки файла — она появится здесь</div>
            <?php endif ?>
        </div>
    </div>

    <?php wrImport('admin/_pagination') ?>
</div>

<?php wrImport('admin/_modals') ?>
