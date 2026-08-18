<?php

use Main\Web\Fmt;

$next = wrData('next') ?? '/admin/ui';
$locked = (bool) wrData('locked');

?><!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход — s5w</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/assets/favicon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
    <meta name="theme-color" content="#3b5bdb">
    <link rel="stylesheet" href="<?= Fmt::asset('/assets/css/fonts.css') ?>">
    <link rel="stylesheet" href="<?= Fmt::asset('/assets/css/admin.css') ?>">
    <script src="<?= Fmt::asset('/assets/js/theme.js') ?>"></script>
</head>
<body style="overflow:auto">

<button class="icon-btn auth__theme" data-theme-toggle aria-label="Тема">
    <svg class="icon"><use href="#i-moon"/></svg>
</button>

<div class="auth">
    <form class="auth__card" data-login-form data-next="<?= Fmt::e($next) ?>" autocomplete="on">
        <div class="auth__logo">
            <svg><use href="#i-logo"/></svg>
            s5w
        </div>
        <div class="auth__sub">Панель управления хранилищем</div>

        <?php if ($locked): ?>
            <div class="alert alert--danger mt-3">
                <svg class="icon"><use href="#i-lock"/></svg>
                <div class="alert__body">
                    <div class="alert__title">Панель заперта</div>
                    <div class="alert__text">
                        Не заданы <span class="mono">ADMIN_LOGIN</span>
                        и <span class="mono">ADMIN_PASSWORD</span>.
                    </div>
                </div>
            </div>
        <?php else: ?>

        <div class="stack mt-3">
            <div class="field">
                <label class="field__label" for="login">Логин</label>
                <div class="input-group">
                    <svg class="icon"><use href="#i-user"/></svg>
                    <input class="input" id="login" name="login" placeholder="admin"
                           autocomplete="username" autocapitalize="off" spellcheck="false" autofocus required>
                </div>
            </div>

            <div class="field">
                <label class="field__label" for="password">Пароль</label>
                <div class="input-group">
                    <svg class="icon"><use href="#i-lock"/></svg>
                    <input class="input" id="password" name="password" type="password" placeholder="••••••••"
                           autocomplete="current-password" required>
                    <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm auth__peek"
                            data-password-peek aria-label="Показать пароль">
                        <svg class="icon icon--sm"><use href="#i-eye"/></svg>
                    </button>
                </div>
            </div>

            <div class="alert alert--danger" data-login-error hidden>
                <svg class="icon"><use href="#i-alert-triangle"/></svg>
                <div class="alert__body">
                    <div class="alert__text" data-login-message></div>
                </div>
            </div>

            <button type="submit" class="btn btn--dark btn--lg w-full" style="justify-content:center">
                Войти
            </button>
        </div>
        <?php endif ?>
    </form>
</div>

<script src="<?= Fmt::asset('/assets/js/icons.js') ?>"></script>
<script src="<?= Fmt::asset('/assets/js/api.js') ?>"></script>
<script src="<?= Fmt::asset('/assets/js/admin.js') ?>"></script>
</body>
</html>
