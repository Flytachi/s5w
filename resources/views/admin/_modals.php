<?php

/**
 * Формы отправляются через fetch (сейчас — через мок), страница не
 * перезагружается: data-api задаёт метод и адрес, data-done — что сделать с
 * ответом. Подстановка {bucket} — id текущего бакета из <body>.
 */

?>

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
                <div class="row" style="flex-wrap:nowrap">
                    <input class="input" type="number" name="quota" value="512" style="max-width:130px">
                    <select class="select-native" name="unit" style="max-width:110px">
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

<!-- ================= Новая папка ================= -->
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
                <span class="field__hint">Срок скользящий: считается от момента загрузки каждого файла.</span>
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
    <form class="modal" data-api="PATCH /admin/buckets/{bucket}/folders/{name}/cache" data-done="cache:saved">
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
            Разрешается снизу вверх: папка → бакет → глобальный дефолт. Пустое поле — «наследовать».
        </p>

        <div class="stack mt-2">
            <div class="field">
                <label class="field__label">max-age, секунд</label>
                <input class="input" type="number" name="maxAge" placeholder="86400">
            </div>

            <div class="field">
                <label class="field__label">Видимость</label>
                <select class="select-native" name="visibility">
                    <option value="">наследовать</option>
                    <option value="1">PUBLIC</option>
                    <option value="2">PRIVATE</option>
                    <option value="3">NO_STORE</option>
                </select>
            </div>

            <div class="alert alert--outline">
                <svg class="icon"><use href="#i-info"/></svg>
                <div class="alert__body">
                    <div class="alert__text">
                        Значения не абсолютны: приватный файл никогда не получит <span class="mono">public</span>,
                        а <span class="mono">max-age</span> подрезается сроком жизни файла и ссылки.
                    </div>
                </div>
            </div>
        </div>

        <div class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close>Отмена</button>
            <button type="submit" class="btn btn--dark">Сохранить</button>
        </div>
    </form>
</div>

<!-- ================= Выпуск токена ================= -->
<div class="modal-backdrop" id="modal-token">
    <form class="modal" data-api="POST /admin/buckets/{bucket}/tokens" data-done="token:created">
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
                <label class="field__label">Срок, дней</label>
                <input class="input" type="number" name="expiresInDays" placeholder="90">
                <span class="field__hint">Пусто — бессрочно, потолок 3650 дней.</span>
            </div>
        </div>

        <div class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close>Отмена</button>
            <button type="submit" class="btn btn--dark">Выпустить</button>
        </div>
    </form>
</div>

<!-- ================= Показ токена ================= -->
<div class="modal-backdrop" id="modal-secret">
    <div class="modal">
        <div class="modal__header">
            <span class="tone tone--ok" style="width:32px;height:32px;padding:0;justify-content:center;border-radius:10px">
                <svg class="icon"><use href="#i-check-circle"/></svg>
            </span>
            <div class="modal__title" data-secret-title>Токен выпущен</div>
        </div>

        <p class="modal__text">
            Значение показывается <b>один раз</b> — в базе лежит только хеш. Скопируйте сейчас.
        </p>

        <div class="secret mt-2">
            <span style="flex:1" data-secret-value></span>
            <button class="icon-btn icon-btn--sm" data-secret-copy aria-label="Копировать">
                <svg class="icon icon--sm"><use href="#i-copy"/></svg>
            </button>
        </div>

        <div class="alert alert--outline mt-2">
            <svg class="icon"><use href="#i-lock"/></svg>
            <div class="alert__body">
                <div class="alert__text">
                    Отправляется заголовком <span class="mono">Authorization: Bearer …</span>.
                    Бакет берётся из токена, а не из адреса.
                </div>
            </div>
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
                <label class="field__label">Живёт</label>
                <select class="select-native" name="ttl">
                    <option value="3600">час</option>
                    <option value="86400">сутки</option>
                    <option value="604800">неделя — потолок</option>
                </select>
            </div>

            <div class="field">
                <label class="field__label">Режим</label>
                <div class="row">
                    <label class="radio"><input type="radio" name="disposition" value="0" checked><span class="radio__dot"></span> показать</label>
                    <label class="radio"><input type="radio" name="disposition" value="1"><span class="radio__dot"></span> скачать</label>
                </div>
            </div>

            <div class="field">
                <label class="field__label">Лимит скачиваний</label>
                <input class="input" type="number" name="maxDownloads" placeholder="без лимита">
                <span class="field__hint">С лимитом появляется строка в базе — такую ссылку можно отозвать поимённо.</span>
            </div>

            <div class="field">
                <label class="field__label">Пометка</label>
                <input class="input" name="note" placeholder="для подрядчика" autocomplete="off">
            </div>

            <label class="check">
                <input type="checkbox" name="revocable">
                <span class="check__box"><svg class="icon"><use href="#i-check"/></svg></span>
                Разрешить отзыв поимённо
            </label>
        </div>

        <div class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close>Отмена</button>
            <button type="submit" class="btn btn--dark">Выпустить</button>
        </div>
    </form>
</div>
