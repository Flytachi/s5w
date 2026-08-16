<?php

use Main\Web\Fmt;


[$usedPercent, $usedState] = Fmt::quotaState($overview['used'], $overview['quota']);

// спарклайн загрузок: точки считаем здесь, чтобы на клиенте не было библиотек
$series = $overview['uploads'];
$max = max($series) ?: 1;
$stepX = 100 / (count($series) - 1);
$points = [];
foreach ($series as $i => $value) {
    $points[] = sprintf('%.2f,%.2f', $i * $stepX, 30 - $value / $max * 26);
}
$line = implode(' ', $points);
$area = "0,30 {$line} 100,30";
?>

<div class="grid grid--4 mb-3">
    <div class="card">
        <div class="metric">
            <span class="metric__label">Бакетов</span>
            <span class="metric__value"><?= $overview['buckets'] ?></span>
            <span class="metric__delta metric__delta--up">
                <svg class="icon icon--sm"><use href="#i-trending"/></svg> +1 за неделю
            </span>
        </div>
    </div>

    <div class="card">
        <div class="metric">
            <span class="metric__label">Файлов</span>
            <span class="metric__value"><?= Fmt::num($overview['files']) ?></span>
            <span class="metric__delta metric__delta--up">
                <svg class="icon icon--sm"><use href="#i-trending"/></svg> +410 за сутки
            </span>
        </div>
    </div>

    <div class="card">
        <div class="metric">
            <span class="metric__label">Блобов</span>
            <span class="metric__value"><?= Fmt::num($overview['blobs']) ?></span>
            <span class="metric__delta metric__delta--up">
                <svg class="icon icon--sm"><use href="#i-layers"/></svg>
                <?= Fmt::num($overview['files'] - $overview['blobs']) ?> дублей свёрнуто
            </span>
        </div>
    </div>

    <div class="card">
        <div class="metric">
            <span class="metric__label">Сэкономлено дедупликацией</span>
            <span class="metric__value"><?= Fmt::bytes($overview['saved']) ?></span>
            <span class="metric__delta metric__delta--up">
                <svg class="icon icon--sm"><use href="#i-check-circle"/></svg> квота не тратится
            </span>
        </div>
    </div>
</div>

<div class="grid grid--2 mb-3">
    <div class="card card--dark">
        <div class="card__header">
            <div>
                <div class="card__title" style="color:#fff">Занято места</div>
                <div class="card__subtitle">по всем бакетам</div>
            </div>
            <div class="card__spacer"></div>
            <span class="tone <?= $usedState === 'is-danger' ? 'tone--danger' : ($usedState === 'is-warn' ? 'tone--warn' : 'tone--ok') ?>">
                <?= round($usedPercent) ?>% квоты
            </span>
        </div>

        <div class="stat-hero">
            <div class="stat-hero__num"><?= Fmt::bytes($overview['used']) ?></div>
            <div class="stat-hero__sep"></div>
            <div class="stat-hero__label">из <?= Fmt::bytes($overview['quota']) ?> выделенных</div>
        </div>

        <div class="quota <?= $usedState ?>">
            <div class="quota__bar"><div class="quota__fill" style="width: <?= $usedPercent ?>%"></div></div>
        </div>

        <div class="mt-3">
            <div class="text-sm text-muted mb-1">Загрузки за две недели</div>
            <svg class="spark" viewBox="0 0 100 32" preserveAspectRatio="none">
                <defs>
                    <linearGradient id="spark-grad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="rgba(255,255,255,.35)"/>
                        <stop offset="100%" stop-color="rgba(255,255,255,0)"/>
                    </linearGradient>
                </defs>
                <polygon class="spark__area" points="<?= $area ?>"/>
                <polyline class="spark__line" style="stroke:#fff" points="<?= $line ?>"/>
            </svg>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <div>
                <div class="card__title">Что лежит</div>
                <div class="card__subtitle">по типам содержимого</div>
            </div>
        </div>

        <div class="split mt-2">
            <?php foreach ($overview['types'] as $type): ?>
                <div class="split__part split__part--<?= $type['kind'] ?>" style="width: <?= $type['share'] ?>%"></div>
            <?php endforeach ?>
        </div>

        <div class="stack mt-3">
            <?php foreach ($overview['types'] as $type): ?>
                <div class="row" style="gap: 10px">
                    <span class="legend__swatch" style="background: var(--<?= match ($type['kind']) {
                        'image' => 'brand', 'video' => 'temp', 'audio' => 'ok', 'doc' => 'warn', default => 'gray-4',
                    } ?>)"></span>
                    <span class="text-sm" style="font-weight:500"><?= Fmt::e($type['label']) ?></span>
                    <span class="ml-auto text-sm text-muted"><?= Fmt::bytes($type['bytes']) ?></span>
                    <b class="text-sm nowrap" style="width:38px; text-align:right"><?= $type['share'] ?>%</b>
                </div>
            <?php endforeach ?>
        </div>

        <hr class="divider">

        <div class="card__title mb-2" style="font-size:.95rem">Отдача по каналам</div>
        <div class="stack">
            <?php foreach ($overview['channels'] as $channel): ?>
                <div class="row" style="gap: 10px">
                    <span class="tone chan chan--<?= $channel['channel'] ?>">/<?= $channel['channel'] ?></span>
                    <span class="text-sm"><?= Fmt::e($channel['label']) ?></span>
                    <span class="ml-auto text-sm text-muted"><?= Fmt::num($channel['hits']) ?> запросов</span>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</div>

<div class="grid grid--2">
    <div class="card">
        <div class="card__header">
            <div>
                <div class="card__title">Бакеты</div>
                <div class="card__subtitle">заполнение квоты</div>
            </div>
            <div class="card__spacer"></div>
            <a class="btn btn--ghost btn--sm" href="/admin/ui/buckets">
                все <svg class="icon icon--sm"><use href="#i-arrow-right"/></svg>
            </a>
        </div>

        <div class="stack mt-2">
            <?php foreach ($cards as $bucket): ?>
                <a class="card card--tile" href="/admin/ui/buckets/<?= Fmt::e($bucket->id) ?>" style="padding:14px 16px">
                    <div class="row" style="justify-content:space-between; flex-wrap:nowrap">
                        <div class="fileline">
                            <div class="ftype ftype--<?= $bucket->isActive() ? 'image' : 'doc' ?>">
                                <svg class="icon"><use href="#i-database"/></svg>
                            </div>
                            <div class="fileline__body">
                                <div class="fileline__name"><?= Fmt::e($bucket->name) ?></div>
                                <div class="fileline__meta">
                                    <?= Fmt::num($bucket->files) ?> файлов<span class="dot-sep"></span><?= $bucket->folders ?> папок
                                </div>
                            </div>
                        </div>

                        <div class="quota <?= $bucket->quotaState() ?>">
                            <div class="quota__bar"><div class="quota__fill" style="width: <?= $bucket->percent() ?>%"></div></div>
                            <div class="quota__meta">
                                <span><b><?= Fmt::bytes($bucket->used) ?></b> из <?= Fmt::bytes($bucket->quota) ?></span>
                                <span><?= round($bucket->percent()) ?>%</span>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach ?>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <div>
                <div class="card__title">Последнее</div>
                <div class="card__subtitle">загрузки, ссылки, уборка</div>
            </div>
        </div>

        <div class="stack mt-2" style="gap: 18px">
            <?php foreach ($events as $event): ?>
                <div class="row" style="gap: 12px; flex-wrap: nowrap; align-items: flex-start">
                    <span class="tone tone--<?= $event['tone'] ?>" style="width:30px;height:30px;padding:0;justify-content:center;border-radius:10px">
                        <svg class="icon"><use href="#<?= $event['icon'] ?>"/></svg>
                    </span>
                    <div style="min-width:0">
                        <div class="text-sm"><?= $event['text'] ?></div>
                        <div class="text-sm text-muted"><?= Fmt::e($event['meta']) ?><span class="dot-sep"></span><?= Fmt::ago($event['at']) ?></div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</div>
