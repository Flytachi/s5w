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

$titleAttr = $bucket === null
    ? sprintf(' data-title-key="page.%s.title"', Fmt::e($nav))
    : sprintf(' data-title-name="%s"', Fmt::e($bucket->name));

$bucketAttrs = $bucket === null ? '' : sprintf(
    ' data-bucket-id="%s" data-cache-max-age="%s" data-cache-visibility="%s"',
    Fmt::e($bucket->id),
    $bucket->cacheMaxAge ?? '',
    Fmt::e($bucket->cacheVisibility->name),
);

?><!DOCTYPE html>
<html lang="en">
<head>
    <?php wrImport('admin/_head') ?>
</head>
<body data-page="<?= Fmt::e($nav) ?>"<?= $titleAttr ?><?= $bucketAttrs ?>>
<script src="<?= Fmt::asset('/assets/js/icons.js') ?>"></script>

<a class="skip-link" href="#content" data-i18n="a11y.skip">Skip to content</a>

<div class="app">
<aside class="sidebar" id="sidebar" data-sidebar aria-label="Navigation" data-i18n-attr="aria-label:a11y.nav">
    <div class="sidebar__top">
        <a class="sidebar__logo" href="/admin/ui">
            <svg class="sidebar__logo-mark"><use href="#i-logo"/></svg>
            s5w
        </a>
        <button type="button" class="icon-btn icon-btn--ghost sidebar__close" data-sidebar-close aria-label="Close menu" data-i18n-attr="aria-label:a11y.closeMenu">
            <svg class="icon"><use href="#i-x"/></svg>
        </button>
    </div>

    <nav class="stack stack--tight" aria-label="Sections" data-i18n-attr="aria-label:a11y.sections">
        <a class="nav-item<?= $active('dashboard') ?>" href="/admin/ui"<?= $current('dashboard') ?>>
            <svg class="icon"><use href="#i-grid"/></svg> <span data-i18n="nav.dashboard">Overview</span>
        </a>
        <a class="nav-item<?= $active('buckets') ?>" href="/admin/ui/buckets"<?= $current('buckets') ?>>
            <svg class="icon"><use href="#i-database"/></svg> <span data-i18n="nav.buckets">All buckets</span>
        </a>
    </nav>

    <span class="nav-section" id="bucket-switch-label" data-i18n="nav.bucket">Bucket</span>

    <!-- Переключатель: раздел при смене бакета сохраняется. -->
    <div class="cselect" data-bucket-switch data-section="<?= Fmt::e(in_array($nav, ['files', 'tokens', 'links', 'stats'], true) ? $nav : 'overview') ?>">
        <button type="button" class="cselect__btn" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="bucket-switch-label">
            <span class="cselect__value">
                <?php if ($bucket === null): ?>
                    <span class="status-dot status-dot--light"></span><span class="cselect__name" data-i18n="bucket.none">none selected</span>
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
                <span class="cselect__option text-muted" aria-disabled="true" data-i18n="bucket.empty">no buckets yet</span>
            <?php endif ?>
        </div>
    </div>

    <?php if ($bucket === null): ?>
        <p class="sidebar__hint" data-i18n="bucket.pick">Select a bucket</p>
    <?php else: ?>
        <nav class="nav-sub" aria-label="Bucket sections" data-i18n-attr="aria-label:a11y.bucketSections">
            <a class="nav-item<?= $active('overview') ?>" href="<?= $base ?>"<?= $current('overview') ?>>
                <svg class="icon"><use href="#i-grid"/></svg> <span data-i18n="nav.overview">Overview</span>
            </a>
            <a class="nav-item<?= $active('files') ?>" href="<?= $base ?>/files"<?= $current('files') ?>>
                <svg class="icon"><use href="#i-folder"/></svg> <span data-i18n="nav.files">Files</span>
            </a>
            <a class="nav-item<?= $active('tokens') ?>" href="<?= $base ?>/tokens"<?= $current('tokens') ?>>
                <svg class="icon"><use href="#i-key"/></svg> <span data-i18n="nav.tokens">Tokens</span>
            </a>
            <a class="nav-item<?= $active('links') ?>" href="<?= $base ?>/links"<?= $current('links') ?>>
                <svg class="icon"><use href="#i-link"/></svg> <span data-i18n="nav.links">Links</span>
            </a>
            <a class="nav-item<?= $active('stats') ?>" href="<?= $base ?>/stats"<?= $current('stats') ?>>
                <svg class="icon"><use href="#i-chart"/></svg> <span data-i18n="nav.stats">Statistics</span>
            </a>
        </nav>

        <div class="quota sidebar__quota <?= $bucket->quotaState() ?>">
            <div class="quota__bar"><div class="quota__fill" style="width: <?= $bucket->percent() ?>%"></div></div>
            <div class="quota__meta">
                <span><b data-fmt="bytes" data-value="<?= $bucket->used ?>"><?= Fmt::bytes($bucket->used) ?></b> <span data-i18n="quota.of">of</span> <span data-fmt="bytes" data-value="<?= $bucket->quota ?>"><?= Fmt::bytes($bucket->quota) ?></span></span>
                <span><?= round($bucket->percent()) ?>%</span>
            </div>
        </div>
    <?php endif ?>

    <div class="sidebar__footer">
        <a class="nav-item" href="/admin/ui/login" data-logout>
            <svg class="icon"><use href="#i-logout"/></svg> <span data-i18n="nav.logout">Sign out</span>
        </a>
    </div>
</aside>

<div class="sidebar-overlay" data-sidebar-overlay hidden></div>

<div class="main" data-main>
    <header class="topbar">
        <button type="button" class="icon-btn burger" data-sidebar-toggle aria-label="Menu" data-i18n-attr="aria-label:a11y.menu">
            <svg class="icon"><use href="#i-menu"/></svg>
        </button>

        <div class="topbar__text">
            <h1 class="topbar__title">
                <?php if ($bucket === null): ?>
                    <span data-i18n="page.<?= Fmt::e($nav) ?>.title"><?= Fmt::e(wrData('title')) ?></span>
                <?php else: ?>
                    <?= Fmt::e(wrData('title')) ?>
                <?php endif ?>
                <?php if ($bucket !== null): ?>
                    <span class="tone tone--<?= $bucket->statusTone() ?>" data-i18n="status.<?= Fmt::e($bucket->status) ?>"><?= Fmt::e($bucket->status) ?></span>
                <?php endif ?>
            </h1>
            <?php $subtitle = wrData('subtitle') ?>
            <?php $subtitleKey = $subtitle === null ? 'page.default.subtitle' : 'page.' . $nav . '.subtitle' ?>
            <?php $subtitle ??= 'file storage' ?>
            <?php if ($subtitle !== ''): ?>
                <div class="topbar__subtitle" data-i18n="<?= Fmt::e($subtitleKey) ?>"><?= Fmt::e($subtitle) ?></div>
            <?php endif ?>
        </div>

        <div class="topbar__tools">
            <?php /* Пояс объясняет, почему у даты именно такое число — его тянет
                     браузер, и он может не совпасть с поясом сервера. */ ?>
            <span class="tzchip" data-tz-chip data-i18n-attr="title:tz.hint"
                  title="Dates and days in the statistics are shown in this timezone. Detected by the browser; everything is stored in UTC.">
                <svg class="icon icon--sm"><use href="#i-clock"/></svg>
                <?= Fmt::e(wrData('timezone') ?? 'UTC') ?>
            </span>

            <button type="button" class="icon-btn lang-btn" data-lang-toggle aria-label="Language">
                <span data-lang-code>EN</span>
            </button>

            <button type="button" class="icon-btn" data-theme-toggle aria-label="Theme">
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
