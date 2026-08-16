<?php

use Main\Web\Fmt;

?>

<?php wrImport('admin/_crumbs') ?>

<div class="alert mb-3">
    <svg class="icon"><use href="#i-info"/></svg>
    <div class="alert__body">
        <div class="alert__title">Папка задаёт файлам три вещи</div>
        <div class="alert__text">
            Видимость — попадёт файл в открытую отдачу <span class="mono">/o</span> или нет.
            Срок хранения — скользящий, считается от загрузки каждого файла.
            Политика кэша — что уйдёт в <span class="mono">Cache-Control</span>.
            Вложенности нет, список плоский; корень бакета всегда публичный и бессрочный.
        </div>
    </div>
</div>

<div class="grid grid--auto" data-rows="folders">
    <?php foreach ($folders as $folder): ?>
        <div class="card" data-row="folder" data-name="<?= Fmt::e($folder['name']) ?>">
            <div class="card__header">
                <span class="ftype ftype--<?= $folder['public'] ? 'audio' : 'doc' ?>">
                    <svg class="icon"><use href="#i-folder"/></svg>
                </span>
                <div>
                    <div class="card__title" data-folder-name><?= Fmt::e($folder['name']) ?></div>
                    <div class="card__subtitle"><?= Fmt::num($folder['files']) ?> файлов · <?= Fmt::bytes($folder['bytes']) ?></div>
                </div>
                <div class="card__spacer"></div>
                <div class="dropdown">
                    <button class="icon-btn icon-btn--ghost icon-btn--sm" data-dropdown-toggle aria-label="Действия">
                        <svg class="icon icon--sm"><use href="#i-more-v"/></svg>
                    </button>
                    <div class="dropdown__menu">
                        <button class="dropdown__item" data-action="folder:cache" data-name="<?= Fmt::e($folder['name']) ?>">
                            Политика кэша <svg class="icon"><use href="#i-clock"/></svg>
                        </button>
                        <button class="dropdown__item" data-action="folder:delete"
                                data-name="<?= Fmt::e($folder['name']) ?>" data-files="<?= (int) $folder['files'] ?>">
                            Удалить <svg class="icon"><use href="#i-trash"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <div class="row mt-2" style="gap:8px" data-cell="badges">
                <?php if ($folder['public']): ?>
                    <span class="tone tone--ok"><svg class="icon"><use href="#i-globe"/></svg> публичная</span>
                <?php else: ?>
                    <span class="tone tone--brand"><svg class="icon"><use href="#i-lock"/></svg> по токену</span>
                <?php endif ?>

                <?php if ($folder['retention']['name'] === 'NONE'): ?>
                    <span class="tone tone--mute">без срока</span>
                <?php else: ?>
                    <span class="tone tone--warn">
                        <svg class="icon"><use href="#i-clock"/></svg>
                        <?= Fmt::e(strtolower($folder['retention']['name'])) ?>
                    </span>
                <?php endif ?>

                <?php if ($folder['cache']['visibility'] !== null): ?>
                    <span class="tone tone--mute mono"><?= Fmt::e($folder['cache']['visibility']) ?> · <?= (int) $folder['cache']['maxAge'] ?>s</span>
                <?php endif ?>
            </div>
        </div>
    <?php endforeach ?>

    <button class="card card--dashed" data-modal-open="modal-folder">
        <svg class="icon icon--lg"><use href="#i-plus"/></svg>
        <div style="font-weight:600; margin-top:8px">Новая папка</div>
        <div class="text-sm text-muted">видимость, срок хранения и кэш</div>
    </button>
</div>

<?php wrImport('admin/_modals') ?>
