<?php

use Main\Web\Fmt;

$next = wrData('next') ?? '/admin/ui';
$locked = (bool) wrData('locked');

?><!DOCTYPE html>
<html lang="ru">
<head>
    <?php wrImport('admin/_head') ?>
</head>
<body data-page="login">
<script src="<?= Fmt::asset('/assets/js/icons.js') ?>"></script>

<button type="button" class="icon-btn auth__theme" data-theme-toggle aria-label="Тема">
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
                    <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm input-group__end"
                            data-password-peek aria-label="Показать пароль" aria-pressed="false">
                        <svg class="icon icon--sm"><use href="#i-eye"/></svg>
                    </button>
                </div>
            </div>

            <div class="alert alert--danger" data-login-error hidden role="alert">
                <svg class="icon"><use href="#i-alert-triangle"/></svg>
                <div class="alert__body">
                    <div class="alert__text" data-login-message></div>
                </div>
            </div>

            <button type="submit" class="btn btn--primary btn--lg btn--block">Войти</button>
        </div>
        <?php endif ?>
    </form>
</div>

<div class="toasts" data-toasts popover="manual"></div>

<script type="module" src="<?= Fmt::asset('/assets/js/app.js') ?>"></script>
</body>
</html>
