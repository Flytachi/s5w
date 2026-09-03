<?php

use Main\Web\Fmt;
use Main\Web\Query;
use Main\Web\Sorting;
use Main\Web\Ui;

/** @var \Main\Request\PanelListRequest $query */
$base = '/admin/ui/buckets/' . $bucket->id . '/files';
$link = static fn(array $params) => Fmt::e(Query::url($base, $params + [
    'search' => $query->search,
    'sort' => $query->sort === 'created' ? null : $query->sort,
    'dir' => $query->dir === 'desc' ? null : $query->dir,
]));
$current = $query->folderFilter();

/** Тот же список, но с другим порядком: папка, поиск и страница сохраняются. */
$sorting = new Sorting(
    ['created' => 'по дате', 'name' => 'по имени', 'type' => 'по типу', 'size' => 'по размеру'],
    $query->sort,
    $query->isDesc(),
    static function (array $override) use ($base, $query): string {
        $sort = $override['sort'] ?? $query->sort;
        $dir = $override['dir'] ?? $query->dir;

        return Query::url($base, [
            'sort' => $sort === 'created' ? null : $sort,
            'dir' => $dir === 'desc' ? null : $dir,
            'page' => null,
        ] + $query->params(1));
    },
);
?>

<div class="fm">
    <!-- ─────────── папки ─────────── -->
    <aside class="fm__side" aria-label="Папки">
        <div class="fm__head">
            <span class="fm__title">Папки</span>
            <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-modal-open="modal-folder" aria-label="Новая папка" title="Новая папка">
                <svg class="icon icon--sm"><use href="#i-plus"/></svg>
            </button>
        </div>

        <div class="fm__scroll" data-rows="folders">
            <a class="fm__folder<?= $current === null ? ' is-active' : '' ?>" href="<?= $link(['folder' => null]) ?>"<?= $current === null ? ' aria-current="true"' : '' ?>>
                <svg class="icon"><use href="#i-layers"/></svg>
                <span class="fm__folder-name">Все файлы</span>
                <span class="fm__count"><?= Fmt::num($bucket->files) ?></span>
            </a>

            <a class="fm__folder<?= $current === '' ? ' is-active' : '' ?>" href="<?= $link(['folder' => '/']) ?>"<?= $current === '' ? ' aria-current="true"' : '' ?>>
                <svg class="icon"><use href="#i-file"/></svg>
                <span class="fm__folder-name">Корень</span>
                <span class="fm__count"><?= Fmt::num(max(0, $bucket->files - array_sum(array_map(static fn($f) => $f->files, $folders)))) ?></span>
            </a>

            <div class="fm__divider"></div>

            <?php foreach ($folders as $folder): ?>
                <div class="fm__folder-wrap" data-row="folder"
                     data-name="<?= Fmt::e($folder->name) ?>"
                     data-public="<?= $folder->public ? '1' : '' ?>"
                     data-retention="<?= $folder->retentionId ?>"
                     data-files="<?= $folder->files ?>"
                     data-max-age="<?= $folder->cacheMaxAge ?? '' ?>"
                     data-visibility="<?= Fmt::e($folder->cacheVisibility?->name ?? '') ?>">
                    <?php
                    // Повторяет folderMarks() в render.js — правки нужны в обоих местах.
                    $marks = [];
                    if (!$folder->public) {
                        $marks[] = ['i-lock', 'brand', 'только по токену'];
                    }
                    if ($folder->hasRetention()) {
                        $marks[] = ['i-clock', 'warn', 'срок хранения: ' . $folder->retentionLabel()];
                    }
                    if ($folder->hasCache()) {
                        $marks[] = ['i-zap', 'temp', 'свой кэш: ' . $folder->cacheLabel()];
                    }
                    ?>
                    <a class="fm__folder<?= $current === $folder->name ? ' is-active' : '' ?>"
                       href="<?= $link(['folder' => $folder->name]) ?>"<?= $current === $folder->name ? ' aria-current="true"' : '' ?>
                       title="<?= Fmt::e($folder->name . ($marks === [] ? '' : ' — ' . implode(', ', array_column($marks, 2)))) ?>">
                        <svg class="icon"><use href="#i-folder"/></svg>
                        <span class="fm__folder-name" data-folder-name><?= Fmt::e($folder->name) ?></span>
                        <span class="fm__badges">
                            <?php foreach ($marks as [$icon, $tone, $hint]): ?>
                                <svg class="icon icon--sm text-<?= $tone ?>" role="img" aria-label="<?= Fmt::e($hint) ?>"><use href="#<?= $icon ?>"/></svg>
                            <?php endforeach ?>
                        </span>
                        <span class="fm__count"><?= Fmt::num($folder->files) ?></span>
                    </a>

                    <div class="dropdown fm__folder-menu">
                        <?= Ui::rowMenu('i-more-v', 'icon-btn--sm') ?>
                        <div class="dropdown__menu" role="menu">
                            <button type="button" class="dropdown__item" data-action="folder:edit" data-name="<?= Fmt::e($folder->name) ?>">
                                Изменить <svg class="icon"><use href="#i-edit"/></svg>
                            </button>
                            <button type="button" class="dropdown__item" data-action="folder:cache" data-name="<?= Fmt::e($folder->name) ?>"
                                    data-max-age="<?= $folder->cacheMaxAge ?? '' ?>"
                                    data-visibility="<?= Fmt::e($folder->cacheVisibility?->name ?? '') ?>">
                                Политика кэша <svg class="icon"><use href="#i-clock"/></svg>
                            </button>
                            <button type="button" class="dropdown__item" data-action="folder:delete"
                                    data-name="<?= Fmt::e($folder->name) ?>" data-files="<?= $folder->files ?>">
                                Удалить <svg class="icon"><use href="#i-trash"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>

            <?php if ($folders === []): ?>
                <p class="text-sm text-muted fm__empty" data-folders-empty>
                    Папок нет — все файлы в корне
                </p>
            <?php endif ?>
        </div>

        <button type="button" class="btn btn--ghost btn--sm fm__add" data-modal-open="modal-folder">
            <svg class="icon icon--sm"><use href="#i-plus"/></svg> Новая папка
        </button>
    </aside>

    <!-- ─────────── файлы ─────────── -->
    <section class="fm__main" aria-label="Файлы">
        <div class="fm__head">
            <div class="fm__where">
                <svg class="icon"><use href="#<?= $current === null ? 'i-layers' : ($current === '' ? 'i-file' : 'i-folder') ?>"/></svg>
                <b><?= $current === null ? 'Все файлы' : ($current === '' ? 'Корень бакета' : Fmt::e($current)) ?></b>
                <span class="text-sm text-muted">
                    <?= Fmt::num($meta->total) ?> <?= Fmt::plural($meta->total, 'файл', 'файла', 'файлов') ?>
                </span>
            </div>

            <?php /* порядок задаётся отдельной кнопкой — при поиске его нельзя терять */ ?>
            <?= Ui::search($base, 'Поиск по имени', $query->search, [
                'folder' => $current === null ? null : ($current === '' ? '/' : $current),
                'sort' => $query->sort,
                'dir' => $query->dir,
            ]) ?>

            <?php if (($query->search ?? '') !== ''): ?>
                <?= Ui::searchReset($link(['search' => null, 'folder' => $current === '' ? '/' : $current])) ?>
            <?php endif ?>

            <?= $sorting->menu() ?>

            <button type="button" class="btn btn--primary btn--sm" data-modal-open="modal-upload">
                <svg class="icon icon--sm"><use href="#i-upload"/></svg> Загрузить
            </button>
        </div>

        <div class="fm__scroll" data-fm-files>
            <table class="table table--cards">
                <tbody data-rows="files">
                <?php foreach ($items as $file): ?>
                    <?php [$kind, $icon] = $file->kind() ?>
                    <tr data-row="file"
                        data-id="<?= Fmt::e($file->slug) ?>"
                        data-name="<?= Fmt::e($file->name) ?>"
                        data-mime="<?= Fmt::e($file->mime) ?>"
                        data-size="<?= $file->size ?>"
                        data-hash="<?= Fmt::e($file->hash) ?>"
                        data-folder="<?= Fmt::e($file->folder ?? '') ?>"
                        data-public="<?= $file->public ? '1' : '' ?>"
                        data-created="<?= Fmt::e($file->createdAt) ?>"
                        data-expires="<?= Fmt::e($file->expiresAt ?? '') ?>"
                        data-private-url="<?= Fmt::e($file->privateUrl) ?>"
                        data-public-url="<?= Fmt::e($file->publicUrl ?? '') ?>">
                        <td data-primary>
                            <button type="button" class="fileline fileline--button" data-action="file:info">
                                <span class="ftype ftype--<?= $kind ?>"><svg class="icon"><use href="#<?= $icon ?>"/></svg></span>
                                <span class="fileline__body">
                                    <span class="fileline__name" title="<?= Fmt::e($file->name) ?>"><?= Fmt::e($file->name) ?></span>
                                    <span class="fileline__meta">
                                        <?= Fmt::bytes($file->size) ?><span class="dot-sep"></span><?= Fmt::e($file->mime) ?>
                                        <?php if ($current === null && $file->folder !== null): ?>
                                            <span class="dot-sep"></span><?= Fmt::e($file->folder) ?>
                                        <?php endif ?>
                                    </span>
                                </span>
                            </button>
                        </td>
                        <td data-label="Канал" data-half>
                            <span class="tone chan chan--<?= $file->channel() ?>">/<?= $file->channel() ?></span>
                        </td>
                        <td data-label="<?= $file->expiresAt === null ? 'Загружен' : 'Срок' ?>" data-half class="text-sm nowrap">
                            <?php if ($file->expiresAt === null): ?>
                                <span class="text-muted"><?= Fmt::ago($file->createdAt) ?></span>
                            <?php else: ?>
                                <span class="text-warn"><?= Fmt::left($file->expiresAt) ?></span>
                            <?php endif ?>
                        </td>
                        <td data-actions>
                            <div class="dropdown">
                                <?= Ui::rowMenu() ?>
                                <div class="dropdown__menu" role="menu">
                                    <button type="button" class="dropdown__item" data-action="file:info">
                                        Подробнее <svg class="icon"><use href="#i-eye"/></svg>
                                    </button>
                                    <button type="button" class="dropdown__item" data-copy="<?= Fmt::e($file->publicUrl ?? $file->privateUrl) ?>">
                                        Копировать ссылку <svg class="icon"><use href="#i-copy"/></svg>
                                    </button>
                                    <button type="button" class="dropdown__item" data-action="link:open">
                                        Временная ссылка <svg class="icon"><use href="#i-link"/></svg>
                                    </button>
                                    <button type="button" class="dropdown__item" data-action="file:rename">
                                        Переименовать <svg class="icon"><use href="#i-edit"/></svg>
                                    </button>
                                    <button type="button" class="dropdown__item" data-action="file:move">
                                        Переместить <svg class="icon"><use href="#i-folder"/></svg>
                                    </button>
                                    <button type="button" class="dropdown__item" data-action="file:delete">
                                        Удалить <svg class="icon"><use href="#i-trash"/></svg>
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>

            <div class="empty" data-empty="files"<?= $items === [] ? '' : ' hidden' ?>>
                <svg class="icon"><use href="#i-file"/></svg>
                <?php if (($query->search ?? '') !== ''): ?>
                    <div class="empty__title">Ничего не нашлось</div>
                    <div class="text-sm">По запросу «<?= Fmt::e($query->search) ?>» здесь пусто</div>
                <?php else: ?>
                    <div class="empty__title">Пусто</div>
                    <div class="text-sm">Загрузите первый файл</div>
                <?php endif ?>
            </div>
        </div>

        <?php wrImport('admin/_pagination') ?>
    </section>
</div>
