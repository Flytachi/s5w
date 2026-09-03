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
            <h2 class="modal__title" id="modal-upload-title">Загрузка файлов</h2>
            <?= Ui::modalClose() ?>
        </header>

        <div class="modal__body">
            <div class="stack">
                <div class="field">
                    <label class="field__label" for="upload-folder">Куда положить</label>
                    <select class="select-native" id="upload-folder" data-upload-folder>
                        <option value="">корень бакета — публичный и бессрочный</option>
                        <?php foreach ($folders as $folder): ?>
                            <option value="<?= Fmt::e($folder->name) ?>">
                                <?= Fmt::e($folder->name) ?> — <?= $folder->public ? 'публичная' : 'по токену' ?><?=
                                    $folder->hasRetention() ? ', ' . Fmt::e($folder->retentionLabel()) : '' ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <span class="field__hint">Видимость и срок хранения файл получает от папки.</span>
                </div>

                <label class="file-drop" data-upload-drop>
                    <svg class="icon"><use href="#i-upload"/></svg>
                    <div class="file-drop__title">Перетащите файлы сюда</div>
                    <div class="text-sm text-muted" data-upload-hint>или нажмите, чтобы выбрать</div>
                    <input type="file" multiple>
                </label>

                <!-- Настройки обработки живут на карточках картинок: их дорисовывает
                     features/upload.js, когда видит, что файл — картинка. -->
                <div data-upload-list></div>
            </div>
        </div>

        <footer class="modal__footer">
            <span class="text-sm text-muted grow" data-upload-total></span>
            <button type="button" class="btn btn--ghost" data-modal-close>Закрыть</button>
            <button type="button" class="btn btn--primary" data-upload-start disabled>
                <svg class="icon icon--sm"><use href="#i-upload"/></svg> Загрузить
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
                <dt>Размер</dt><dd data-file-size></dd>
                <dt>Тип</dt><dd class="mono" data-file-mime></dd>
                <dt>Папка</dt><dd data-file-folder></dd>
                <dt>Загружен</dt><dd data-file-created></dd>
                <dt>Срок хранения</dt><dd data-file-expires></dd>
            </dl>

            <div class="field__label mt-3">Содержимое</div>
            <div class="secret mt-1">
                <span data-file-hash></span>
                <button type="button" class="icon-btn icon-btn--sm" data-file-hash-copy aria-label="Копировать хеш">
                    <svg class="icon icon--sm"><use href="#i-copy"/></svg>
                </button>
            </div>

            <div class="field__label mt-3">Постоянные адреса</div>
            <div class="stack mt-1" data-file-urls></div>

            <div class="field__label mt-3">Временные ссылки</div>
            <p class="text-sm text-muted">Только отзываемые и с лимитом.</p>
            <div class="stack mt-1" data-file-links></div>
        </div>

        <footer class="modal__footer">
            <button type="button" class="btn btn--ghost btn--sm" data-action="file:rename" data-from-drawer>
                <svg class="icon icon--sm"><use href="#i-edit"/></svg> Имя
            </button>
            <button type="button" class="btn btn--ghost btn--sm" data-action="file:move" data-from-drawer>
                <svg class="icon icon--sm"><use href="#i-folder"/></svg> Папка
            </button>
            <button type="button" class="btn btn--ghost btn--sm" data-action="link:open" data-from-drawer>
                <svg class="icon icon--sm"><use href="#i-link"/></svg> Ссылка
            </button>
            <button type="button" class="btn btn--danger btn--sm" data-action="file:delete" data-from-drawer>
                <svg class="icon icon--sm"><use href="#i-trash"/></svg> Удалить
            </button>
        </footer>
    </div>
</dialog>

<!-- ================= Переименование и перенос ================= -->
<dialog class="modal" id="modal-file" aria-labelledby="modal-file-title">
    <form class="modal__inner" data-api="PUT /admin/buckets/{bucket}/files/{slug}" data-done="file:updated">
        <header class="modal__header">
            <?= Ui::modalIcon('brand', 'i-edit') ?>
            <h2 class="modal__title" id="modal-file-title" data-file-form-title>Переименовать</h2>
            <?= Ui::modalClose() ?>
        </header>

        <div class="modal__body">
            <div class="stack">
                <div class="field" data-file-field="name">
                    <label class="field__label" for="file-name">Имя</label>
                    <input class="input" id="file-name" name="name" autocomplete="off">
                    <span class="field__hint">Уникально внутри папки. Под этим именем файл скачается.</span>
                </div>

                <div class="field" data-file-field="folder">
                    <label class="field__label" for="file-folder">Папка</label>
                    <select class="select-native" id="file-folder" name="folder">
                        <option value="">корень бакета</option>
                        <?php foreach ($folders as $folder): ?>
                            <option value="<?= Fmt::e($folder->name) ?>"><?= Fmt::e($folder->name) ?></option>
                        <?php endforeach ?>
                    </select>
                    <span class="field__hint">При переносе файл получает видимость и срок новой папки.</span>
                </div>
            </div>
        </div>

        <footer class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close>Отмена</button>
            <button type="submit" class="btn btn--primary">Сохранить</button>
        </footer>
    </form>
</dialog>

<!-- ================= Новый бакет ================= -->
<dialog class="modal" id="modal-bucket" aria-labelledby="modal-bucket-title">
    <form class="modal__inner" data-api="POST /admin/buckets" data-done="bucket:created">
        <header class="modal__header">
            <?= Ui::modalIcon('brand', 'i-database') ?>
            <h2 class="modal__title" id="modal-bucket-title">Новый бакет</h2>
            <?= Ui::modalClose() ?>
        </header>

        <div class="modal__body">
            <p class="modal__text">Бакет готовится в фоне и станет <b>ACTIVE</b> через мгновение.</p>

            <div class="stack">
                <div class="field">
                    <label class="field__label" for="bucket-name">Имя</label>
                    <input class="input" id="bucket-name" name="name" placeholder="media-lab" autocomplete="off">
                    <span class="field__hint">Уникально, 1…100 символов.</span>
                </div>

                <div class="field">
                    <label class="field__label" for="bucket-description">Описание</label>
                    <input class="input" id="bucket-description" name="description" placeholder="для чего этот бакет" autocomplete="off">
                </div>

                <div class="field">
                    <label class="field__label" for="bucket-quota">Квота</label>
                    <div class="quota-input">
                        <input class="input" id="bucket-quota" type="number" name="quota" value="512" inputmode="numeric">
                        <select class="select-native" name="unit" aria-label="Единица">
                            <option value="1048576">МБ</option>
                            <option value="1073741824">ГБ</option>
                        </select>
                    </div>
                    <span class="field__hint">Считается по занятым байтам — дедупликация экономит квоту.</span>
                </div>
            </div>
        </div>

        <footer class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close>Отмена</button>
            <button type="submit" class="btn btn--primary">Создать</button>
        </footer>
    </form>
</dialog>

<!-- ================= Папка ================= -->
<dialog class="modal" id="modal-folder" aria-labelledby="modal-folder-title">
    <form class="modal__inner" data-api="POST /admin/buckets/{bucket}/folders" data-done="folder:created">
        <header class="modal__header">
            <?= Ui::modalIcon('ok', 'i-folder') ?>
            <h2 class="modal__title" id="modal-folder-title">Новая папка</h2>
            <?= Ui::modalClose() ?>
        </header>

        <div class="modal__body">
            <div class="stack">
                <div class="field">
                    <label class="field__label" for="folder-name">Имя</label>
                    <input class="input" id="folder-name" name="name" placeholder="photos" autocomplete="off">
                    <span class="field__hint">Буквы, цифры, пробел, точка, дефис и подчёркивание.</span>
                </div>

                <label class="switch">
                    <input type="checkbox" name="public">
                    <span class="switch__track"></span>
                    <span>Публичная — файлы отдаются через <span class="mono">/o</span> без ключа</span>
                </label>

                <div class="field">
                    <label class="field__label" for="folder-retention">Срок хранения</label>
                    <select class="select-native" id="folder-retention" name="retention">
                        <option value="0">без срока</option>
                        <option value="1">день</option>
                        <option value="2">неделя</option>
                        <option value="3">месяц</option>
                        <option value="4">три месяца</option>
                        <option value="5">полгода</option>
                        <option value="6">год</option>
                    </select>
                    <span class="field__hint">
                        У каждого файла свой срок, от его загрузки. Смена срока пересчитывает и уже лежащие файлы.
                    </span>
                </div>
            </div>
        </div>

        <footer class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close>Отмена</button>
            <button type="submit" class="btn btn--primary">Создать</button>
        </footer>
    </form>
</dialog>

<!-- ================= Политика кэша ================= -->
<dialog class="modal modal--cache modal--full" id="modal-cache" aria-labelledby="modal-cache-title">
    <form class="modal__inner" data-api="PATCH /admin/buckets/{bucket}/folders/{name}/cache" data-done="cache:saved">
        <header class="modal__header">
            <?= Ui::modalIcon('brand', 'i-clock') ?>
            <h2 class="modal__title" id="modal-cache-title">Политика кэша <span data-cache-target class="text-muted"></span></h2>
            <?= Ui::modalClose() ?>
        </header>

        <div class="modal__body">
            <p class="modal__text" data-cache-intro></p>

            <div class="cache-grid">
                <div class="stack">
                    <fieldset class="field">
                        <legend class="field__label">Кому можно хранить копию</legend>

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
                                    <span class="choice__title">Всем — браузеру и CDN</span>
                                    <span class="choice__text">
                                        Копию хранят и браузер, и CDN с прокси. Для того, что и так
                                        открыто всем: аватарки, обложки, картинки сайта.
                                    </span>
                                </span>
                            </label>

                            <label class="choice" data-cache-opt="private">
                                <input type="radio" name="visibility" value="2">
                                <span class="radio__dot"></span>
                                <span class="choice__body">
                                    <span class="choice__title">Только браузеру клиента</span>
                                    <span class="choice__text">
                                        Копия остаётся только у того, кто скачал; CDN и прокси хранить
                                        её не имеют права. Для личных файлов: счета, выписки, фото профиля.
                                    </span>
                                </span>
                            </label>

                            <label class="choice" data-cache-opt="none">
                                <input type="radio" name="visibility" value="3">
                                <span class="radio__dot"></span>
                                <span class="choice__body">
                                    <span class="choice__title">Никому</span>
                                    <span class="choice__text">
                                        Копию не хранит никто — файл качается заново каждый раз. Для того,
                                        что нельзя оставлять на чужом устройстве: паспорта, договоры.
                                    </span>
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    <div class="field" data-cache-ttl>
                        <label class="field__label" for="cache-max-age">Сколько держать копию, секунд</label>
                        <input class="input" id="cache-max-age" type="number" name="maxAge" min="0" max="31536000" inputmode="numeric">
                        <div class="cache-presets mt-1" data-cache-presets>
                            <button type="button" class="btn btn--ghost btn--sm" data-ttl="3600">час</button>
                            <button type="button" class="btn btn--ghost btn--sm" data-ttl="86400">сутки</button>
                            <button type="button" class="btn btn--ghost btn--sm" data-ttl="31536000">год</button>
                            <button type="button" class="btn btn--ghost btn--sm" data-ttl="0">не кэшировать</button>
                            <button type="button" class="btn btn--ghost btn--sm" data-ttl="">по умолчанию</button>
                        </div>
                        <span class="field__hint" data-cache-ttl-hint></span>
                    </div>
                </div>

                <aside class="stack">
                    <div class="cache-preview" data-cache-preview aria-live="polite"></div>

                    <div class="alert alert--outline">
                        <svg class="icon"><use href="#i-info"/></svg>
                        <div class="alert__body">
                            <div class="alert__text">
                                Приватный файл не получит <span class="mono">public</span>, каналы
                                <span class="mono">/p</span> и <span class="mono">/t</span> всегда отдают
                                <span class="mono">private</span>, а срок кэша не переживёт срок файла
                                или ссылки.
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        <footer class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close>Отмена</button>
            <button type="submit" class="btn btn--primary">Сохранить</button>
        </footer>
    </form>
</dialog>

<!-- ================= Выпуск токена ================= -->
<dialog class="modal modal--token" id="modal-token" aria-labelledby="modal-token-title">
    <form class="modal__inner" data-api="POST /admin/buckets/{bucket}/tokens" data-done="token:created">
        <header class="modal__header">
            <?= Ui::modalIcon('brand', 'i-key') ?>
            <h2 class="modal__title" id="modal-token-title">Выпустить токен</h2>
            <?= Ui::modalClose() ?>
        </header>

        <div class="modal__body">
            <div class="stack">
                <div class="field">
                    <label class="field__label" for="token-name">Название</label>
                    <input class="input" id="token-name" name="name" placeholder="мобильное приложение" autocomplete="off">
                    <span class="field__hint">Чтобы потом понять, что отзывать.</span>
                </div>

                <fieldset class="field">
                    <legend class="field__label">Что открывает токен</legend>

                    <div class="choices choices--pair">
                        <label class="choice">
                            <input type="radio" name="access" value="1" checked>
                            <span class="radio__dot"></span>
                            <span class="choice__body">
                                <span class="choice__title">Только отдача</span>
                                <span class="choice__text">
                                    Забирает приватные файлы по <span class="mono">/p</span> по известному
                                    адресу. Списка файлов не видит и ничего не меняет.
                                </span>
                            </span>
                        </label>

                        <label class="choice">
                            <input type="radio" name="access" value="2">
                            <span class="radio__dot"></span>
                            <span class="choice__body">
                                <span class="choice__title">Полный доступ</span>
                                <span class="choice__text">
                                    Всё то же плюс <span class="mono">/v1</span>: список и загрузка файлов,
                                    папки, временные ссылки. Для своего бэкенда — не для браузера.
                                </span>
                            </span>
                        </label>
                    </div>
                </fieldset>

                <div class="field">
                    <label class="field__label" for="token-expires">Срок</label>
                    <select class="select-native" id="token-expires" name="expiresInDays">
                        <option value="">бессрочно</option>
                        <option value="30">30 дней</option>
                        <option value="90">90 дней</option>
                        <option value="365">год</option>
                    </select>
                    <span class="field__hint">
                        После срока токен отвечает <b>403</b> — продлить нельзя, только выпустить новый.
                    </span>
                </div>
            </div>
        </div>

        <footer class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close>Отмена</button>
            <button type="submit" class="btn btn--primary">Выпустить</button>
        </footer>
    </form>
</dialog>

<!-- ================= Показ секрета ================= -->
<dialog class="modal" id="modal-secret" aria-labelledby="modal-secret-title">
    <div class="modal__inner">
        <header class="modal__header">
            <?= Ui::modalIcon('ok', 'i-check-circle') ?>
            <h2 class="modal__title" id="modal-secret-title" data-secret-title>Готово</h2>
        </header>

        <div class="modal__body">
            <p class="modal__text" data-secret-note>
                Значение показывается <b>один раз</b>. Скопируйте сейчас.
            </p>

            <div class="secret mt-2">
                <span data-secret-value></span>
                <button type="button" class="icon-btn icon-btn--sm" data-secret-copy aria-label="Копировать">
                    <svg class="icon icon--sm"><use href="#i-copy"/></svg>
                </button>
            </div>

            <div class="field mt-3" data-secret-check hidden>
                <span class="field__label">Проверить прямо сейчас</span>
                <div class="secret">
                    <span data-secret-curl></span>
                    <button type="button" class="icon-btn icon-btn--sm" data-secret-curl-copy aria-label="Копировать команду">
                        <svg class="icon icon--sm"><use href="#i-copy"/></svg>
                    </button>
                </div>
                <span class="field__hint" data-secret-check-hint></span>
            </div>
        </div>

        <footer class="modal__footer">
            <button type="button" class="btn btn--primary" data-modal-close>Готово</button>
        </footer>
    </div>
</dialog>

<!-- ================= Временная ссылка ================= -->
<dialog class="modal" id="modal-link" aria-labelledby="modal-link-title">
    <form class="modal__inner" data-api="POST /admin/buckets/{bucket}/files/{slug}/link" data-done="link:created">
        <header class="modal__header">
            <?= Ui::modalIcon('temp', 'i-link') ?>
            <h2 class="modal__title" id="modal-link-title">Временная ссылка</h2>
            <?= Ui::modalClose() ?>
        </header>

        <div class="modal__body">
            <p class="modal__text break">на файл <b data-link-file></b></p>

            <div class="stack">
                <div class="field">
                    <label class="field__label" for="link-ttl">Сколько работает</label>
                    <select class="select-native" id="link-ttl" name="ttl">
                        <option value="300">5 минут</option>
                        <option value="900">15 минут</option>
                        <option value="1800">30 минут</option>
                        <option value="3600" selected>час</option>
                        <option value="86400">сутки</option>
                        <option value="604800">неделя</option>
                        <option value="2592000">месяц</option>
                    </select>
                    <span class="field__hint">
                        После срока ссылка перестаёт открываться. Продлить нельзя — только выпустить новую.
                    </span>
                </div>

                <fieldset class="field">
                    <legend class="field__label">Что произойдёт по ссылке</legend>
                    <div class="row">
                        <label class="radio"><input type="radio" name="disposition" value="0" checked><span class="radio__dot"></span> открыть в браузере</label>
                        <label class="radio"><input type="radio" name="disposition" value="1"><span class="radio__dot"></span> скачать файлом</label>
                    </div>
                </fieldset>

                <div class="field">
                    <label class="field__label" for="link-max">Лимит скачиваний</label>
                    <input class="input" id="link-max" type="number" name="maxDownloads" placeholder="без лимита" inputmode="numeric">
                    <span class="field__hint">После N-го скачивания ссылка закрывается сама.</span>
                </div>

                <div class="field">
                    <label class="field__label" for="link-note">Пометка</label>
                    <input class="input" id="link-note" name="note" placeholder="для подрядчика" autocomplete="off">
                    <span class="field__hint">Видна только в списке ссылок.</span>
                </div>

                <label class="check">
                    <input type="checkbox" name="revocable">
                    <span class="check__box"><svg class="icon"><use href="#i-check"/></svg></span>
                    <span class="opt__label">
                        <span class="opt__title">Разрешить отзыв ссылки</span>
                        <span class="opt__hint">Появится в списке «Ссылки», и её можно будет закрыть одной кнопкой.</span>
                    </span>
                </label>

                <div class="alert alert--outline" data-link-warn hidden>
                    <svg class="icon"><use href="#i-info"/></svg>
                    <div class="alert__body">
                        <div class="alert__text">
                            Ссылка живёт долго, а отозвать её можно будет только вместе со всеми.
                            Включите отзыв или лимит.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close>Отмена</button>
            <button type="submit" class="btn btn--primary">Выпустить</button>
        </footer>
    </form>
</dialog>
