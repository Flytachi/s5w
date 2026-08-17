<?php

use Main\Web\Fmt;

$base = '/admin/ui/buckets/' . $bucket->id;

// ВРЕМЕННО: цифры выдуманы, чтобы согласовать вёрстку. Заменяются запросами.
$mock = [
    'ext' => [
        ['mp4', 148_209_664, 12],
        ['png', 96_468_992, 84],
        ['webp', 61_865_984, 121],
        ['jpg', 55_107_584, 63],
        ['pdf', 28_311_552, 31],
        ['zip', 18_874_368, 6],
        ['svg', 12_582_912, 44],
        ['mp3', 9_437_184, 17],
        ['docx', 7_340_032, 12],
        ['csv', 5_242_880, 9],
        ['txt', 1_048_576, 13],
    ],
    'folders' => 7,
    'files' => 412,
    'blobs' => 361,
    'where' => [
        ['Корень', 'i-file', 1, 38, null],
        ['Папки', 'i-folder', 3, 320, 6],
        ['Временные папки', 'i-clock', 4, 54, 1],
    ],
    'tokens' => ['active' => 4, 'full' => 1, 'inactive' => 2, 'expired' => 1],
    'links' => ['active' => 18, 'total' => 28, 'revoked' => 2, 'expired' => 8],
];

$kindsTotal = array_sum(array_column($mock['ext'], 1));
$dedup = $mock['files'] - $mock['blobs'];

$circle = 2 * M_PI * 54;
$offset = 0;
?>

<div class="grid grid--3 mb-3">
    <div class="card card--brand">
        <div class="card__header">
            <div class="card__title">Место</div>
            <div class="card__spacer"></div>
            <span class="tone"><?= round($bucket->percent()) ?>%</span>
        </div>

        <div class="usage">
            <div class="usage__part">
                <span class="usage__label">Занято</span>
                <span class="usage__value"><?= Fmt::bytes($bucket->used) ?></span>
            </div>
            <div class="usage__sep"></div>
            <div class="usage__part">
                <span class="usage__label">Свободно</span>
                <span class="usage__value usage__value--free"><?= Fmt::bytes($bucket->free) ?></span>
            </div>
        </div>

        <div class="quota <?= $bucket->quotaState() ?> mt-2">
            <div class="quota__bar"><div class="quota__fill" style="width: <?= $bucket->percent() ?>%"></div></div>
            <div class="quota__meta">
                <span>квота <?= Fmt::bytes($bucket->quota) ?></span>
                <span><?= Fmt::num($mock['files']) ?> файлов</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <div class="card__title">Бакет</div>
            <div class="card__spacer"></div>
            <button class="icon-btn icon-btn--ghost icon-btn--sm" aria-label="Изменить"
                    data-action="bucket:edit" data-id="<?= Fmt::e($bucket->id) ?>"
                    data-name="<?= Fmt::e($bucket->name) ?>" data-description="<?= Fmt::e($bucket->description) ?>"
                    data-quota="<?= $bucket->quota ?>">
                <svg class="icon icon--sm"><use href="#i-edit"/></svg>
            </button>
        </div>

        <dl class="kv mt-2">
            <dt>Статус</dt>
            <dd><span class="tone tone--<?= $bucket->statusTone() ?>"><?= Fmt::e($bucket->status) ?></span></dd>
            <dt>Создан</dt><dd><?= Fmt::date($bucket->createdAt) ?></dd>
            <dt>Описание</dt>
            <dd><?= $bucket->description === '' ? '<span class="text-muted">нет</span>' : Fmt::e($bucket->description) ?></dd>
        </dl>

        <div class="text-sm text-muted mt-3">Идентификатор в адресах <span class="mono">/o</span></div>
        <div class="row mt-1">
            <span class="copyable copyable--wide mono" data-copy="<?= Fmt::e($bucket->id) ?>">
                <?= Fmt::e($bucket->id) ?>
                <svg class="icon"><use href="#i-copy"/></svg>
            </span>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <div class="card__title">Кэш по умолчанию</div>
            <div class="card__spacer"></div>
            <button class="icon-btn icon-btn--ghost icon-btn--sm" aria-label="Изменить"
                    data-action="bucket:cache" data-id="<?= Fmt::e($bucket->id) ?>"
                    data-max-age="<?= $bucket->cacheMaxAge ?? '' ?>"
                    data-visibility="<?= Fmt::e($bucket->cacheVisibility ?? '') ?>">
                <svg class="icon icon--sm"><use href="#i-edit"/></svg>
            </button>
        </div>

        <?php if ($bucket->cacheVisibility === null): ?>
            <div class="empty-inline mt-2">
                <svg class="icon"><use href="#i-clock"/></svg>
                <div>
                    <div style="font-weight:600">Не задан</div>
                    <div class="text-sm text-muted">Берётся дефолт сервиса, папка может его переопределить.</div>
                </div>
            </div>
        <?php else: ?>
            <dl class="kv mt-2">
                <dt>Видимость</dt>
                <dd><span class="tone tone--<?= $bucket->cacheVisibility === 'PUBLIC' ? 'ok' : 'brand' ?>">
                    <?= Fmt::e($bucket->cacheVisibility) ?>
                </span></dd>
                <dt>max-age</dt><dd><?= (int) $bucket->cacheMaxAge ?> с</dd>
            </dl>
        <?php endif ?>

        <div class="text-sm text-muted mt-3">Действует на <span class="mono">/o</span>; на <span class="mono">/p</span>
            и <span class="mono">/t</span> отдаётся <span class="mono">private</span>.</div>
    </div>
</div>

<div class="grid grid--overview mb-3">
    <div class="card">
        <div class="card__header">
            <div>
                <div class="card__title">Что занимает место</div>
                <div class="card__subtitle">по расширениям</div>
            </div>
        </div>

        <div class="ring-row mt-2">
            <div class="ring-box">
                <svg class="ring" viewBox="0 0 140 140" data-donut>
                    <circle class="ring__track" cx="70" cy="70" r="54"></circle>
                    <?php foreach ($mock['ext'] as $i => [$ext, $size, $count]): ?>
                        <?php
                        $share = $kindsTotal > 0 ? $size / $kindsTotal : 0;
                        $length = $circle * $share;
                        ?>
                        <circle class="ring__slice" style="stroke: var(--chart-<?= $i % 8 + 1 ?>)"
                                cx="70" cy="70" r="54" data-slice="<?= $i ?>"
                                data-name="<?= Fmt::e($ext) ?>"
                                data-size="<?= Fmt::e(Fmt::bytes($size)) ?>"
                                data-count="<?= $count ?>"
                                data-share="<?= round($share * 100, 1) ?>"
                                stroke-dasharray="<?= round($length, 2) ?> <?= round($circle - $length, 2) ?>"
                                stroke-dashoffset="<?= round(-$offset, 2) ?>"></circle>
                        <?php $offset += $length ?>
                    <?php endforeach ?>
                    <text class="ring__total" x="70" y="67"><?= Fmt::bytes($kindsTotal) ?></text>
                    <text class="ring__cap" x="70" y="84">на диске</text>
                </svg>
            </div>

            <div class="kinds-scroll">
                <table class="kinds">
                    <?php foreach ($mock['ext'] as $i => [$ext, $size, $count]): ?>
                        <tr data-slice="<?= $i ?>">
                            <td class="kinds__name">
                                <span class="kinds__dot" style="background: var(--chart-<?= $i % 8 + 1 ?>)"></span>
                                <span class="mono">.<?= Fmt::e($ext) ?></span>
                                <span class="kinds__count"><?= Fmt::num($count) ?> шт</span>
                            </td>
                            <td class="kinds__value"><?= Fmt::bytes($size) ?></td>
                            <td class="kinds__share"><?= $kindsTotal > 0 ? round($size / $kindsTotal * 100) : 0 ?>%</td>
                        </tr>
                    <?php endforeach ?>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <div class="card__title">Файлы</div>
            <div class="card__spacer"></div>
            <a class="btn btn--ghost btn--sm" href="<?= $base ?>/files">Открыть</a>
        </div>

        <div class="pair mt-2">
            <div class="pair__side">
                <span class="pair__value"><?= Fmt::num($mock['files']) ?></span>
                <span class="pair__label">файлов</span>
            </div>
            <span class="pair__vs">против</span>
            <div class="pair__side">
                <span class="pair__value"><?= Fmt::num($mock['blobs']) ?></span>
                <span class="pair__label">блобов</span>
            </div>
        </div>

        <div class="bar-mini mt-2">
            <div class="bar-mini__fill" style="width: <?= $mock['files'] > 0 ? round($mock['blobs'] / $mock['files'] * 100) : 0 ?>%"></div>
        </div>
        <div class="text-sm text-muted mt-1">
            Дублей свёрнуто: <b><?= Fmt::num($dedup) ?></b> — столько файлов делят чужие байты.
        </div>

        <div class="text-sm text-muted mt-3">Где лежат</div>

        <div class="share-bar mt-1">
            <?php foreach ($mock['where'] as [$label, $icon, $color, $count, $folders]): ?>
                <span class="share-bar__part"
                      style="width: <?= $mock['files'] > 0 ? round($count / $mock['files'] * 100, 2) : 0 ?>%;
                             background: var(--chart-<?= $color ?>)"></span>
            <?php endforeach ?>
        </div>

        <?php foreach ($mock['where'] as [$label, $icon, $color, $count, $folders]): ?>
            <div class="kv-row">
                <span>
                    <span class="kinds__dot" style="background: var(--chart-<?= $color ?>)"></span>
                    <?= Fmt::e($label) ?>
                    <?php if ($folders !== null): ?>
                        <span class="kinds__count"><?= Fmt::num($folders) ?> шт</span>
                    <?php endif ?>
                </span>
                <b><?= Fmt::num($count) ?></b>
            </div>
        <?php endforeach ?>
    </div>
</div>

<div class="grid grid--4">
    <a class="card stat stat--ok access-tile" href="<?= $base ?>/tokens">
        <span class="stat__icon"><svg class="icon"><use href="#i-key"/></svg></span>
        <span class="stat__value<?= $mock['tokens']['active'] === 0 ? ' is-zero' : '' ?>"><?= $mock['tokens']['active'] ?></span>
        <span class="stat__body">
            <span class="stat__label">Ключи</span>
            <span class="stat__note">активны</span>
        </span>
    </a>

    <a class="card stat stat--danger access-tile" href="<?= $base ?>/tokens">
        <span class="stat__icon"><svg class="icon"><use href="#i-clock"/></svg></span>
        <span class="stat__value<?= $mock['tokens']['expired'] === 0 ? ' is-zero' : '' ?>"><?= $mock['tokens']['expired'] ?></span>
        <span class="stat__body">
            <span class="stat__label">Ключи</span>
            <span class="stat__note">просрочены</span>
        </span>
    </a>

    <a class="card stat stat--brand access-tile" href="<?= $base ?>/links">
        <span class="stat__icon"><svg class="icon"><use href="#i-link"/></svg></span>
        <span class="stat__value<?= $mock['links']['active'] === 0 ? ' is-zero' : '' ?>"><?= $mock['links']['active'] ?></span>
        <span class="stat__body">
            <span class="stat__label">Ссылки</span>
            <span class="stat__note">живые</span>
        </span>
    </a>

    <a class="card stat stat--warn access-tile" href="<?= $base ?>/links">
        <span class="stat__icon"><svg class="icon"><use href="#i-clock"/></svg></span>
        <span class="stat__value<?= $mock['links']['expired'] === 0 ? ' is-zero' : '' ?>"><?= $mock['links']['expired'] ?></span>
        <span class="stat__body">
            <span class="stat__label">Ссылки</span>
            <span class="stat__note">истекли</span>
        </span>
    </a>
</div>

<?php wrImport('admin/_modals') ?>
