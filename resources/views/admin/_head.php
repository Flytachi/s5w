<?php

use Main\Web\Fmt;

/** Общая часть <head>: мета, иконки, стили, тема до отрисовки, карта модулей. */
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= Fmt::e(wrData('title') ?? 'Вход') ?> — s5w</title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#3b5bdb">
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/assets/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
<link rel="stylesheet" href="<?= Fmt::asset('/assets/css/fonts.css') ?>">
<link rel="stylesheet" href="<?= Fmt::asset('/assets/css/base.css') ?>">
<link rel="stylesheet" href="<?= Fmt::asset('/assets/css/shell.css') ?>">
<link rel="stylesheet" href="<?= Fmt::asset('/assets/css/components.css') ?>">
<link rel="stylesheet" href="<?= Fmt::asset('/assets/css/pages.css') ?>">
<script src="<?= Fmt::asset('/assets/js/theme.js') ?>"></script>
<?= Fmt::importMap() ?>
