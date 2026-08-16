<?php

use Main\Web\Fmt;

$active = array_values(array_filter($links, static fn(array $l): bool => !$l['revoked']));
$limited = array_values(array_filter($active, static fn(array $l): bool => $l['maxDownloads'] !== null));
$downloads = array_sum(array_column($links, 'downloads'));
?>

<?php wrImport('admin/_crumbs') ?>

<div class="grid grid--4 mb-3">
    <div class="card">
        <div class="metric">
            <span class="metric__label">Живых ссылок</span>
            <span class="metric__value" data-counter="links-active"><?= count($active) ?></span>
        </div>
    </div>
    <div class="card">
        <div class="metric">
            <span class="metric__label">С лимитом скачиваний</span>
            <span class="metric__value"><?= count($limited) ?></span>
        </div>
    </div>
    <div class="card">
        <div class="metric">
            <span class="metric__label">Скачиваний всего</span>
            <span class="metric__value"><?= Fmt::num($downloads) ?></span>
        </div>
    </div>
    <div class="card">
        <div class="metric">
            <span class="metric__label">Эпоха ссылок</span>
            <span class="metric__value" data-counter="epoch">3</span>
            <span class="metric__label">массовый отзыв поднимает её на единицу</span>
        </div>
    </div>
</div>

<div class="card">
    <div class="card__header">
        <div>
            <div class="card__title">Временные ссылки</div>
            <div class="card__subtitle">канал <span class="mono">/t</span> — доступ даёт сама подпись</div>
        </div>
        <div class="card__spacer"></div>
        <button class="btn btn--ghost btn--sm" data-action="links:revoke-all">
            <svg class="icon icon--sm"><use href="#i-x-circle"/></svg> Отозвать все
        </button>
    </div>

    <div class="alert mt-2">
        <svg class="icon"><use href="#i-info"/></svg>
        <div class="alert__body">
            <div class="alert__title">Здесь только ссылки со строкой в базе</div>
            <div class="alert__text">
                Строка заводится, если ссылку нужно уметь отозвать поимённо или ограничить числом скачиваний.
                Остальные живут целиком в подписи — показывать нечего, гасятся только массово.
                Выпускаются из карточки файла.
            </div>
        </div>
    </div>

    <div class="table-wrap mt-2">
        <table class="table">
            <thead>
            <tr>
                <th>Файл</th>
                <th>Режим</th>
                <th>Скачиваний</th>
                <th>Срок</th>
                <th>Пометка</th>
                <th></th>
            </tr>
            </thead>
            <tbody data-rows="links">
            <?php foreach ($links as $link): ?>
                <tr data-row="link" data-id="<?= (int) $link['id'] ?>"<?= $link['revoked'] ? ' data-revoked="1" style="opacity:.55"' : '' ?>>
                    <td>
                        <div class="fileline">
                            <span class="ftype ftype--video"><svg class="icon"><use href="#i-link"/></svg></span>
                            <span class="fileline__body">
                                <span class="fileline__name"><?= Fmt::e($link['file']) ?></span>
                                <span class="fileline__meta mono"><?= Fmt::e($link['slug']) ?></span>
                            </span>
                        </div>
                    </td>
                    <td>
                        <span class="tone tone--<?= $link['disposition']['name'] === 'ATTACHMENT' ? 'brand' : 'mute' ?>">
                            <?= $link['disposition']['name'] === 'ATTACHMENT' ? 'скачивание' : 'просмотр' ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($link['maxDownloads'] === null): ?>
                            <span class="text-sm"><?= (int) $link['downloads'] ?> <span class="text-muted">без лимита</span></span>
                        <?php else: ?>
                            <?php $rest = $link['maxDownloads'] - $link['downloads'] ?>
                            <span class="tone tone--<?= $rest <= 0 ? 'danger' : ($rest <= 1 ? 'warn' : 'mute') ?>">
                                <?= (int) $link['downloads'] ?> / <?= (int) $link['maxDownloads'] ?>
                            </span>
                        <?php endif ?>
                    </td>
                    <td class="text-sm nowrap" data-cell="expiry">
                        <?php if ($link['revoked']): ?>
                            <span class="tone tone--danger">отозвана</span>
                        <?php else: ?>
                            <?= Fmt::left($link['expiresAt']) ?>
                        <?php endif ?>
                    </td>
                    <td class="text-sm text-muted"><?= Fmt::e($link['note']) ?></td>
                    <td>
                        <div class="row" style="justify-content:flex-end; flex-wrap:nowrap">
                            <span class="copyable mono" data-copy="<?= Fmt::e($link['url']) ?>">
                                /t/<?= Fmt::e(substr(basename($link['url']), 0, 10)) ?>…
                                <svg class="icon"><use href="#i-copy"/></svg>
                            </span>
                            <?php if (!$link['revoked']): ?>
                                <button class="icon-btn icon-btn--ghost icon-btn--sm" aria-label="Отозвать"
                                        data-action="link:revoke" data-id="<?= (int) $link['id'] ?>">
                                    <svg class="icon icon--sm"><use href="#i-x-circle"/></svg>
                                </button>
                            <?php endif ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <div class="empty" data-empty="links"<?= $links === [] ? '' : ' hidden' ?>>
        <svg class="icon"><use href="#i-link"/></svg>
        <div class="empty__title">Ссылок нет</div>
        <div class="text-sm">Выпустите ссылку из карточки файла</div>
    </div>
</div>

<div class="card mt-3">
    <div class="card__header">
        <div>
            <div class="card__title">Из чего состоит ссылка</div>
            <div class="card__subtitle">подпись самодостаточна — сервер её не хранит</div>
        </div>
    </div>

    <div class="grid grid--4 mt-2">
        <div>
            <div class="text-sm" style="font-weight:600">id файла</div>
            <div class="text-sm text-muted">на какой файл выдана</div>
        </div>
        <div>
            <div class="text-sm" style="font-weight:600">срок</div>
            <div class="text-sm text-muted">после него — 404, база не нужна</div>
        </div>
        <div>
            <div class="text-sm" style="font-weight:600">эпоха бакета</div>
            <div class="text-sm text-muted">гасит все подписи разом</div>
        </div>
        <div>
            <div class="text-sm" style="font-weight:600">подпись HMAC</div>
            <div class="text-sm text-muted">меняешь байт — ссылка мертва</div>
        </div>
    </div>
</div>

<?php wrImport('admin/_modals') ?>
