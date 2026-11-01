<?php

use Main\Web\Fmt;
use Main\Web\Query;
use Main\Web\Sorting;
use Main\Web\Ui;

/** @var \Main\Controllers\Requests\PanelListRequest $query */
$base = '/admin/ui/buckets/' . $bucket->id . '/files';
$link = static fn(array $params) => Fmt::e(Query::url($base, $params + [
    'search' => $query->search,
    'sort' => $query->sort === 'created' ? null : $query->sort,
    'dir' => $query->dir === 'desc' ? null : $query->dir,
]));
$current = $query->folderFilter();

/** Тот же список, но с другим порядком: папка, поиск и страница сохраняются. */
$sorting = new Sorting(
    [
        'created' => ['sort.by.date', 'by date'],
        'name' => ['sort.by.name', 'by name'],
        'type' => ['sort.by.type', 'by type'],
        'size' => ['sort.by.size', 'by size'],
    ],
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
    <aside class="fm__side" aria-label="Folders" data-i18n-attr="aria-label:files.folders">
        <div class="fm__head">
            <span class="fm__title" data-i18n="files.folders">Folders</span>
            <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-modal-open="modal-folder"
                    data-i18n-attr="aria-label:files.newFolder;title:files.newFolder" aria-label="New folder" title="New folder">
                <svg class="icon icon--sm"><use href="#i-plus"/></svg>
            </button>
        </div>

        <div class="fm__scroll" data-rows="folders">
            <a class="fm__folder<?= $current === null ? ' is-active' : '' ?>" href="<?= $link(['folder' => null]) ?>"<?= $current === null ? ' aria-current="true"' : '' ?>>
                <svg class="icon"><use href="#i-layers"/></svg>
                <span class="fm__folder-name" data-i18n="files.all">All files</span>
                <span class="fm__count"><?= Fmt::num($bucket->files) ?></span>
            </a>

            <a class="fm__folder<?= $current === '' ? ' is-active' : '' ?>" href="<?= $link(['folder' => '/']) ?>"<?= $current === '' ? ' aria-current="true"' : '' ?>>
                <svg class="icon"><use href="#i-file"/></svg>
                <span class="fm__folder-name" data-i18n="files.root">Root</span>
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
                        $marks[] = ['i-lock', 'brand', 'folder.mark.private', [], 'token only'];
                    }
                    if ($folder->hasRetention()) {
                        $marks[] = [
                            'i-clock',
                            'warn',
                            'folder.mark.retention',
                            ['value' => '@' . $folder->retentionKey()],
                            'retention: ' . $folder->retentionLabel(),
                        ];
                    }
                    if ($folder->hasCache()) {
                        $vis = $folder->cacheVisibility;
                        $age = $folder->cacheMaxAge === null ? null : $folder->cacheMaxAge . ' s';
                        $markKey = match (true) {
                            $vis !== null && $age !== null => 'folder.mark.cacheBoth',
                            $vis !== null => 'folder.mark.cacheVis',
                            default => 'folder.mark.cacheAge',
                        };
                        $markVars = [];
                        if ($vis !== null) {
                            $markVars['vis'] = '@' . $vis->labelKey();
                        }
                        if ($age !== null) {
                            $markVars['age'] = $age;
                        }
                        $marks[] = ['i-zap', 'temp', $markKey, $markVars, 'own cache: ' . $folder->cacheLabel()];
                    }
                    ?>
                    <a class="fm__folder<?= $current === $folder->name ? ' is-active' : '' ?>"
                       href="<?= $link(['folder' => $folder->name]) ?>"<?= $current === $folder->name ? ' aria-current="true"' : '' ?>
                       title="<?= Fmt::e($folder->name . ($marks === [] ? '' : ' — ' . implode(', ', array_column($marks, 4)))) ?>">
                        <svg class="icon"><use href="#i-folder"/></svg>
                        <span class="fm__folder-name" data-folder-name><?= Fmt::e($folder->name) ?></span>
                        <span class="fm__badges">
                            <?php foreach ($marks as [$icon, $tone, $markKey, $markVars, $hint]): ?>
                                <svg class="icon icon--sm text-<?= $tone ?>" role="img"
                                     data-i18n-attr="aria-label:<?= Fmt::e($markKey) ?>"
                                     <?= $markVars === [] ? '' : 'data-i18n-vars="' . Fmt::e((string) json_encode($markVars)) . '"' ?>
                                     aria-label="<?= Fmt::e($hint) ?>"><use href="#<?= $icon ?>"/></svg>
                            <?php endforeach ?>
                        </span>
                        <span class="fm__count"><?= Fmt::num($folder->files) ?></span>
                    </a>

                    <div class="dropdown fm__folder-menu">
                        <?= Ui::rowMenu('i-more-v', 'icon-btn--sm') ?>
                        <div class="dropdown__menu" role="menu">
                            <button type="button" class="dropdown__item" data-action="folder:edit" data-name="<?= Fmt::e($folder->name) ?>">
                                <span data-i18n="common.edit">Edit</span> <svg class="icon"><use href="#i-edit"/></svg>
                            </button>
                            <button type="button" class="dropdown__item" data-action="folder:cache" data-name="<?= Fmt::e($folder->name) ?>"
                                    data-max-age="<?= $folder->cacheMaxAge ?? '' ?>"
                                    data-visibility="<?= Fmt::e($folder->cacheVisibility?->name ?? '') ?>">
                                <span data-i18n="bucket.cachePolicy">Cache policy</span> <svg class="icon"><use href="#i-clock"/></svg>
                            </button>
                            <button type="button" class="dropdown__item" data-action="folder:delete"
                                    data-name="<?= Fmt::e($folder->name) ?>" data-files="<?= $folder->files ?>">
                                <span data-i18n="common.delete">Delete</span> <svg class="icon"><use href="#i-trash"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>

            <?php if ($folders === []): ?>
                <p class="text-sm text-muted fm__empty" data-folders-empty data-i18n="files.noFolders">
                    No folders — every file is in the root
                </p>
            <?php endif ?>
        </div>

        <button type="button" class="btn btn--ghost btn--sm fm__add" data-modal-open="modal-folder">
            <svg class="icon icon--sm"><use href="#i-plus"/></svg> <span data-i18n="files.newFolder">New folder</span>
        </button>
    </aside>

    <!-- ─────────── файлы ─────────── -->
    <section class="fm__main" aria-label="Files" data-i18n-attr="aria-label:nav.files">
        <div class="fm__head">
            <div class="fm__where">
                <svg class="icon"><use href="#<?= $current === null ? 'i-layers' : ($current === '' ? 'i-file' : 'i-folder') ?>"/></svg>
                <?php if ($current === null): ?>
                    <b data-i18n="files.all">All files</b>
                <?php elseif ($current === ''): ?>
                    <b data-i18n="files.bucketRoot">Bucket root</b>
                <?php else: ?>
                    <b><?= Fmt::e($current) ?></b>
                <?php endif ?>
                <span class="text-sm text-muted">
                    <span data-i18n-plural="files.count" data-count="<?= $meta->total ?>"><?= $meta->total ?> files</span>
                </span>
            </div>

            <?php /* порядок задаётся отдельной кнопкой — при поиске его нельзя терять */ ?>
            <?= Ui::search($base, 'files.searchPlaceholder', 'Search by name', $query->search, [
                'folder' => $current === null ? null : ($current === '' ? '/' : $current),
                'sort' => $query->sort,
                'dir' => $query->dir,
            ]) ?>

            <?php if (($query->search ?? '') !== ''): ?>
                <?= Ui::searchReset($link(['search' => null, 'folder' => $current === '' ? '/' : $current])) ?>
            <?php endif ?>

            <?= $sorting->menu() ?>

            <button type="button" class="btn btn--primary btn--sm" data-modal-open="modal-upload">
                <svg class="icon icon--sm"><use href="#i-upload"/></svg> <span data-i18n="files.upload">Upload</span>
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
                                        <span data-fmt="bytes" data-value="<?= $file->size ?>"><?= Fmt::bytes($file->size) ?></span><span class="dot-sep"></span><?= Fmt::e($file->mime) ?>
                                        <?php if ($current === null && $file->folder !== null): ?>
                                            <span class="dot-sep"></span><?= Fmt::e($file->folder) ?>
                                        <?php endif ?>
                                    </span>
                                </span>
                            </button>
                        </td>
                        <td data-label="Channel" data-i18n-attr="data-label:table.channel" data-half>
                            <span class="tone chan chan--<?= $file->channel() ?>">/<?= $file->channel() ?></span>
                        </td>
                        <td data-label="<?= $file->expiresAt === null ? 'Uploaded' : 'Expiry' ?>"
                            data-i18n-attr="data-label:<?= $file->expiresAt === null ? 'table.uploaded' : 'table.expiry' ?>"
                            data-half class="text-sm nowrap">
                            <?php if ($file->expiresAt === null): ?>
                                <span class="text-muted" data-fmt="ago" data-value="<?= Fmt::e($file->createdAt) ?>"><?= Fmt::e(Fmt::ago($file->createdAt)) ?></span>
                            <?php else: ?>
                                <span class="text-warn" data-fmt="left" data-value="<?= Fmt::e($file->expiresAt) ?>"><?= Fmt::e(Fmt::left($file->expiresAt)) ?></span>
                            <?php endif ?>
                        </td>
                        <td data-actions>
                            <div class="dropdown">
                                <?= Ui::rowMenu() ?>
                                <div class="dropdown__menu" role="menu">
                                    <button type="button" class="dropdown__item" data-action="file:info">
                                        <span data-i18n="file.details">Details</span> <svg class="icon"><use href="#i-eye"/></svg>
                                    </button>
                                    <button type="button" class="dropdown__item" data-copy="<?= Fmt::e($file->publicUrl ?? $file->privateUrl) ?>">
                                        <span data-i18n="file.copyLink">Copy link</span> <svg class="icon"><use href="#i-copy"/></svg>
                                    </button>
                                    <button type="button" class="dropdown__item" data-action="link:open">
                                        <span data-i18n="file.tempLink">Temporary link</span> <svg class="icon"><use href="#i-link"/></svg>
                                    </button>
                                    <button type="button" class="dropdown__item" data-action="file:rename">
                                        <span data-i18n="file.rename">Rename</span> <svg class="icon"><use href="#i-edit"/></svg>
                                    </button>
                                    <button type="button" class="dropdown__item" data-action="file:move">
                                        <span data-i18n="file.move">Move</span> <svg class="icon"><use href="#i-folder"/></svg>
                                    </button>
                                    <button type="button" class="dropdown__item" data-action="file:delete">
                                        <span data-i18n="common.delete">Delete</span> <svg class="icon"><use href="#i-trash"/></svg>
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
                    <div class="empty__title" data-i18n="empty.nothing">Nothing found</div>
                    <div class="text-sm" data-i18n="files.empty.search" data-i18n-vars="<?= Fmt::e((string) json_encode(['query' => $query->search])) ?>">Nothing here matches “<?= Fmt::e($query->search) ?>”</div>
                <?php else: ?>
                    <div class="empty__title" data-i18n="files.empty.title">Empty</div>
                    <div class="text-sm" data-i18n="files.empty.hint">Upload the first file</div>
                <?php endif ?>
            </div>
        </div>

        <?php wrImport('admin/_pagination') ?>
    </section>
</div>
