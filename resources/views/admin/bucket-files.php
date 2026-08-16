<?php

use Main\Web\Fmt;
use Main\Web\Query;

$base = '/admin/ui/buckets/' . $bucket->id . '/files';
$link = static fn(array $params) => Fmt::e(Query::url($base, $params + [
    'search' => $query->search,
    'sort' => $query->sort === 'created' ? null : $query->sort,
    'dir' => $query->dir === 'desc' ? null : $query->dir,
]));
$current = $query->folderFilter();

/** Тот же список, но с другим порядком: папка, поиск и страница сохраняются. */
$order = static fn(array $params) => Fmt::e(Query::url($base, $params + $query->params(1)));
?>

<div class="fm">
    <!-- ─────────── папки ─────────── -->
    <aside class="fm__side">
        <div class="fm__head">
            <span class="fm__title">Папки</span>
            <button class="icon-btn icon-btn--ghost icon-btn--sm" data-modal-open="modal-folder" aria-label="Новая папка">
                <svg class="icon icon--sm"><use href="#i-plus"/></svg>
            </button>
        </div>

        <div class="fm__scroll" data-rows="folders">
            <a class="fm__folder<?= $current === null ? ' is-active' : '' ?>" href="<?= $link(['folder' => null]) ?>">
                <svg class="icon"><use href="#i-layers"/></svg>
                <span class="fm__folder-name">Все файлы</span>
                <span class="fm__count"><?= Fmt::num($bucket->files) ?></span>
            </a>

            <a class="fm__folder<?= $current === '' ? ' is-active' : '' ?>" href="<?= $link(['folder' => '/']) ?>">
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
                     data-visibility="<?= Fmt::e($folder->cacheVisibility ?? '') ?>">
                    <?php
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
                       href="<?= $link(['folder' => $folder->name]) ?>"
                       title="<?= Fmt::e($folder->name . ($marks === [] ? '' : ' — ' . implode(', ', array_column($marks, 2)))) ?>">
                        <svg class="icon"><use href="#i-folder"/></svg>
                        <span class="fm__folder-name" data-folder-name><?= Fmt::e($folder->name) ?></span>
                        <span class="fm__badges">
                            <?php foreach ($marks as [$icon, $tone, $hint]): ?>
                                <svg class="icon icon--sm text-<?= $tone ?>" aria-label="<?= Fmt::e($hint) ?>"><use href="#<?= $icon ?>"/></svg>
                            <?php endforeach ?>
                        </span>
                        <span class="fm__count"><?= Fmt::num($folder->files) ?></span>
                    </a>

                    <div class="dropdown fm__folder-menu">
                        <button class="icon-btn icon-btn--ghost icon-btn--sm" data-dropdown-toggle aria-label="Действия">
                            <svg class="icon icon--sm"><use href="#i-more-v"/></svg>
                        </button>
                        <div class="dropdown__menu">
                            <button class="dropdown__item" data-action="folder:edit" data-name="<?= Fmt::e($folder->name) ?>">
                                Изменить <svg class="icon"><use href="#i-edit"/></svg>
                            </button>
                            <button class="dropdown__item" data-action="folder:cache" data-name="<?= Fmt::e($folder->name) ?>"
                                    data-max-age="<?= $folder->cacheMaxAge ?? '' ?>"
                                    data-visibility="<?= Fmt::e($folder->cacheVisibility ?? '') ?>">
                                Политика кэша <svg class="icon"><use href="#i-clock"/></svg>
                            </button>
                            <button class="dropdown__item" data-action="folder:delete"
                                    data-name="<?= Fmt::e($folder->name) ?>" data-files="<?= $folder->files ?>">
                                Удалить <svg class="icon"><use href="#i-trash"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>

            <?php if ($folders === []): ?>
                <p class="text-sm text-muted" style="padding: 10px 8px">
                    Папок нет — все файлы лежат в корне. Папка задаёт видимость, срок хранения и кэш.
                </p>
            <?php endif ?>
        </div>

        <button class="btn btn--ghost btn--sm fm__add" data-modal-open="modal-folder">
            <svg class="icon icon--sm"><use href="#i-plus"/></svg> Новая папка
        </button>
    </aside>

    <!-- ─────────── файлы ─────────── -->
    <section class="fm__main">
        <form class="fm__head" method="get" action="<?= $base ?>">
            <?php if ($current !== null): ?>
                <input type="hidden" name="folder" value="<?= $current === '' ? '/' : Fmt::e($current) ?>">
            <?php endif ?>
            <?php /* порядок задаётся отдельной кнопкой — при поиске его нельзя терять */ ?>
            <input type="hidden" name="sort" value="<?= Fmt::e($query->sort) ?>">
            <input type="hidden" name="dir" value="<?= Fmt::e($query->dir) ?>">

            <div class="fm__where">
                <svg class="icon"><use href="#<?= $current === null ? 'i-layers' : ($current === '' ? 'i-file' : 'i-folder') ?>"/></svg>
                <b><?= $current === null ? 'Все файлы' : ($current === '' ? 'Корень бакета' : Fmt::e($current)) ?></b>
                <span class="text-sm text-muted">
                    <?= Fmt::num($meta->total) ?> <?= Fmt::plural($meta->total, 'файл', 'файла', 'файлов') ?>
                </span>
            </div>

            <div class="search-pill">
                <svg class="icon icon--sm"><use href="#i-search"/></svg>
                <input type="search" name="search" placeholder="Поиск по имени" value="<?= Fmt::e($query->search ?? '') ?>">
            </div>

            <?php if (($query->search ?? '') !== ''): ?>
                <a class="icon-btn icon-btn--ghost icon-btn--sm" href="<?= $link(['search' => null, 'folder' => $current === '' ? '/' : $current]) ?>"
                   aria-label="Сбросить поиск">
                    <svg class="icon icon--sm"><use href="#i-x"/></svg>
                </a>
            <?php endif ?>

            <div class="dropdown">
                <button type="button" class="btn btn--ghost btn--sm" data-dropdown-toggle>
                    <svg class="icon icon--sm"><use href="#i-sort"/></svg>
                    <?= Fmt::e($query->sortLabel()) ?><span class="text-muted"><?= $query->isDesc() ? '↓' : '↑' ?></span>
                    <svg class="icon icon--sm"><use href="#i-chevron-down"/></svg>
                </button>

                <div class="dropdown__menu dropdown__menu--check">
                    <?php foreach (['created' => 'по дате создания', 'name' => 'по имени', 'type' => 'по типу', 'size' => 'по весу'] as $key => $label): ?>
                        <a class="dropdown__item<?= $query->sort === $key ? ' is-selected' : '' ?>"
                           href="<?= $order(['sort' => $key === 'created' ? null : $key]) ?>">
                            <?= $label ?>
                            <?php if ($query->sort === $key): ?>
                                <svg class="icon icon--sm"><use href="#i-check"/></svg>
                            <?php endif ?>
                        </a>
                    <?php endforeach ?>

                    <div class="dropdown__divider"></div>

                    <a class="dropdown__item" href="<?= $order(['dir' => $query->isDesc() ? 'asc' : null]) ?>">
                        <?= Fmt::e($query->dirToggleLabel()) ?>
                        <svg class="icon icon--sm" style="transform: rotate(<?= $query->isDesc() ? 180 : 0 ?>deg)">
                            <use href="#i-chevron-down"/>
                        </svg>
                    </a>
                </div>
            </div>

            <button type="button" class="btn btn--dark btn--sm" data-modal-open="modal-upload">
                <svg class="icon icon--sm"><use href="#i-upload"/></svg> Загрузить
            </button>
        </form>

        <div class="fm__scroll" data-fm-files>
            <table class="table">
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
                        <td>
                            <button type="button" class="fileline fileline--button" data-action="file:info">
                                <span class="ftype ftype--<?= $kind ?>"><svg class="icon"><use href="#<?= $icon ?>"/></svg></span>
                                <span class="fileline__body">
                                    <span class="fileline__name"><?= Fmt::e($file->name) ?></span>
                                    <span class="fileline__meta">
                                        <?= Fmt::bytes($file->size) ?><span class="dot-sep"></span><?= Fmt::e($file->mime) ?>
                                        <?php if ($current === null && $file->folder !== null): ?>
                                            <span class="dot-sep"></span><?= Fmt::e($file->folder) ?>
                                        <?php endif ?>
                                    </span>
                                </span>
                            </button>
                        </td>
                        <td>
                            <span class="tone chan chan--<?= $file->channel() ?>">/<?= $file->channel() ?></span>
                        </td>
                        <td class="text-sm nowrap">
                            <?php if ($file->expiresAt === null): ?>
                                <span class="text-muted"><?= Fmt::ago($file->createdAt) ?></span>
                            <?php else: ?>
                                <span class="text-warn"><?= Fmt::left($file->expiresAt) ?></span>
                            <?php endif ?>
                        </td>
                        <td style="width:60px">
                            <div class="dropdown">
                                <button class="icon-btn icon-btn--ghost icon-btn--sm" data-dropdown-toggle aria-label="Действия">
                                    <svg class="icon icon--sm"><use href="#i-more-h"/></svg>
                                </button>
                                <div class="dropdown__menu">
                                    <button class="dropdown__item" data-action="file:info">
                                        Подробнее <svg class="icon"><use href="#i-eye"/></svg>
                                    </button>
                                    <button class="dropdown__item" data-copy="<?= Fmt::e($file->publicUrl ?? $file->privateUrl) ?>">
                                        Копировать ссылку <svg class="icon"><use href="#i-copy"/></svg>
                                    </button>
                                    <button class="dropdown__item" data-action="link:open">
                                        Временная ссылка <svg class="icon"><use href="#i-link"/></svg>
                                    </button>
                                    <button class="dropdown__item" data-action="file:rename">
                                        Переименовать <svg class="icon"><use href="#i-edit"/></svg>
                                    </button>
                                    <button class="dropdown__item" data-action="file:move">
                                        Переместить <svg class="icon"><use href="#i-folder"/></svg>
                                    </button>
                                    <button class="dropdown__item" data-action="file:delete">
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
                    <div class="text-sm">Перетащите файлы в окно загрузки — они появятся здесь</div>
                <?php endif ?>
            </div>
        </div>

        <?php wrImport('admin/_pagination') ?>
    </section>
</div>

<?php wrImport('admin/_modals') ?>
