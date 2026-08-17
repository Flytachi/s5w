<?php

use Main\Web\Fmt;

/**
 * Формы уходят через fetch, страница не перезагружается: `data-api` задаёт
 * метод и адрес, `data-done` — что сделать с ответом. `{bucket}` подставляется
 * из <body>, `{name}` и `{slug}` — из данных, с которыми модалку открыли.
 */

$folders = $folders ?? [];
?>

<!-- ================= Загрузка файлов ================= -->
<div class="modal-backdrop" id="modal-upload">
    <div class="modal modal--upload">
        <div class="modal__header">
            <span class="tone tone--brand" style="width:32px;height:32px;padding:0;justify-content:center;border-radius:10px">
                <svg class="icon"><use href="#i-upload"/></svg>
            </span>
            <div class="modal__title">Загрузка файлов</div>
            <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-modal-close aria-label="Закрыть">
                <svg class="icon icon--sm"><use href="#i-x"/></svg>
            </button>
        </div>

        <div class="modal__scroll">
            <div class="stack">
                <div class="field">
                    <label class="field__label">Куда положить</label>
                    <select class="select-native" data-upload-folder>
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
                    <div style="font-weight:600">Перетащите файлы сюда</div>
                    <div class="text-sm text-muted" data-upload-hint>или нажмите, чтобы выбрать</div>
                    <input type="file" multiple>
                </label>

                <!-- Настройки обработки живут на карточках картинок: их дорисовывает
                     initUpload, когда видит, что файл — картинка. -->
                <div data-upload-list></div>
            </div>
        </div>

        <div class="modal__footer">
            <span class="text-sm text-muted ml-auto" data-upload-total></span>
            <button type="button" class="btn btn--ghost" data-modal-close>Закрыть</button>
            <button type="button" class="btn btn--dark" data-upload-start>
                <svg class="icon icon--sm"><use href="#i-upload"/></svg> Загрузить
            </button>
        </div>
    </div>
</div>

<!-- ================= Карточка файла ================= -->
<div class="modal-backdrop modal-backdrop--right" id="drawer-file">
    <div class="modal modal--drawer">
        <div class="modal__header">
            <span class="ftype" data-file-icon><svg class="icon"><use href="#i-file"/></svg></span>
            <div style="min-width:0; flex:1">
                <div class="modal__title" data-file-name style="font-size:1.05rem; word-break:break-word"></div>
                <div class="card__subtitle mono" data-file-slug></div>
            </div>
            <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-modal-close aria-label="Закрыть">
                <svg class="icon icon--sm"><use href="#i-x"/></svg>
            </button>
        </div>

        <div class="modal__body">
            <div class="row mt-2" style="gap:8px" data-file-badges></div>

            <dl class="kv mt-3">
                <dt>Размер</dt><dd data-file-size></dd>
                <dt>Тип</dt><dd class="mono" data-file-mime></dd>
                <dt>Папка</dt><dd data-file-folder></dd>
                <dt>Загружен</dt><dd data-file-created></dd>
                <dt>Срок хранения</dt><dd data-file-expires></dd>
            </dl>

            <div class="card__title mt-3" style="font-size:.95rem">Содержимое</div>
            <div class="secret mt-1" style="font-size:.74rem">
                <span style="flex:1" data-file-hash></span>
                <button class="icon-btn icon-btn--sm" data-file-hash-copy aria-label="Копировать">
                    <svg class="icon icon--sm"><use href="#i-copy"/></svg>
                </button>
            </div>

            <div class="card__title mt-3" style="font-size:.95rem">Постоянные адреса</div>
            <div class="stack mt-1" data-file-urls></div>

            <div class="card__title mt-3" style="font-size:.95rem">Временные ссылки</div>
            <p class="text-sm text-muted">Только отзываемые и с лимитом — остальные нигде не учитываются.</p>
            <div class="stack mt-1" data-file-links></div>
        </div>

        <div class="modal__footer" style="flex-wrap:wrap; gap:8px">
            <button class="btn btn--ghost btn--sm" data-action="file:rename" data-from-drawer>
                <svg class="icon icon--sm"><use href="#i-edit"/></svg> Имя
            </button>
            <button class="btn btn--ghost btn--sm" data-action="file:move" data-from-drawer>
                <svg class="icon icon--sm"><use href="#i-folder"/></svg> Папка
            </button>
            <button class="btn btn--ghost btn--sm" data-action="link:open" data-from-drawer>
                <svg class="icon icon--sm"><use href="#i-link"/></svg> Ссылка
            </button>
            <button class="btn btn--danger btn--sm" data-action="file:delete" data-from-drawer>
                <svg class="icon icon--sm"><use href="#i-trash"/></svg> Удалить
            </button>
        </div>
    </div>
</div>

<!-- ================= Переименование и перенос ================= -->
<div class="modal-backdrop" id="modal-file">
    <form class="modal" data-api="PUT /admin/buckets/{bucket}/files/{slug}" data-done="file:updated">
        <div class="modal__header">
            <span class="tone tone--brand" style="width:32px;height:32px;padding:0;justify-content:center;border-radius:10px">
                <svg class="icon"><use href="#i-edit"/></svg>
            </span>
            <div class="modal__title" data-file-form-title>Переименовать</div>
            <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-modal-close aria-label="Закрыть">
                <svg class="icon icon--sm"><use href="#i-x"/></svg>
            </button>
        </div>

        <div class="stack mt-2">
            <div class="field" data-file-field="name">
                <label class="field__label">Имя</label>
                <input class="input" name="name" autocomplete="off">
                <span class="field__hint">
                    Уникально внутри папки. Попадает в <span class="mono">Content-Disposition</span> при скачивании.
                </span>
            </div>

            <div class="field" data-file-field="folder">
                <label class="field__label">Папка</label>
                <select class="select-native" name="folder">
                    <option value="">корень бакета</option>
                    <?php foreach ($folders as $folder): ?>
                        <option value="<?= Fmt::e($folder->name) ?>"><?= Fmt::e($folder->name) ?></option>
                    <?php endforeach ?>
                </select>
                <span class="field__hint">
                    При переносе видимость и срок пересчитываются по новому месту: файл, уехавший
                    из публичной папки в приватную, перестаёт отдаваться через <span class="mono">/o</span> сразу.
                </span>
            </div>
        </div>

        <div class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close>Отмена</button>
            <button type="submit" class="btn btn--dark">Сохранить</button>
        </div>
    </form>
</div>

<!-- ================= Новый бакет ================= -->
<div class="modal-backdrop" id="modal-bucket">
    <form class="modal" data-api="POST /admin/buckets" data-done="bucket:created">
        <div class="modal__header">
            <span class="tone tone--brand" style="width:32px;height:32px;padding:0;justify-content:center;border-radius:10px">
                <svg class="icon"><use href="#i-database"/></svg>
            </span>
            <div class="modal__title">Новый бакет</div>
            <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-modal-close aria-label="Закрыть">
                <svg class="icon icon--sm"><use href="#i-x"/></svg>
            </button>
        </div>

        <p class="modal__text">
            Каталог на диске заводится фоном: бакет побудет в статусе <b>CREATED</b>
            и станет <b>ACTIVE</b> через доли секунды.
        </p>

        <div class="stack mt-2">
            <div class="field">
                <label class="field__label">Имя</label>
                <input class="input" name="name" placeholder="media-lab" autocomplete="off">
                <span class="field__hint">Уникально, 1…100 символов. Попадает в адрес публичной отдачи.</span>
            </div>

            <div class="field">
                <label class="field__label">Описание</label>
                <input class="input" name="description" placeholder="для чего этот бакет" autocomplete="off">
            </div>

            <div class="field">
                <label class="field__label">Квота</label>
                <div class="row quota-input">
                    <input class="input" type="number" name="quota" value="512">
                    <select class="select-native" name="unit">
                        <option value="1048576">МБ</option>
                        <option value="1073741824">ГБ</option>
                    </select>
                </div>
                <span class="field__hint">Считается по занятым байтам: сжатие и дедупликация экономят квоту.</span>
            </div>
        </div>

        <div class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close>Отмена</button>
            <button type="submit" class="btn btn--dark">Создать</button>
        </div>
    </form>
</div>

<!-- ================= Папка ================= -->
<div class="modal-backdrop" id="modal-folder">
    <form class="modal" data-api="POST /admin/buckets/{bucket}/folders" data-done="folder:created">
        <div class="modal__header">
            <span class="tone tone--ok" style="width:32px;height:32px;padding:0;justify-content:center;border-radius:10px">
                <svg class="icon"><use href="#i-folder"/></svg>
            </span>
            <div class="modal__title">Новая папка</div>
            <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-modal-close aria-label="Закрыть">
                <svg class="icon icon--sm"><use href="#i-x"/></svg>
            </button>
        </div>

        <div class="stack mt-2">
            <div class="field">
                <label class="field__label">Имя</label>
                <input class="input" name="name" placeholder="photos" autocomplete="off">
                <span class="field__hint">Буквы, цифры, пробел, точка, дефис и подчёркивание.</span>
            </div>

            <label class="switch">
                <input type="checkbox" name="public">
                <span class="switch__track"></span>
                <span>Публичная — файлы отдаются через <span class="mono">/o</span> без ключа</span>
            </label>

            <div class="field">
                <label class="field__label">Срок хранения</label>
                <select class="select-native" name="retention">
                    <option value="0">без срока</option>
                    <option value="1">день</option>
                    <option value="2">неделя</option>
                    <option value="3">месяц</option>
                    <option value="4">три месяца</option>
                    <option value="5">полгода</option>
                    <option value="6">год</option>
                </select>
                <span class="field__hint">
                    Срок скользящий: считается от загрузки каждого файла. Смена срока действует
                    на новые файлы — уже лежащие сохраняют свой.
                </span>
            </div>
        </div>

        <div class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close>Отмена</button>
            <button type="submit" class="btn btn--dark">Создать</button>
        </div>
    </form>
</div>

<!-- ================= Политика кэша ================= -->
<div class="modal-backdrop" id="modal-cache">
    <form class="modal modal--cache" data-api="PATCH /admin/buckets/{bucket}/folders/{name}/cache" data-done="cache:saved">
        <div class="modal__header">
            <span class="tone tone--brand" style="width:32px;height:32px;padding:0;justify-content:center;border-radius:10px">
                <svg class="icon"><use href="#i-clock"/></svg>
            </span>
            <div class="modal__title">Политика кэша <span data-cache-target class="text-muted"></span></div>
            <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-modal-close aria-label="Закрыть">
                <svg class="icon icon--sm"><use href="#i-x"/></svg>
            </button>
        </div>

        <p class="modal__text">
            Когда клиент скачал файл, копия остаётся у него в браузере, а по пути — ещё и у
            CDN, прокси и провайдера. Здесь решается, <b>кому</b> из них можно держать эту
            копию и <b>сколько</b>. Настройка наследуется снизу вверх: папка перекрывает
            бакет, бакет — общий дефолт сервиса; «наследовать» значит «не решать здесь».
        </p>

        <div class="cache-grid mt-2">
            <div class="stack">
                <div class="field">
                    <label class="field__label">Сколько держать копию, секунд</label>
                    <input class="input" type="number" name="maxAge" placeholder="наследовать">
                    <span class="field__hint">
                        <span class="mono">3600</span> — час, <span class="mono">86400</span> — сутки,
                        <span class="mono">0</span> — перепроверять каждый раз.
                    </span>
                </div>

                <div class="field">
                    <label class="field__label">Кому можно хранить копию</label>

                    <div class="choices">
                        <label class="choice">
                            <input type="radio" name="visibility" value="" checked>
                            <span class="radio__dot"></span>
                            <span class="choice__body">
                                <span class="choice__title">Наследовать</span>
                                <span class="choice__text">
                                    Решает бакет, а если и там пусто — сам сервис. Обычный выбор,
                                    пока у папки нет причин отличаться.
                                </span>
                            </span>
                        </label>

                        <label class="choice">
                            <input type="radio" name="visibility" value="1">
                            <span class="radio__dot"></span>
                            <span class="choice__body">
                                <span class="choice__title">
                                    Всем по пути <span class="mono">public</span>
                                </span>
                                <span class="choice__text">
                                    Копию держат и браузер, и CDN с прокси — файл отдаётся быстро и почти
                                    без нагрузки на сервис, но лежит на чужих серверах. Для того, что и так
                                    открыто всем: аватарки, обложки, картинки сайта.
                                </span>
                            </span>
                        </label>

                        <label class="choice">
                            <input type="radio" name="visibility" value="2">
                            <span class="radio__dot"></span>
                            <span class="choice__body">
                                <span class="choice__title">
                                    Только клиенту <span class="mono">private</span>
                                </span>
                                <span class="choice__text">
                                    Копия остаётся в браузере того, кто скачал; общие кэши хранить не
                                    имеют права. Для личных файлов: счета, фото профиля, выписки.
                                </span>
                            </span>
                        </label>

                        <label class="choice">
                            <input type="radio" name="visibility" value="3">
                            <span class="radio__dot"></span>
                            <span class="choice__body">
                                <span class="choice__title">
                                    Нигде <span class="mono">no-store</span>
                                </span>
                                <span class="choice__text">
                                    Не сохраняет никто — каждый показ качает файл заново. Медленнее и
                                    дороже по трафику, зато на диске у клиента ничего не оседает: паспорта,
                                    договоры, всё, что нельзя оставлять после выхода.
                                </span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <aside class="stack">
                <div class="cache-preview" data-cache-preview></div>

                <div class="alert alert--outline">
                    <svg class="icon"><use href="#i-info"/></svg>
                    <div class="alert__body">
                        <div class="alert__text">
                            Настройки не абсолютны. Приватный файл никогда не получит
                            <span class="mono">public</span>, каналы <span class="mono">/p</span> и
                            <span class="mono">/t</span> всегда отдают <span class="mono">private</span>,
                            а срок подрезается временем жизни файла и ссылки — кэш не должен пережить
                            то, что кэширует.
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <div class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close>Отмена</button>
            <button type="submit" class="btn btn--dark">Сохранить</button>
        </div>
    </form>
</div>

<!-- ================= Выпуск токена ================= -->
<div class="modal-backdrop" id="modal-token">
    <form class="modal modal--token" data-api="POST /admin/buckets/{bucket}/tokens" data-done="token:created">
        <div class="modal__header">
            <span class="tone tone--brand" style="width:32px;height:32px;padding:0;justify-content:center;border-radius:10px">
                <svg class="icon"><use href="#i-key"/></svg>
            </span>
            <div class="modal__title">Выпустить токен</div>
            <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-modal-close aria-label="Закрыть">
                <svg class="icon icon--sm"><use href="#i-x"/></svg>
            </button>
        </div>

        <div class="stack mt-2">
            <div class="field">
                <label class="field__label">Название</label>
                <input class="input" name="name" placeholder="мобильное приложение" autocomplete="off">
                <span class="field__hint">Чтобы потом было понятно, что именно отзывать.</span>
            </div>

            <div class="field">
                <label class="field__label">Что открывает ключ</label>

                <div class="choices choices--pair">
                    <label class="choice">
                        <input type="radio" name="access" value="1" checked>
                        <span class="radio__dot"></span>
                        <span class="choice__body">
                            <span class="choice__title">Только отдача</span>
                            <span class="choice__text">
                                Забирает приватные файлы по <span class="mono">/p</span>, если знает адрес.
                                Списка файлов не видит и ничего не меняет — такой ключ не страшно
                                положить в приложение.
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
            </div>

            <div class="field">
                <label class="field__label">Срок</label>
                <select class="select-native" name="expiresInDays">
                    <option value="">бессрочно</option>
                    <option value="30">30 дней</option>
                    <option value="90">90 дней</option>
                    <option value="365">год</option>
                </select>
                <span class="field__hint">
                    После срока ключ отвечает <b>403</b> — продлить нельзя, только выпустить новый.
                </span>
            </div>
        </div>

        <div class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close>Отмена</button>
            <button type="submit" class="btn btn--dark">Выпустить</button>
        </div>
    </form>
</div>

<!-- ================= Показ секрета ================= -->
<div class="modal-backdrop" id="modal-secret">
    <div class="modal">
        <div class="modal__header">
            <span class="tone tone--ok" style="width:32px;height:32px;padding:0;justify-content:center;border-radius:10px">
                <svg class="icon"><use href="#i-check-circle"/></svg>
            </span>
            <div class="modal__title" data-secret-title>Готово</div>
        </div>

        <p class="modal__text" data-secret-note>
            Значение показывается <b>один раз</b>. Скопируйте сейчас.
        </p>

        <div class="secret mt-2">
            <span style="flex:1" data-secret-value></span>
            <button class="icon-btn icon-btn--sm" data-secret-copy aria-label="Копировать">
                <svg class="icon icon--sm"><use href="#i-copy"/></svg>
            </button>
        </div>

        <div class="field mt-2" data-secret-check hidden>
            <span class="field__label">Проверить прямо сейчас</span>
            <div class="secret">
                <span style="flex:1" data-secret-curl></span>
                <button class="icon-btn icon-btn--sm" data-secret-curl-copy aria-label="Копировать">
                    <svg class="icon icon--sm"><use href="#i-copy"/></svg>
                </button>
            </div>
            <span class="field__hint" data-secret-check-hint></span>
        </div>

        <div class="modal__footer">
            <button class="btn btn--dark" data-modal-close>Готово</button>
        </div>
    </div>
</div>

<!-- ================= Временная ссылка ================= -->
<div class="modal-backdrop" id="modal-link">
    <form class="modal" data-api="POST /admin/buckets/{bucket}/files/{slug}/link" data-done="link:created">
        <div class="modal__header">
            <span class="tone tone--temp" style="width:32px;height:32px;padding:0;justify-content:center;border-radius:10px">
                <svg class="icon"><use href="#i-link"/></svg>
            </span>
            <div class="modal__title">Временная ссылка</div>
            <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-modal-close aria-label="Закрыть">
                <svg class="icon icon--sm"><use href="#i-x"/></svg>
            </button>
        </div>

        <p class="modal__text">на файл <b data-link-file></b></p>

        <div class="stack mt-2">
            <div class="field">
                <label class="field__label">Сколько работает</label>
                <select class="select-native" name="ttl">
                    <option value="300">5 минут</option>
                    <option value="900">15 минут</option>
                    <option value="1800">30 минут</option>
                    <option value="3600" selected>час</option>
                    <option value="86400">сутки</option>
                    <option value="604800">неделя</option>
                    <option value="2592000">месяц</option>
                </select>
                <span class="field__hint">
                    Срок зашит в саму ссылку: по истечении она перестаёт открываться,
                    продлить её нельзя — только выпустить новую.
                </span>
            </div>

            <div class="field">
                <label class="field__label">Что произойдёт по ссылке</label>
                <div class="row">
                    <label class="radio"><input type="radio" name="disposition" value="0" checked><span class="radio__dot"></span> открыть в браузере</label>
                    <label class="radio"><input type="radio" name="disposition" value="1"><span class="radio__dot"></span> скачать файлом</label>
                </div>
            </div>

            <div class="field">
                <label class="field__label">Лимит скачиваний</label>
                <input class="input" type="number" name="maxDownloads" placeholder="без лимита">
                <span class="field__hint">После N-го скачивания ссылка закрывается сама.</span>
            </div>

            <div class="field">
                <label class="field__label">Пометка</label>
                <input class="input" name="note" placeholder="для подрядчика" autocomplete="off">
                <span class="field__hint">Видна только в списке ссылок — чтобы потом понять, кому она уходила.</span>
            </div>

            <label class="check">
                <input type="checkbox" name="revocable">
                <span class="check__box"><svg class="icon"><use href="#i-check"/></svg></span>
                <span class="opt__label">
                    <span class="opt__title">Дать возможность отозвать эту ссылку</span>
                    <span class="opt__hint">
                        Тогда она появится в списке «Ссылки» и закрывается одной кнопкой.
                        Без этого её можно закрыть, только отозвав разом все ссылки бакета.
                    </span>
                </span>
            </label>

            <div class="alert alert--outline" data-link-warn hidden>
                <svg class="icon"><use href="#i-info"/></svg>
                <div class="alert__body">
                    <div class="alert__text">
                        Ссылка живёт долго, а отозвать её по отдельности будет нельзя —
                        только все сразу. Для такого срока лучше включить отзыв или лимит.
                    </div>
                </div>
            </div>
        </div>

        <div class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close>Отмена</button>
            <button type="submit" class="btn btn--dark">Выпустить</button>
        </div>
    </form>
</div>
