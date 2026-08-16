<?php

use Main\Web\Fmt;

$statusTone = static fn(string $status): string => match ($status) {
    'ACTIVE' => 'ok',
    'CREATED', 'PENDING' => 'warn',
    default => 'mute',
};
?>

<div class="row mb-3">
    <div class="search-pill">
        <svg class="icon icon--sm"><use href="#i-search"/></svg>
        <input type="search" placeholder="Поиск по имени и описанию" data-filter="buckets">
    </div>

    <div class="topbar__spacer"></div>

    <button class="btn btn--dark" data-modal-open="modal-bucket">
        <svg class="icon icon--sm"><use href="#i-plus"/></svg> Новый бакет
    </button>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table" data-sortable>
            <thead>
            <tr>
                <th class="sortable">Бакет</th>
                <th>Статус</th>
                <th>Квота</th>
                <th class="num sortable">Файлов</th>
                <th class="num">Блобов</th>
                <th>Кэш</th>
                <th>Создан</th>
                <th></th>
            </tr>
            </thead>
            <tbody data-rows="buckets">
            <?php foreach ($buckets as $bucket): ?>
                <?php [$percent, $state] = Fmt::quotaState($bucket['bytes']['used'], $bucket['bytes']['quota']) ?>
                <tr data-row="bucket" data-id="<?= Fmt::e($bucket['id']) ?>" data-name="<?= Fmt::e($bucket['name']) ?>">
                    <td>
                        <a class="fileline" href="/admin/ui/buckets/<?= Fmt::e($bucket['id']) ?>">
                            <span class="ftype ftype--image"><svg class="icon"><use href="#i-database"/></svg></span>
                            <span class="fileline__body">
                                <span class="fileline__name" data-bucket-name><?= Fmt::e($bucket['name']) ?></span>
                                <span class="fileline__meta"><?= Fmt::e($bucket['description']) ?></span>
                            </span>
                        </a>
                    </td>
                    <td data-cell="status">
                        <span class="tone tone--<?= $statusTone($bucket['status']['name']) ?>">
                            <span class="status-dot" style="background: currentColor"></span>
                            <?= Fmt::e($bucket['status']['name']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="quota <?= $state ?>">
                            <div class="quota__bar"><div class="quota__fill" style="width: <?= $percent ?>%"></div></div>
                            <div class="quota__meta">
                                <span><b><?= Fmt::bytes($bucket['bytes']['used']) ?></b> из <?= Fmt::bytes($bucket['bytes']['quota']) ?></span>
                                <span><?= round($percent) ?>%</span>
                            </div>
                        </div>
                    </td>
                    <td class="num"><?= Fmt::num($bucket['files']) ?></td>
                    <td class="num text-muted"><?= Fmt::num($bucket['blobs']) ?></td>
                    <td>
                        <?php if ($bucket['cache']['visibility'] === null): ?>
                            <span class="tone tone--mute">по умолчанию</span>
                        <?php else: ?>
                            <span class="tone tone--<?= $bucket['cache']['visibility']['name'] === 'PUBLIC' ? 'ok' : 'brand' ?>">
                                <?= Fmt::e($bucket['cache']['visibility']['name']) ?> · <?= (int) $bucket['cache']['maxAge'] ?>s
                            </span>
                        <?php endif ?>
                    </td>
                    <td class="text-muted text-sm nowrap"><?= Fmt::date($bucket['createdAt']) ?></td>
                    <td>
                        <div class="dropdown">
                            <button class="icon-btn icon-btn--ghost icon-btn--sm" data-dropdown-toggle aria-label="Действия">
                                <svg class="icon icon--sm"><use href="#i-more-h"/></svg>
                            </button>
                            <div class="dropdown__menu">
                                <a class="dropdown__item" href="/admin/ui/buckets/<?= Fmt::e($bucket['id']) ?>">
                                    Открыть <svg class="icon"><use href="#i-arrow-right"/></svg>
                                </a>
                                <a class="dropdown__item" href="/admin/ui/buckets/<?= Fmt::e($bucket['id']) ?>/tokens">
                                    Токены <svg class="icon"><use href="#i-key"/></svg>
                                </a>
                                <button class="dropdown__item" data-action="bucket:delete"
                                        data-id="<?= Fmt::e($bucket['id']) ?>" data-name="<?= Fmt::e($bucket['name']) ?>">
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

    <div class="empty" data-empty="buckets" hidden>
        <svg class="icon"><use href="#i-database"/></svg>
        <div class="empty__title">Ничего не нашлось</div>
        <div class="text-sm">Попробуйте другой запрос</div>
    </div>
</div>

<?php wrImport('admin/_modals') ?>
