<?php

use Main\Web\Fmt;

// Раздел приходит из контроллера: wrIsActiveLink() сравнивает адрес точно,
// а страницы бакета лежат глубже и подсветили бы пустоту.
$nav = wrData('nav');
$bucket = wrData('bucket');
$buckets = wrData('buckets') ?? [];
$active = static fn(string $section): string => $nav === $section ? 'active' : '';
$base = $bucket === null ? null : '/admin/ui/buckets/' . $bucket->id;

?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Fmt::e(wrData('title')) ?> — s5w</title>
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/assets/favicon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
    <meta name="theme-color" content="#3b5bdb">
    <link rel="stylesheet" href="<?= Fmt::asset('/assets/css/fonts.css') ?>">
    <link rel="stylesheet" href="<?= Fmt::asset('/assets/css/admin.css') ?>">
    <script src="<?= Fmt::asset('/assets/js/theme.js') ?>"></script>
</head>
<body data-page="<?= Fmt::e($nav) ?>"<?= $bucket === null ? '' : ' data-bucket-id="' . Fmt::e($bucket->id) . '"' ?>>

<div class="layout">

    <aside class="sidebar">
        <a class="sidebar__logo" href="/admin/ui">
            <svg class="sidebar__logo-mark"><use href="#i-logo"/></svg>
            s5w
        </a>

        <a class="nav-item <?= $active('dashboard') ?>" href="/admin/ui">
            <svg class="icon"><use href="#i-grid"/></svg> Обзор
        </a>
        <a class="nav-item <?= $active('buckets') ?>" href="/admin/ui/buckets">
            <svg class="icon"><use href="#i-database"/></svg> Все бакеты
        </a>

        <span class="nav-section">Бакет</span>

        <!-- Переключатель: раздел при смене бакета сохраняется. -->
        <div class="cselect" data-bucket-switch data-section="<?= Fmt::e(in_array($nav, ['files', 'tokens', 'links'], true) ? $nav : 'overview') ?>">
            <button type="button" class="cselect__btn">
                <span class="cselect__value">
                    <?php if ($bucket === null): ?>
                        <span class="status-dot status-dot--light"></span>не выбран
                    <?php else: ?>
                        <span class="status-dot<?= $bucket->isActive() ? '' : ' status-dot--mid' ?>"></span><span class="cselect__name"><?= Fmt::e($bucket->name) ?></span>
                    <?php endif ?>
                </span>
                <svg class="icon icon--sm cselect__chev"><use href="#i-chevron-down"/></svg>
            </button>
            <div class="cselect__menu">
                <?php foreach ($buckets as $item): ?>
                    <button type="button"
                            class="cselect__option<?= $bucket !== null && $item->id === $bucket->id ? ' is-selected' : '' ?>"
                            data-value="<?= Fmt::e($item->id) ?>">
                        <span class="status-dot<?= $item->isActive() ? '' : ' status-dot--mid' ?>"></span><span class="cselect__name"><?= Fmt::e($item->name) ?></span>
                        <svg class="icon icon--check"><use href="#i-check"/></svg>
                    </button>
                <?php endforeach ?>
            </div>
        </div>

        <?php if ($bucket === null): ?>
            <p class="text-sm text-muted" style="padding: 10px 6px 0">
                Выберите бакет — файлы, папки, токены и ссылки у каждого свои.
            </p>
        <?php else: ?>
            <div class="stack mt-1" style="gap: 4px">
                <a class="nav-item <?= $active('overview') ?>" href="<?= $base ?>">
                    <svg class="icon"><use href="#i-grid"/></svg> Обзор
                </a>
                <a class="nav-item <?= $active('files') ?>" href="<?= $base ?>/files">
                    <svg class="icon"><use href="#i-folder"/></svg> Файлы
                </a>
                <a class="nav-item <?= $active('tokens') ?>" href="<?= $base ?>/tokens">
                    <svg class="icon"><use href="#i-key"/></svg> Токены
                </a>
                <a class="nav-item <?= $active('links') ?>" href="<?= $base ?>/links">
                    <svg class="icon"><use href="#i-link"/></svg> Ссылки
                </a>
            </div>

            <div class="quota <?= $bucket->quotaState() ?>" style="padding: 14px 6px 0">
                <div class="quota__bar"><div class="quota__fill" style="width: <?= $bucket->percent() ?>%"></div></div>
                <div class="quota__meta">
                    <span><b><?= Fmt::bytes($bucket->used) ?></b> из <?= Fmt::bytes($bucket->quota) ?></span>
                    <span><?= round($bucket->percent()) ?>%</span>
                </div>
            </div>
        <?php endif ?>

        <div class="sidebar__footer">
            <a class="nav-item" href="/admin/ui/login" data-logout>
                <svg class="icon"><use href="#i-logout"/></svg> Выйти
            </a>
        </div>
    </aside>

    <main class="main">
        <div class="topbar">
            <button class="icon-btn icon-btn--ghost burger" data-sidebar-toggle aria-label="Меню">
                <svg class="icon"><use href="#i-menu"/></svg>
            </button>

            <div>
                <div class="topbar__title">
                    <?= Fmt::e(wrData('title')) ?>
                    <?php if (wrData('bucket') !== null): ?>
                        <?php $current = wrData('bucket') ?>
                        <span class="tone tone--<?= $current->statusTone() ?>"><?= Fmt::e($current->status) ?></span>
                    <?php endif ?>
                </div>
                <?php $subtitle = wrData('subtitle') ?? 'файловое хранилище' ?>
                <?php if ($subtitle !== ''): ?>
                    <div class="topbar__subtitle"><?= Fmt::e($subtitle) ?></div>
                <?php endif ?>
            </div>

            <div class="topbar__spacer"></div>

            <?php if (wrData('mocked')): ?>
                <span class="mock-note">
                    <svg class="icon"><use href="#i-zap"/></svg>
                    раздел на моках — бэкенд ещё не подключён
                </span>
            <?php else: ?>
                <span class="mock-note mock-note--live">
                    <svg class="icon"><use href="#i-database"/></svg>
                    данные из базы
                </span>
            <?php endif ?>

            <button class="icon-btn" data-theme-toggle aria-label="Тема">
                <svg class="icon"><use href="#i-moon"/></svg>
            </button>
        </div>

        <?php wrContent() ?>
    </main>
</div>

<script src="<?= Fmt::asset('/assets/js/icons.js') ?>"></script>
<script src="<?= Fmt::asset('/assets/js/api.js') ?>"></script>
<script src="<?= Fmt::asset('/assets/js/render.js') ?>"></script>
<script src="<?= Fmt::asset('/assets/js/admin.js') ?>"></script>
</body>
</html>
