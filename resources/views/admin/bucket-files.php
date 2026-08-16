<?php

use Main\Web\Fmt;

?>

<?php wrImport('admin/_crumbs') ?>

<div class="grid grid--3 mb-3">
    <div class="card">
        <div class="card__header"><div class="card__title">Содержимое</div></div>
        <dl class="kv mt-2">
            <dt>Файлов</dt><dd><?= Fmt::num($bucket['files']) ?></dd>
            <dt>Блобов</dt><dd><?= Fmt::num($bucket['blobs']) ?></dd>
            <dt>Свёрнуто дублей</dt><dd class="text-ok"><?= Fmt::num($bucket['files'] - $bucket['blobs']) ?></dd>
        </dl>
    </div>

    <div class="card">
        <div class="card__header"><div class="card__title">Адрес</div></div>
        <p class="text-sm text-muted mt-2">Публичная отдача идёт по id бакета и slug файла.</p>
        <div class="row mt-2">
            <span class="copyable mono" data-copy="<?= Fmt::e($bucket['id']) ?>">
                <?= Fmt::e(substr($bucket['id'], 0, 18)) ?>…
                <svg class="icon"><use href="#i-copy"/></svg>
            </span>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <div class="card__title">Кэш по умолчанию</div>
            <div class="card__spacer"></div>
            <button class="icon-btn icon-btn--ghost icon-btn--sm" data-action="bucket:cache" aria-label="Изменить">
                <svg class="icon icon--sm"><use href="#i-edit"/></svg>
            </button>
        </div>

        <?php if ($bucket['cache']['visibility'] === null): ?>
            <p class="text-sm text-muted mt-2">Не задано — берётся глобальный дефолт сервиса.</p>
        <?php else: ?>
            <dl class="kv mt-2">
                <dt>Видимость</dt>
                <dd><span class="tone tone--<?= $bucket['cache']['visibility']['name'] === 'PUBLIC' ? 'ok' : 'brand' ?>">
                    <?= Fmt::e($bucket['cache']['visibility']['name']) ?>
                </span></dd>
                <dt>max-age</dt><dd><?= (int) $bucket['cache']['maxAge'] ?> с</dd>
            </dl>
        <?php endif ?>
    </div>
</div>

<div class="grid grid--2 mb-3" style="grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr)">
    <div class="card">
        <div class="card__header">
            <div>
                <div class="card__title">Загрузка</div>
                <div class="card__subtitle">обработка применяется до записи в хранилище</div>
            </div>
            <div class="card__spacer"></div>
            <div class="field" style="min-width:150px">
                <select class="select-native" name="folder" data-upload-folder>
                    <option value="">корень бакета</option>
                    <?php foreach ($folders as $folder): ?>
                        <option value="<?= Fmt::e($folder['name']) ?>"><?= Fmt::e($folder['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>

        <label class="file-drop mt-2" data-upload-drop>
            <svg class="icon"><use href="#i-upload"/></svg>
            <div style="font-weight:600">Перетащите файлы сюда</div>
            <div class="text-sm text-muted" data-upload-hint>или нажмите, чтобы выбрать</div>
            <input type="file" multiple>
        </label>

        <div class="mt-2" data-upload-list></div>
    </div>

    <div class="card" data-image-options>
        <div class="card__header">
            <div>
                <div class="card__title">Картинки</div>
                <div class="card__subtitle">применяется только к изображениям</div>
            </div>
        </div>

        <div class="stack mt-2">
            <div class="field">
                <label class="field__label">Формат</label>
                <select class="select-native" name="format">
                    <option value="ORIGINAL">ORIGINAL — не менять контейнер</option>
                    <option value="WEBP">WEBP</option>
                    <option value="JPEG">JPEG</option>
                    <option value="PNG">PNG</option>
                    <option value="AVIF">AVIF</option>
                </select>
            </div>

            <div class="field">
                <label class="field__label">Качество · <b data-quality-value>82</b></label>
                <input class="range" type="range" name="quality" min="1" max="100" value="82" data-default="82">
                <span class="field__hint">Больше — лучше. У PNG это уровень zlib, а не потери.</span>
            </div>

            <div class="row" style="flex-wrap:nowrap; gap:10px">
                <div class="field w-full">
                    <label class="field__label">Ширина не больше</label>
                    <input class="input" type="number" name="maxWidth" placeholder="2000">
                </div>
                <div class="field w-full">
                    <label class="field__label">Высота не больше</label>
                    <input class="input" type="number" name="maxHeight" placeholder="2000">
                </div>
            </div>

            <div class="alert alert--glass">
                <svg class="icon"><use href="#i-crop"/></svg>
                <div class="alert__body">
                    <div class="alert__title">Что произойдёт</div>
                    <div class="alert__text" data-image-summary></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card__header">
        <div class="card__title">Файлы</div>
        <div class="card__spacer"></div>
        <div class="search-pill">
            <svg class="icon icon--sm"><use href="#i-search"/></svg>
            <input type="search" placeholder="Поиск по имени" data-filter="files">
        </div>
    </div>

    <div class="table-wrap">
        <table class="table" data-sortable>
            <thead>
            <tr>
                <th style="width:34px">
                    <label class="check"><input type="checkbox" data-check-all="files">
                        <span class="check__box"><svg class="icon"><use href="#i-check"/></svg></span>
                    </label>
                </th>
                <th class="sortable">Файл</th>
                <th>Папка</th>
                <th>Обработка</th>
                <th class="num sortable">Размер</th>
                <th>Канал</th>
                <th>Срок</th>
                <th></th>
            </tr>
            </thead>
            <tbody data-rows="files">
            <?php foreach ($files as $file): ?>
                <?php [$kind, $icon] = Fmt::kind($file['content']['mime']) ?>
                <tr data-row="file" data-id="<?= Fmt::e($file['id']) ?>" data-name="<?= Fmt::e($file['name']) ?>">
                    <td>
                        <label class="check"><input type="checkbox" data-check="files">
                            <span class="check__box"><svg class="icon"><use href="#i-check"/></svg></span>
                        </label>
                    </td>
                    <td>
                        <div class="fileline">
                            <span class="ftype ftype--<?= $kind ?>"><svg class="icon"><use href="#<?= $icon ?>"/></svg></span>
                            <span class="fileline__body">
                                <span class="fileline__name"><?= Fmt::e($file['name']) ?></span>
                                <span class="fileline__meta">
                                    <span class="mono"><?= Fmt::e($file['id']) ?></span>
                                    <span class="dot-sep"></span><?= Fmt::e($file['content']['mime']) ?>
                                    <?php if ($file['deduplicated']): ?>
                                        <span class="dot-sep"></span><span class="text-ok">дедуп</span>
                                    <?php endif ?>
                                </span>
                            </span>
                        </div>
                    </td>
                    <td>
                        <?php if ($file['folder'] === null): ?>
                            <span class="text-muted text-sm">корень</span>
                        <?php else: ?>
                            <span class="tone tone--mute">
                                <svg class="icon"><use href="#i-folder"/></svg> <?= Fmt::e($file['folder']) ?>
                            </span>
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if ($file['processed']['applied'] ?? false): ?>
                            <span class="tone tone--brand" title="<?= Fmt::e(implode(', ', $file['processed']['operations'])) ?>">
                                <svg class="icon"><use href="#i-zap"/></svg>
                                <?= Fmt::bytes($file['processed']['source']['size']) ?> → <?= Fmt::bytes($file['processed']['result']['size']) ?>
                            </span>
                        <?php else: ?>
                            <span class="tone tone--mute"><?= Fmt::e($file['processed']['reason'] ?? '—') ?></span>
                        <?php endif ?>
                    </td>
                    <td class="num nowrap"><?= Fmt::bytes($file['content']['size']) ?></td>
                    <td>
                        <span class="tone chan chan--<?= $file['public'] ? 'o' : 'p' ?>">/<?= $file['public'] ? 'o' : 'p' ?></span>
                    </td>
                    <td class="text-sm nowrap">
                        <?php if ($file['expiresAt'] === null): ?>
                            <span class="text-muted">бессрочно</span>
                        <?php else: ?>
                            <span class="text-warn"><?= Fmt::left($file['expiresAt']) ?></span>
                        <?php endif ?>
                    </td>
                    <td>
                        <div class="dropdown">
                            <button class="icon-btn icon-btn--ghost icon-btn--sm" data-dropdown-toggle aria-label="Действия">
                                <svg class="icon icon--sm"><use href="#i-more-h"/></svg>
                            </button>
                            <div class="dropdown__menu">
                                <button class="dropdown__item" data-copy="http://localhost:9090/o/<?= Fmt::e($bucket['id']) ?>/<?= Fmt::e($file['id']) ?>">
                                    Копировать ссылку <svg class="icon"><use href="#i-copy"/></svg>
                                </button>
                                <button class="dropdown__item" data-action="link:open"
                                        data-slug="<?= Fmt::e($file['id']) ?>" data-name="<?= Fmt::e($file['name']) ?>">
                                    Временная ссылка <svg class="icon"><use href="#i-link"/></svg>
                                </button>
                                <button class="dropdown__item" data-action="file:delete"
                                        data-id="<?= Fmt::e($file['id']) ?>" data-name="<?= Fmt::e($file['name']) ?>">
                                    Удалить <svg class="icon"><use href="#i-trash"/></svg>
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <div class="empty" data-empty="files"<?= $files === [] ? '' : ' hidden' ?>>
        <svg class="icon"><use href="#i-file"/></svg>
        <div class="empty__title">Пока пусто</div>
        <div class="text-sm">Загрузите первый файл — он появится здесь</div>
    </div>
</div>

<?php wrImport('admin/_modals') ?>
