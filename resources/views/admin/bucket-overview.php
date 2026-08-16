<?php

use Main\Web\Fmt;

$base = '/admin/ui/buckets/' . $bucket->id;
?>

<div class="grid grid--4 mb-3">
    <a class="card" href="<?= $base ?>/files">
        <div class="metric">
            <span class="metric__label">Файлов</span>
            <span class="metric__value"><?= Fmt::num($bucket->files) ?></span>
            <span class="metric__delta metric__delta--up">
                <svg class="icon icon--sm"><use href="#i-folder"/></svg>
                в <?= $bucket->folders ?> <?= Fmt::plural($bucket->folders, 'папке', 'папках', 'папках') ?>
            </span>
        </div>
    </a>

    <div class="card">
        <div class="metric">
            <span class="metric__label">Блобов на диске</span>
            <span class="metric__value"><?= Fmt::num($bucket->blobs) ?></span>
            <span class="metric__delta metric__delta--up">
                <svg class="icon icon--sm"><use href="#i-layers"/></svg>
                дублей свёрнуто: <?= Fmt::num($bucket->files - $bucket->blobs) ?>
            </span>
        </div>
    </div>

    <a class="card" href="<?= $base ?>/links">
        <div class="metric">
            <span class="metric__label">Временные ссылки</span>
            <span class="metric__value"><?= Fmt::num($linkCounts['active']) ?></span>
            <span class="metric__label">живых из <?= Fmt::num($linkCounts['total']) ?> выпущенных</span>
        </div>
    </a>

    <a class="card" href="<?= $base ?>/tokens">
        <div class="metric">
            <span class="metric__label">Токены доступа</span>
            <span class="metric__value"><?= Fmt::num($tokenCount) ?></span>
            <span class="metric__label">ключи к каналу <span class="mono">/p</span></span>
        </div>
    </a>
</div>

<div class="grid grid--3">
    <div class="card card--brand">
        <div class="card__header">
            <div>
                <div class="card__title">Место</div>
                <div class="card__subtitle">считается по блобам, а не по файлам</div>
            </div>
            <div class="card__spacer"></div>
            <span class="tone"><?= round($bucket->percent()) ?>% занято</span>
        </div>

        <div class="stat-hero">
            <div class="stat-hero__num"><?= Fmt::bytes($bucket->used) ?></div>
            <div class="stat-hero__sep"></div>
            <div class="stat-hero__label">из <?= Fmt::bytes($bucket->quota) ?>, свободно <?= Fmt::bytes($bucket->free) ?></div>
        </div>

        <div class="quota <?= $bucket->quotaState() ?>">
            <div class="quota__bar"><div class="quota__fill" style="width: <?= $bucket->percent() ?>%"></div></div>
        </div>

        <p class="text-sm mt-3">
            Одинаковые файлы делят один блоб, поэтому сумма размеров файлов больше занятого места.
        </p>
    </div>

    <div class="card">
        <div class="card__header">
            <div class="card__title">Бакет</div>
            <div class="card__spacer"></div>
            <button class="icon-btn icon-btn--ghost icon-btn--sm" aria-label="Изменить"
                    data-action="bucket:edit" data-id="<?= Fmt::e($bucket->id) ?>"
                    data-name="<?= Fmt::e($bucket->name) ?>" data-description="<?= Fmt::e($bucket->description) ?>"
                    data-quota="<?= $bucket->quota ?>">
                <svg class="icon icon--sm"><use href="#i-edit"/></svg>
            </button>
        </div>

        <dl class="kv mt-2">
            <dt>Статус</dt>
            <dd><span class="tone tone--<?= $bucket->statusTone() ?>"><?= Fmt::e($bucket->status) ?></span></dd>
            <dt>Создан</dt><dd><?= Fmt::date($bucket->createdAt) ?></dd>
            <?php if ($bucket->description !== ''): ?>
                <?php /* описание из шапки переехало сюда — там оно занимало высоту у списков */ ?>
                <dt>Описание</dt><dd><?= Fmt::e($bucket->description) ?></dd>
            <?php endif ?>
        </dl>

        <div class="text-sm text-muted mt-3">Идентификатор в адресах публичной отдачи</div>
        <div class="row mt-1">
            <span class="copyable mono" data-copy="<?= Fmt::e($bucket->id) ?>">
                <?= Fmt::e(substr($bucket->id, 0, 20)) ?>…
                <svg class="icon"><use href="#i-copy"/></svg>
            </span>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <div class="card__title">Кэш по умолчанию</div>
            <div class="card__spacer"></div>
            <button class="icon-btn icon-btn--ghost icon-btn--sm" aria-label="Изменить"
                    data-action="bucket:cache" data-id="<?= Fmt::e($bucket->id) ?>"
                    data-max-age="<?= $bucket->cacheMaxAge ?? '' ?>"
                    data-visibility="<?= Fmt::e($bucket->cacheVisibility ?? '') ?>">
                <svg class="icon icon--sm"><use href="#i-edit"/></svg>
            </button>
        </div>

        <?php if ($bucket->cacheVisibility === null): ?>
            <p class="text-sm text-muted mt-2">
                Не задано — берётся глобальный дефолт сервиса. Папка может переопределить его у себя.
            </p>
        <?php else: ?>
            <dl class="kv mt-2">
                <dt>Видимость</dt>
                <dd><span class="tone tone--<?= $bucket->cacheVisibility === 'PUBLIC' ? 'ok' : 'brand' ?>">
                    <?= Fmt::e($bucket->cacheVisibility) ?>
                </span></dd>
                <dt>max-age</dt><dd><?= (int) $bucket->cacheMaxAge ?> с</dd>
            </dl>
        <?php endif ?>

        <p class="text-sm text-muted mt-2">
            Приватный файл не получит <span class="mono">public</span>, а каналы
            <span class="mono">/p</span> и <span class="mono">/t</span> всегда отдают
            <span class="mono">private</span>.
        </p>
    </div>
</div>

<?php wrImport('admin/_modals') ?>
