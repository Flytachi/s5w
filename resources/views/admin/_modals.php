<?php

use Main\Web\Fmt;
use Main\Web\Ui;

/**
 * Модалки — нативные <dialog>, лежат прямо в <body> (подключаются из layout),
 * поэтому подложка накрывает весь экран, а не только колонку содержимого.
 *
 * Формы уходят через fetch, страница не перезагружается: `data-api` задаёт
 * метод и адрес, `data-done` — что сделать с ответом. `{bucket}` подставляется
 * из <body>, `{name}` и `{slug}` — из данных, с которыми модалку открыли.
 */

$folders = wrData('folders') ?? [];
?>

<!-- ================= Загрузка файлов ================= -->
<dialog class="modal modal--upload modal--full" id="modal-upload" aria-labelledby="modal-upload-title">
    <div class="modal__inner">
        <header class="modal__header">
            <?= Ui::modalIcon('brand', 'i-upload') ?>
            <h2 class="modal__title" id="modal-upload-title" data-i18n="modal.upload.title">Upload files</h2>
            <?= Ui::modalClose() ?>
        </header>

        <div class="modal__body">
            <div class="stack">
                <div class="field">
                    <label class="field__label" for="upload-folder" data-i18n="modal.upload.where">Where to put it</label>
                    <select class="select-native" id="upload-folder" data-upload-folder>
                        <option value="" data-i18n="modal.upload.rootOption">bucket root — public and permanent</option>
                        <?php foreach ($folders as $folder): ?>
                            <?php
                            $optVars = [
                                'name' => $folder->name,
                                'vis' => $folder->public ? '@folder.public' : '@folder.private',
                            ];
                            if ($folder->hasRetention()) {
                                $optVars['ret'] = '@' . $folder->retentionKey();
                            }
                            ?>
                            <option value="<?= Fmt::e($folder->name) ?>"
                                    data-i18n="<?= $folder->hasRetention() ? 'modal.upload.folderOptRet' : 'modal.upload.folderOpt' ?>"
                                    data-i18n-vars="<?= Fmt::e((string) json_encode($optVars)) ?>">
                                <?= Fmt::e($folder->name) ?> — <?= $folder->public ? 'public' : 'token only' ?><?=
                                    $folder->hasRetention() ? ', ' . Fmt::e($folder->retentionLabel()) : '' ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <span class="field__hint" data-i18n="modal.upload.hint">The file inherits visibility and retention from the folder.</span>
                </div>

                <label class="file-drop" data-upload-drop>
                    <svg class="icon"><use href="#i-upload"/></svg>
                    <div class="file-drop__title" data-i18n="modal.upload.drop">Drag files here</div>
                    <div class="text-sm text-muted" data-upload-hint data-i18n="modal.upload.pick">or click to choose</div>
                    <input type="file" multiple>
                </label>

                <!-- Настройки обработки живут на карточках картинок: их дорисовывает
                     features/upload.js, когда видит, что файл — картинка. -->
                <div data-upload-list></div>
            </div>
        </div>

        <footer class="modal__footer">
            <span class="text-sm text-muted grow" data-upload-total></span>
            <button type="button" class="btn btn--ghost" data-modal-close data-i18n="common.close">Close</button>
            <button type="button" class="btn btn--primary" data-upload-start disabled>
                <svg class="icon icon--sm"><use href="#i-upload"/></svg> <span data-i18n="files.upload">Upload</span>
            </button>
        </footer>
    </div>
</dialog>

<!-- ================= Карточка файла ================= -->
<dialog class="modal modal--drawer" id="drawer-file" aria-labelledby="drawer-file-title">
    <div class="modal__inner">
        <header class="modal__header">
            <span class="ftype" data-file-icon><svg class="icon"><use href="#i-file"/></svg></span>
            <div class="grow">
                <h2 class="modal__title break" id="drawer-file-title" data-file-name></h2>
                <div class="card__subtitle mono" data-file-slug></div>
            </div>
            <?= Ui::modalClose() ?>
        </header>

        <div class="modal__body">
            <div class="row mt-1" data-file-badges></div>

            <dl class="kv mt-3">
                <dt data-i18n="modal.file.size">Size</dt><dd data-file-size></dd>
                <dt data-i18n="modal.file.type">Type</dt><dd class="mono" data-file-mime></dd>
                <dt data-i18n="modal.file.folder">Folder</dt><dd data-file-folder></dd>
                <dt data-i18n="table.uploaded">Uploaded</dt><dd data-file-created></dd>
                <dt data-i18n="modal.file.retention">Retention</dt><dd data-file-expires></dd>
            </dl>

            <div class="field__label mt-3" data-i18n="modal.file.content">Content</div>
            <div class="secret mt-1">
                <span data-file-hash></span>
                <button type="button" class="icon-btn icon-btn--sm" data-file-hash-copy data-i18n-attr="aria-label:modal.file.copyHash" aria-label="Copy hash">
                    <svg class="icon icon--sm"><use href="#i-copy"/></svg>
                </button>
            </div>

            <div class="field__label mt-3" data-i18n="modal.file.permanent">Permanent addresses</div>
            <div class="stack mt-1" data-file-urls></div>

            <div class="field__label mt-3" data-i18n="links.title">Temporary links</div>
            <p class="text-sm text-muted" data-i18n="modal.file.linksNote">Only revocable ones and ones with a limit.</p>
            <div class="stack mt-1" data-file-links></div>
        </div>

        <footer class="modal__footer">
            <button type="button" class="btn btn--ghost btn--sm" data-action="file:rename" data-from-drawer>
                <svg class="icon icon--sm"><use href="#i-edit"/></svg> <span data-i18n="modal.file.name">Name</span>
            </button>
            <button type="button" class="btn btn--ghost btn--sm" data-action="file:move" data-from-drawer>
                <svg class="icon icon--sm"><use href="#i-folder"/></svg> <span data-i18n="modal.file.folder">Folder</span>
            </button>
            <button type="button" class="btn btn--ghost btn--sm" data-action="link:open" data-from-drawer>
                <svg class="icon icon--sm"><use href="#i-link"/></svg> <span data-i18n="modal.file.link">Link</span>
            </button>
            <button type="button" class="btn btn--danger btn--sm" data-action="file:delete" data-from-drawer>
                <svg class="icon icon--sm"><use href="#i-trash"/></svg> <span data-i18n="common.delete">Delete</span>
            </button>
        </footer>
    </div>
</dialog>

<!-- ================= Переименование и перенос ================= -->
<dialog class="modal" id="modal-file" aria-labelledby="modal-file-title">
    <form class="modal__inner" data-api="PUT /admin/buckets/{bucket}/files/{slug}" data-done="file:updated">
        <header class="modal__header">
            <?= Ui::modalIcon('brand', 'i-edit') ?>
            <h2 class="modal__title" id="modal-file-title" data-file-form-title data-i18n="file.rename">Rename</h2>
            <?= Ui::modalClose() ?>
        </header>

        <div class="modal__body">
            <div class="stack">
                <div class="field" data-file-field="name">
                    <label class="field__label" for="file-name" data-i18n="modal.file.name">Name</label>
                    <input class="input" id="file-name" name="name" autocomplete="off">
                    <span class="field__hint" data-i18n="modal.file.nameHint">Unique inside the folder. The file downloads under this name.</span>
                </div>

                <div class="field" data-file-field="folder">
                    <label class="field__label" for="file-folder" data-i18n="modal.file.folder">Folder</label>
                    <select class="select-native" id="file-folder" name="folder">
                        <option value="" data-i18n="files.bucketRoot">Bucket root</option>
                        <?php foreach ($folders as $folder): ?>
                            <option value="<?= Fmt::e($folder->name) ?>"><?= Fmt::e($folder->name) ?></option>
                        <?php endforeach ?>
                    </select>
                    <span class="field__hint" data-i18n="modal.file.moveHint">On move the file takes the visibility and retention of the new folder.</span>
                </div>
            </div>
        </div>

        <footer class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close data-i18n="common.cancel">Cancel</button>
            <button type="submit" class="btn btn--primary" data-i18n="common.save">Save</button>
        </footer>
    </form>
</dialog>

<!-- ================= Новый бакет ================= -->
<dialog class="modal" id="modal-bucket" aria-labelledby="modal-bucket-title">
    <form class="modal__inner" data-api="POST /admin/buckets" data-done="bucket:created">
        <header class="modal__header">
            <?= Ui::modalIcon('brand', 'i-database') ?>
            <h2 class="modal__title" id="modal-bucket-title" data-i18n="buckets.new">New bucket</h2>
            <?= Ui::modalClose() ?>
        </header>

        <div class="modal__body">
            <p class="modal__text"><span data-i18n="modal.bucket.noteA">The bucket is prepared in the background and becomes</span> <b>ACTIVE</b> <span data-i18n="modal.bucket.noteB">in a moment.</span></p>

            <div class="stack">
                <div class="field">
                    <label class="field__label" for="bucket-name" data-i18n="modal.file.name">Name</label>
                    <input class="input" id="bucket-name" name="name" placeholder="media-lab" autocomplete="off">
                    <span class="field__hint" data-i18n="modal.bucket.nameHint">Unique, 1…100 characters.</span>
                </div>

                <div class="field">
                    <label class="field__label" for="bucket-description" data-i18n="overview.description">Description</label>
                    <input class="input" id="bucket-description" name="description" data-i18n-attr="placeholder:modal.bucket.descPlaceholder" placeholder="what this bucket is for" autocomplete="off">
                </div>

                <div class="field">
                    <label class="field__label" for="bucket-quota" data-i18n="table.quota">Quota</label>
                    <div class="quota-input">
                        <input class="input" id="bucket-quota" type="number" name="quota" value="512" inputmode="numeric">
                        <select class="select-native" name="unit" data-i18n-attr="aria-label:modal.bucket.unit" aria-label="Unit">
                            <option value="1048576" data-i18n="unit.mb">MB</option>
                            <option value="1073741824" data-i18n="unit.gb">GB</option>
                        </select>
                    </div>
                    <span class="field__hint" data-i18n="modal.bucket.quotaHint">Counted by stored bytes — deduplication saves quota.</span>
                </div>
            </div>
        </div>

        <footer class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close data-i18n="common.cancel">Cancel</button>
            <button type="submit" class="btn btn--primary" data-i18n="common.create">Create</button>
        </footer>
    </form>
</dialog>

<!-- ================= Папка ================= -->
<dialog class="modal" id="modal-folder" aria-labelledby="modal-folder-title">
    <form class="modal__inner" data-api="POST /admin/buckets/{bucket}/folders" data-done="folder:created">
        <header class="modal__header">
            <?= Ui::modalIcon('ok', 'i-folder') ?>
            <h2 class="modal__title" id="modal-folder-title" data-i18n="files.newFolder">New folder</h2>
            <?= Ui::modalClose() ?>
        </header>

        <div class="modal__body">
            <div class="stack">
                <div class="field">
                    <label class="field__label" for="folder-name" data-i18n="modal.file.name">Name</label>
                    <input class="input" id="folder-name" name="name" placeholder="photos" autocomplete="off">
                    <span class="field__hint" data-i18n="modal.folder.nameHint">Letters, digits, space, dot, hyphen and underscore.</span>
                </div>

                <label class="switch">
                    <input type="checkbox" name="public">
                    <span class="switch__track"></span>
                    <span><span data-i18n="modal.folder.publicA">Public — files are served through</span> <span class="mono">/o</span> <span data-i18n="modal.folder.publicB">without a key</span></span>
                </label>

                <div class="field">
                    <label class="field__label" for="folder-retention" data-i18n="modal.file.retention">Retention</label>
                    <select class="select-native" id="folder-retention" name="retention">
                        <option value="0" data-i18n="retention.NONE">no expiry</option>
                        <option value="1" data-i18n="retention.DAY">a day</option>
                        <option value="2" data-i18n="retention.WEEK">a week</option>
                        <option value="3" data-i18n="retention.MONTH">a month</option>
                        <option value="4" data-i18n="retention.QUARTER">three months</option>
                        <option value="5" data-i18n="retention.HALF_YEAR">half a year</option>
                        <option value="6" data-i18n="retention.YEAR">a year</option>
                    </select>
                    <span class="field__hint" data-i18n="modal.folder.retentionHint">
                        Each file has its own deadline counted from its upload. Changing it recalculates the files already stored.
                    </span>
                </div>
            </div>
        </div>

        <footer class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close data-i18n="common.cancel">Cancel</button>
            <button type="submit" class="btn btn--primary" data-i18n="common.create">Create</button>
        </footer>
    </form>
</dialog>

<!-- ================= Политика кэша ================= -->
<dialog class="modal modal--cache modal--full" id="modal-cache" aria-labelledby="modal-cache-title">
    <form class="modal__inner" data-api="PATCH /admin/buckets/{bucket}/folders/{name}/cache" data-done="cache:saved">
        <header class="modal__header">
            <?= Ui::modalIcon('brand', 'i-clock') ?>
            <h2 class="modal__title" id="modal-cache-title"><span data-i18n="bucket.cachePolicy">Cache policy</span> <span data-cache-target class="text-muted"></span></h2>
            <?= Ui::modalClose() ?>
        </header>

        <div class="modal__body">
            <p class="modal__text" data-cache-intro></p>

            <div class="cache-grid">
                <div class="stack">
                    <fieldset class="field">
                        <legend class="field__label" data-i18n="modal.cache.whoTitle">Who may keep a copy</legend>

                        <div class="choices">
                            <label class="choice" data-cache-opt="auto">
                                <input type="radio" name="visibility" value="" checked>
                                <span class="radio__dot"></span>
                                <span class="choice__body">
                                    <span class="choice__title" data-cache-auto-title></span>
                                    <span class="choice__text" data-cache-auto-text></span>
                                </span>
                            </label>

                            <label class="choice" data-cache-opt="public">
                                <input type="radio" name="visibility" value="1">
                                <span class="radio__dot"></span>
                                <span class="choice__body">
                                    <span class="choice__title" data-i18n="modal.cache.publicTitle">Everyone — browser and CDN</span>
                                    <span class="choice__text" data-i18n="modal.cache.publicText">
                                        Both the browser and the CDN with proxies keep a copy. For what is already
                                        open to everyone: avatars, covers, site images.
                                    </span>
                                </span>
                            </label>

                            <label class="choice" data-cache-opt="private">
                                <input type="radio" name="visibility" value="2">
                                <span class="radio__dot"></span>
                                <span class="choice__body">
                                    <span class="choice__title" data-i18n="modal.cache.privateTitle">Only the client browser</span>
                                    <span class="choice__text" data-i18n="modal.cache.privateText">
                                        The copy stays only with whoever downloaded it; CDNs and proxies are not
                                        allowed to keep it. For personal files: invoices, statements, profile photos.
                                    </span>
                                </span>
                            </label>

                            <label class="choice" data-cache-opt="none">
                                <input type="radio" name="visibility" value="3">
                                <span class="radio__dot"></span>
                                <span class="choice__body">
                                    <span class="choice__title" data-i18n="modal.cache.noneTitle">Nobody</span>
                                    <span class="choice__text" data-i18n="modal.cache.noneText">
                                        Nobody keeps a copy — the file is downloaded anew every time. For what must
                                        not be left on someone else's device: passports, contracts.
                                    </span>
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    <div class="field" data-cache-ttl>
                        <label class="field__label" for="cache-max-age" data-i18n="modal.cache.ttlLabel">How long to keep the copy, seconds</label>
                        <input class="input" id="cache-max-age" type="number" name="maxAge" min="0" max="31536000" inputmode="numeric">
                        <div class="cache-presets mt-1" data-cache-presets>
                            <button type="button" class="btn btn--ghost btn--sm" data-ttl="3600" data-i18n="modal.cache.hour">an hour</button>
                            <button type="button" class="btn btn--ghost btn--sm" data-ttl="86400" data-i18n="modal.cache.day">a day</button>
                            <button type="button" class="btn btn--ghost btn--sm" data-ttl="31536000" data-i18n="retention.YEAR">a year</button>
                            <button type="button" class="btn btn--ghost btn--sm" data-ttl="0" data-i18n="modal.cache.never">do not cache</button>
                            <button type="button" class="btn btn--ghost btn--sm" data-ttl="" data-i18n="cache.default">default</button>
                        </div>
                        <span class="field__hint" data-cache-ttl-hint></span>
                    </div>
                </div>

                <aside class="stack">
                    <div class="cache-preview" data-cache-preview aria-live="polite"></div>

                    <div class="alert alert--outline">
                        <svg class="icon"><use href="#i-info"/></svg>
                        <div class="alert__body">
                            <div class="alert__text" data-i18n="modal.cache.note">
                                A private file never gets public, the /p and /t channels always serve private,
                                and the cache lifetime never outlives the file or the link.
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        <footer class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close data-i18n="common.cancel">Cancel</button>
            <button type="submit" class="btn btn--primary" data-i18n="common.save">Save</button>
        </footer>
    </form>
</dialog>

<!-- ================= Выпуск токена ================= -->
<dialog class="modal modal--token" id="modal-token" aria-labelledby="modal-token-title">
    <form class="modal__inner" data-api="POST /admin/buckets/{bucket}/tokens" data-done="token:created">
        <header class="modal__header">
            <?= Ui::modalIcon('brand', 'i-key') ?>
            <h2 class="modal__title" id="modal-token-title" data-i18n="modal.token.title">Issue a token</h2>
            <?= Ui::modalClose() ?>
        </header>

        <div class="modal__body">
            <div class="stack">
                <div class="field">
                    <label class="field__label" for="token-name" data-i18n="modal.token.name">Name</label>
                    <input class="input" id="token-name" name="name" data-i18n-attr="placeholder:modal.token.namePlaceholder" placeholder="mobile app" autocomplete="off">
                    <span class="field__hint" data-i18n="modal.token.nameHint">So you know later what to revoke.</span>
                </div>

                <fieldset class="field">
                    <legend class="field__label" data-i18n="modal.token.opens">What the token opens</legend>

                    <div class="choices choices--pair">
                        <label class="choice">
                            <input type="radio" name="access" value="1" checked>
                            <span class="radio__dot"></span>
                            <span class="choice__body">
                                <span class="choice__title" data-i18n="access.BASIC">delivery only</span>
                                <span class="choice__text" data-i18n="modal.token.basicText">
                                    Fetches private files over /p by a known address. It sees no file list
                                    and changes nothing.
                                </span>
                            </span>
                        </label>

                        <label class="choice">
                            <input type="radio" name="access" value="2">
                            <span class="radio__dot"></span>
                            <span class="choice__body">
                                <span class="choice__title" data-i18n="access.FULL">full access</span>
                                <span class="choice__text" data-i18n="modal.token.fullText">
                                    All of the above plus /v1: listing and uploading files, folders, temporary
                                    links. For your own backend — not for a browser.
                                </span>
                            </span>
                        </label>
                    </div>
                </fieldset>

                <div class="field">
                    <label class="field__label" for="token-expires" data-i18n="table.expiry">Expiry</label>
                    <select class="select-native" id="token-expires" name="expiresInDays">
                        <option value="" data-i18n="time.forever">permanent</option>
                        <option value="30" data-i18n="modal.token.days30">30 days</option>
                        <option value="90" data-i18n="modal.token.days90">90 days</option>
                        <option value="365" data-i18n="retention.YEAR">a year</option>
                    </select>
                    <span class="field__hint" data-i18n="modal.token.expiryHint">
                        After expiry the token answers 403 — it cannot be extended, only reissued.
                    </span>
                </div>
            </div>
        </div>

        <footer class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close data-i18n="common.cancel">Cancel</button>
            <button type="submit" class="btn btn--primary" data-i18n="modal.issue">Issue</button>
        </footer>
    </form>
</dialog>

<!-- ================= Показ секрета ================= -->
<dialog class="modal" id="modal-secret" aria-labelledby="modal-secret-title">
    <div class="modal__inner">
        <header class="modal__header">
            <?= Ui::modalIcon('ok', 'i-check-circle') ?>
            <h2 class="modal__title" id="modal-secret-title" data-secret-title data-i18n="modal.secret.title">Done</h2>
        </header>

        <div class="modal__body">
            <p class="modal__text" data-secret-note data-i18n="modal.secret.note">
                The value is shown once. Copy it now.
            </p>

            <div class="secret mt-2">
                <span data-secret-value></span>
                <button type="button" class="icon-btn icon-btn--sm" data-secret-copy data-i18n-attr="aria-label:modal.secret.copy" aria-label="Copy">
                    <svg class="icon icon--sm"><use href="#i-copy"/></svg>
                </button>
            </div>

            <div class="field mt-3" data-secret-check hidden>
                <span class="field__label" data-i18n="modal.secret.check">Check it right now</span>
                <div class="secret">
                    <span data-secret-curl></span>
                    <button type="button" class="icon-btn icon-btn--sm" data-secret-curl-copy data-i18n-attr="aria-label:modal.secret.copyCmd" aria-label="Copy the command">
                        <svg class="icon icon--sm"><use href="#i-copy"/></svg>
                    </button>
                </div>
                <span class="field__hint" data-secret-check-hint></span>
            </div>
        </div>

        <footer class="modal__footer">
            <button type="button" class="btn btn--primary" data-modal-close data-i18n="modal.secret.title">Done</button>
        </footer>
    </div>
</dialog>

<!-- ================= Временная ссылка ================= -->
<dialog class="modal" id="modal-link" aria-labelledby="modal-link-title">
    <form class="modal__inner" data-api="POST /admin/buckets/{bucket}/files/{slug}/link" data-done="link:created">
        <header class="modal__header">
            <?= Ui::modalIcon('temp', 'i-link') ?>
            <h2 class="modal__title" id="modal-link-title" data-i18n="file.tempLink">Temporary link</h2>
            <?= Ui::modalClose() ?>
        </header>

        <div class="modal__body">
            <p class="modal__text break"><span data-i18n="modal.link.forFile">for file</span> <b data-link-file></b></p>

            <div class="stack">
                <div class="field">
                    <label class="field__label" for="link-ttl" data-i18n="modal.link.ttl">How long it works</label>
                    <select class="select-native" id="link-ttl" name="ttl">
                        <option value="300" data-i18n="modal.link.min5">5 minutes</option>
                        <option value="900" data-i18n="modal.link.min15">15 minutes</option>
                        <option value="1800" data-i18n="modal.link.min30">30 minutes</option>
                        <option value="3600" selected data-i18n="modal.cache.hour">an hour</option>
                        <option value="86400" data-i18n="modal.cache.day">a day</option>
                        <option value="604800" data-i18n="retention.WEEK">a week</option>
                        <option value="2592000" data-i18n="retention.MONTH">a month</option>
                    </select>
                    <span class="field__hint" data-i18n="modal.link.ttlHint">
                        After expiry the link stops opening. It cannot be extended — only reissued.
                    </span>
                </div>

                <fieldset class="field">
                    <legend class="field__label" data-i18n="modal.link.what">What happens on the link</legend>
                    <div class="row">
                        <label class="radio"><input type="radio" name="disposition" value="0" checked><span class="radio__dot"></span> <span data-i18n="modal.link.open">open in the browser</span></label>
                        <label class="radio"><input type="radio" name="disposition" value="1"><span class="radio__dot"></span> <span data-i18n="modal.link.download">download as a file</span></label>
                    </div>
                </fieldset>

                <div class="field">
                    <label class="field__label" for="link-max" data-i18n="modal.link.limit">Download limit</label>
                    <input class="input" id="link-max" type="number" name="maxDownloads" data-i18n-attr="placeholder:link.noLimit" placeholder="no limit" inputmode="numeric">
                    <span class="field__hint" data-i18n="modal.link.limitHint">After the N-th download the link closes itself.</span>
                </div>

                <div class="field">
                    <label class="field__label" for="link-note" data-i18n="table.note">Note</label>
                    <input class="input" id="link-note" name="note" data-i18n-attr="placeholder:modal.link.notePlaceholder" placeholder="for the contractor" autocomplete="off">
                    <span class="field__hint" data-i18n="modal.link.noteHint">Visible only in the link list.</span>
                </div>

                <label class="check">
                    <input type="checkbox" name="revocable">
                    <span class="check__box"><svg class="icon"><use href="#i-check"/></svg></span>
                    <span class="opt__label">
                        <span class="opt__title" data-i18n="modal.link.revocable">Allow revoking the link</span>
                        <span class="opt__hint" data-i18n="modal.link.revocableHint">It shows up in the Links list and can be closed with one button.</span>
                    </span>
                </label>

                <div class="alert alert--outline" data-link-warn hidden>
                    <svg class="icon"><use href="#i-info"/></svg>
                    <div class="alert__body">
                        <div class="alert__text" data-i18n="modal.link.warn">
                            The link lives long, and it can only be revoked together with all the others.
                            Turn on revoking or a limit.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close data-i18n="common.cancel">Cancel</button>
            <button type="submit" class="btn btn--primary" data-i18n="modal.issue">Issue</button>
        </footer>
    </form>
</dialog>
