<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход — s5w</title>
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/assets/favicon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
    <meta name="theme-color" content="#3b5bdb">
    <link rel="stylesheet" href="/assets/css/fonts.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <script src="/assets/js/theme.js"></script>
</head>
<body style="overflow:auto">

<div class="auth">
    <div class="auth__card">
        <div class="auth__logo">
            <svg><use href="#i-logo"/></svg>
            s5w
        </div>
        <div class="auth__sub">Панель управления хранилищем</div>

        <div class="stack">
            <div class="field">
                <label class="field__label">Логин</label>
                <div class="input-group">
                    <svg class="icon"><use href="#i-user"/></svg>
                    <input class="input" placeholder="admin">
                </div>
            </div>

            <div class="field">
                <label class="field__label">Пароль</label>
                <div class="input-group">
                    <svg class="icon"><use href="#i-lock"/></svg>
                    <input class="input" type="password" placeholder="••••••••">
                </div>
            </div>

            <label class="check">
                <input type="checkbox" checked>
                <span class="check__box"><svg class="icon"><use href="#i-check"/></svg></span>
                Запомнить на этом устройстве
            </label>

            <a class="btn btn--dark btn--lg w-full" href="/admin/ui" style="justify-content:center">Войти</a>
        </div>

        <div class="alert alert--outline mt-3">
            <svg class="icon"><use href="#i-alert-triangle"/></svg>
            <div class="alert__body">
                <div class="alert__title">Формы пока нет по-настоящему</div>
                <div class="alert__text">
                    Экран нарисован под будущий <span class="mono">AdminJwtMiddleware</span> —
                    сейчас админка открыта без проверки.
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/icons.js"></script>
<script src="/assets/js/admin.js"></script>
</body>
</html>
