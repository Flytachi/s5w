<?php

use Main\Web\Fmt;

// Раздел приходит из контроллера: wrIsActiveLink() сравнивает адрес точно,
// а страницы бакета лежат глубже и подсветили бы пустоту.
$nav = wrData('nav');
$bucket = wrData('bucket');
$buckets = wrData('buckets') ?? [];
$active = static fn(string $section): string => $nav === $section ? ' active' : '';
$current = static fn(string $section): string => $nav === $section ? ' aria-current="page"' : '';
$base = $bucket === null ? null : '/admin/ui/buckets/' . $bucket->id;

$bucketAttrs = $bucket === null ? '' : sprintf(
    ' data-bucket-id="%s" data-cache-max-age="%s" data-cache-visibility="%s"',
    Fmt::e($bucket->id),
    $bucket->cacheMaxAge ?? '',
    Fmt::e($bucket->cacheVisibility->name),
);

?><!DOCTYPE html>
<html lang="ru">
<head>
    <?php wrImport('admin/_head') ?>
</head>
<body data-page="<?= Fmt::e($nav) ?>"<?= $bucketAttrs ?>>
<script src="<?= Fmt::asset('/assets/js/icons.js') ?>"></script>

<a class="skip-link" href="#content">К содержимому</a>

<div class="app">
<aside class="sidebar" id="sidebar" data-sidebar aria-label="Навигация">
    <div class="sidebar__top">
        <a class="sidebar__logo" href="/admin/ui">
            <svg class="sidebar__logo-mark"><use href="#i-logo"/></svg>
            s5w
        </a>
        <button type="button" class="icon-btn icon-btn--ghost sidebar__close" data-sidebar-close aria-label="Закрыть меню">
            <svg class="icon"><use href="#i-x"/></svg>
        </button>
    </div>

    <nav class="stack stack--tight" aria-label="Разделы">
        <a class="nav-item<?= $active('dashboard') ?>" href="/admin/ui"<?= $current('dashboard') ?>>
            <svg class="icon"><use href="#i-grid"/></svg> Обзор
        </a>
        <a class="nav-item<?= $active('buckets') ?>" href="/admin/ui/buckets"<?= $current('buckets') ?>>
            <svg class="icon"><use href="#i-database"/></svg> Все бакеты
        </a>
    </nav>

    <span class="nav-section" id="bucket-switch-label">Бакет</span>

    <!-- Переключатель: раздел при смене бакета сохраняется. -->
    <div class="cselect" data-bucket-switch data-section="<?= Fmt::e(in_array($nav, ['files', 'tokens', 'links', 'stats'], true) ? $nav : 'overview') ?>">
        <button type="button" class="cselect__btn" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="bucket-switch-label">
            <span class="cselect__value">
                <?php if ($bucket === null): ?>
                    <span class="status-dot status-dot--light"></span><span class="cselect__name">не выбран</span>
                <?php else: ?>
                    <span class="status-dot<?= $bucket->isActive() ? '' : ' status-dot--mid' ?>"></span><span class="cselect__name"><?= Fmt::e($bucket->name) ?></span>
                <?php endif ?>
            </span>
            <svg class="icon icon--sm cselect__chev"><use href="#i-chevron-down"/></svg>
        </button>
        <div class="cselect__menu" role="listbox">
            <?php foreach ($buckets as $item): ?>
                <button type="button" role="option"
                        class="cselect__option<?= $bucket !== null && $item->id === $bucket->id ? ' is-selected' : '' ?>"
                        aria-selected="<?= $bucket !== null && $item->id === $bucket->id ? 'true' : 'false' ?>"
                        data-value="<?= Fmt::e($item->id) ?>">
                    <span class="status-dot<?= $item->isActive() ? '' : ' status-dot--mid' ?>"></span><span class="cselect__name"><?= Fmt::e($item->name) ?></span>
                    <svg class="icon icon--check"><use href="#i-check"/></svg>
                </button>
            <?php endforeach ?>
            <?php if ($buckets === []): ?>
                <span class="cselect__option text-muted" aria-disabled="true">бакетов пока нет</span>
            <?php endif ?>
        </div>
    </div>

    <?php if ($bucket === null): ?>
        <p class="sidebar__hint">Выберите бакет</p>
    <?php else: ?>
        <nav class="nav-sub" aria-label="Разделы бакета">
            <a class="nav-item<?= $active('overview') ?>" href="<?= $base ?>"<?= $current('overview') ?>>
                <svg class="icon"><use href="#i-grid"/></svg> Обзор
            </a>
            <a class="nav-item<?= $active('files') ?>" href="<?= $base ?>/files"<?= $current('files') ?>>
                <svg class="icon"><use href="#i-folder"/></svg> Файлы
            </a>
            <a class="nav-item<?= $active('tokens') ?>" href="<?= $base ?>/tokens"<?= $current('tokens') ?>>
                <svg class="icon"><use href="#i-key"/></svg> Токены
            </a>
            <a class="nav-item<?= $active('links') ?>" href="<?= $base ?>/links"<?= $current('links') ?>>
                <svg class="icon"><use href="#i-link"/></svg> Ссылки
            </a>
            <a class="nav-item<?= $active('stats') ?>" href="<?= $base ?>/stats"<?= $current('stats') ?>>
                <svg class="icon"><use href="#i-chart"/></svg> Статистика
            </a>
        </nav>

        <div class="quota sidebar__quota <?= $bucket->quotaState() ?>">
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

<div class="sidebar-overlay" data-sidebar-overlay hidden></div>

<div class="main" data-main>
    <header class="topbar">
        <button type="button" class="icon-btn burger" data-sidebar-toggle aria-label="Меню">
            <svg class="icon"><use href="#i-menu"/></svg>
        </button>

        <div class="topbar__text">
            <h1 class="topbar__title">
                <?= Fmt::e(wrData('title')) ?>
                <?php if ($bucket !== null): ?>
                    <span class="tone tone--<?= $bucket->statusTone() ?>"><?= Fmt::e($bucket->status) ?></span>
                <?php endif ?>
            </h1>
            <?php $subtitle = wrData('subtitle') ?? 'файловое хранилище' ?>
            <?php if ($subtitle !== ''): ?>
                <div class="topbar__subtitle"><?= Fmt::e($subtitle) ?></div>
            <?php endif ?>
        </div>

        <div class="topbar__tools">
            <?php /* Пояс объясняет, почему у даты именно такое число — его тянет
                     браузер, и он может не совпасть с поясом сервера. */ ?>
            <span class="tzchip" data-tz-chip
                  title="Даты и сутки в статистике показаны в этом поясе. Определяется браузером; в базе всё лежит в UTC.">
                <svg class="icon icon--sm"><use href="#i-clock"/></svg>
                <?= Fmt::e(wrData('timezone') ?? 'UTC') ?>
            </span>

            <button type="button" class="icon-btn" data-theme-toggle aria-label="Тема">
                <svg class="icon"><use href="#i-moon"/></svg>
            </button>
        </div>
    </header>

    <main class="content" id="content">
        <?php wrContent() ?>
    </main>
</div>
</div>

<?php if (wrData('modals')): wrImport('admin/_modals'); endif ?>

<div class="toasts" data-toasts popover="manual"></div>

<script type="module" src="<?= Fmt::asset('/assets/js/app.js') ?>"></script>
</body>
</html>
