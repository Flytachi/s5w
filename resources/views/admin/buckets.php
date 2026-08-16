<?php

use Main\Web\Fmt;

/** Заголовок-ссылка: тот же столбец — переворот порядка, другой — сортировка по нему. */
$th = static function (string $sort, string $label, string $class = '') use ($query): string {
    $arrow = $query->sortArrow($sort);

    return sprintf(
        '<th class="%s"><a href="%s" class="th-sort%s">%s%s</a></th>',
        $class,
        Fmt::e($query->sortUrl($sort)),
        $arrow === null ? '' : ' is-active',
        Fmt::e($label),
        $arrow === null ? '' : ' <span class="th-sort__arrow">' . $arrow . '</span>',
    );
};
?>

<form class="row mb-3" method="get" action="/admin/ui/buckets">
    <input type="hidden" name="sort" value="<?= Fmt::e($query->sort) ?>">
    <input type="hidden" name="dir" value="<?= Fmt::e($query->dir) ?>">

    <div class="search-pill">
        <svg class="icon icon--sm"><use href="#i-search"/></svg>
        <input type="search" name="search" placeholder="Поиск по имени и описанию"
               value="<?= Fmt::e($query->search ?? '') ?>">
    </div>

    <?php if ($query->search !== null && $query->search !== ''): ?>
        <a class="btn btn--ghost btn--sm" href="/admin/ui/buckets">
            <svg class="icon icon--sm"><use href="#i-x"/></svg> Сбросить
        </a>
    <?php endif ?>

    <div class="topbar__spacer"></div>

    <span class="text-sm text-muted">
        <?= $page->meta->total ?> <?= Fmt::plural($page->meta->total, 'бакет', 'бакета', 'бакетов') ?>
    </span>

    <button type="button" class="btn btn--dark" data-modal-open="modal-bucket">
        <svg class="icon icon--sm"><use href="#i-plus"/></svg> Новый бакет
    </button>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <?= $th('name', 'Бакет') ?>
                <th>Статус</th>
                <?= $th('used', 'Квота', 'col-quota') ?>
                <th class="num">Файлов</th>
                <th class="num">Блобов</th>
                <th>Кэш</th>
                <?= $th('created', 'Создан') ?>
                <th></th>
            </tr>
            </thead>
            <tbody data-rows="buckets">
            <?php foreach ($page->data as $bucket): ?>
                <tr data-row="bucket" data-id="<?= Fmt::e($bucket->id) ?>" data-name="<?= Fmt::e($bucket->name) ?>">
                    <td>
                        <a class="fileline" href="/admin/ui/buckets/<?= Fmt::e($bucket->id) ?>">
                            <span class="ftype ftype--image"><svg class="icon"><use href="#i-database"/></svg></span>
                            <span class="fileline__body">
                                <span class="fileline__name" data-bucket-name><?= Fmt::e($bucket->name) ?></span>
                                <span class="fileline__meta"><?= Fmt::e($bucket->description) ?></span>
                            </span>
                        </a>
                    </td>
                    <td data-cell="status">
                        <span class="tone tone--<?= $bucket->statusTone() ?>">
                            <span class="status-dot" style="background: currentColor"></span>
                            <?= Fmt::e($bucket->status) ?>
                        </span>
                    </td>
                    <td class="col-quota">
                        <div class="quota <?= $bucket->quotaState() ?>">
                            <div class="quota__bar"><div class="quota__fill" style="width: <?= $bucket->percent() ?>%"></div></div>
                            <div class="quota__meta">
                                <span><b><?= Fmt::bytes($bucket->used) ?></b> из <?= Fmt::bytes($bucket->quota) ?></span>
                                <span><?= round($bucket->percent()) ?>%</span>
                            </div>
                        </div>
                    </td>
                    <td class="num"><?= Fmt::num($bucket->files) ?></td>
                    <td class="num text-muted"><?= Fmt::num($bucket->blobs) ?></td>
                    <td>
                        <?php if ($bucket->cacheVisibility === null): ?>
                            <span class="tone tone--mute">по умолчанию</span>
                        <?php else: ?>
                            <span class="tone tone--<?= $bucket->cacheVisibility === 'PUBLIC' ? 'ok' : 'brand' ?>">
                                <?= Fmt::e($bucket->cacheVisibility) ?> · <?= (int) $bucket->cacheMaxAge ?>s
                            </span>
                        <?php endif ?>
                    </td>
                    <td class="text-muted text-sm nowrap"><?= Fmt::date($bucket->createdAt) ?></td>
                    <td>
                        <div class="dropdown">
                            <button class="icon-btn icon-btn--ghost icon-btn--sm" data-dropdown-toggle aria-label="Действия">
                                <svg class="icon icon--sm"><use href="#i-more-h"/></svg>
                            </button>
                            <div class="dropdown__menu">
                                <a class="dropdown__item" href="/admin/ui/buckets/<?= Fmt::e($bucket->id) ?>">
                                    Открыть <svg class="icon"><use href="#i-arrow-right"/></svg>
                                </a>
                                <button class="dropdown__item" data-action="bucket:edit"
                                        data-id="<?= Fmt::e($bucket->id) ?>"
                                        data-name="<?= Fmt::e($bucket->name) ?>"
                                        data-description="<?= Fmt::e($bucket->description) ?>"
                                        data-quota="<?= $bucket->quota ?>">
                                    Изменить <svg class="icon"><use href="#i-edit"/></svg>
                                </button>
                                <button class="dropdown__item" data-action="bucket:cache"
                                        data-id="<?= Fmt::e($bucket->id) ?>"
                                        data-max-age="<?= $bucket->cacheMaxAge ?? '' ?>"
                                        data-visibility="<?= Fmt::e($bucket->cacheVisibility ?? '') ?>">
                                    Политика кэша <svg class="icon"><use href="#i-clock"/></svg>
                                </button>
                                <button class="dropdown__item" data-action="bucket:delete"
                                        data-id="<?= Fmt::e($bucket->id) ?>" data-name="<?= Fmt::e($bucket->name) ?>">
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

    <div class="empty" data-empty="buckets"<?= $page->data === [] ? '' : ' hidden' ?>>
        <svg class="icon"><use href="#i-database"/></svg>
        <?php if ($query->search !== null && $query->search !== ''): ?>
            <div class="empty__title">Ничего не нашлось</div>
            <div class="text-sm">По запросу «<?= Fmt::e($query->search) ?>» бакетов нет</div>
        <?php else: ?>
            <div class="empty__title">Бакетов пока нет</div>
            <div class="text-sm">Создайте первый — каталог заведётся сам</div>
        <?php endif ?>
    </div>

    <?php if ($page->meta->pages > 1): ?>
        <div class="pagination">
            <span class="text-sm text-muted ml-auto">
                страница <?= $page->meta->current ?> из <?= $page->meta->pages ?>
            </span>

            <a class="page-btn<?= $page->meta->previous === null ? ' is-disabled' : '' ?>"
               href="<?= Fmt::e($query->url(['page' => $page->meta->previous ?? 1])) ?>" aria-label="Назад">
                <svg class="icon icon--sm"><use href="#i-chevron-left"/></svg>
            </a>

            <?php foreach (Fmt::pages($page->meta->current, $page->meta->pages) as $number): ?>
                <?php if ($number === null): ?>
                    <span class="page-btn is-gap">…</span>
                <?php else: ?>
                    <a class="page-btn<?= $number === $page->meta->current ? ' active' : '' ?>"
                       href="<?= Fmt::e($query->url(['page' => $number])) ?>"><?= $number ?></a>
                <?php endif ?>
            <?php endforeach ?>

            <a class="page-btn<?= $page->meta->next === null ? ' is-disabled' : '' ?>"
               href="<?= Fmt::e($query->url(['page' => $page->meta->next ?? $page->meta->pages])) ?>" aria-label="Вперёд">
                <svg class="icon icon--sm"><use href="#i-chevron-right"/></svg>
            </a>
        </div>
    <?php endif ?>
</div>

<?php wrImport('admin/_modals') ?>
