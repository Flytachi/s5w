<?php

use Main\Dto\TrafficBucket;
use Main\Web\BucketView;
use Main\Web\Fmt;
use Main\Web\TrafficChart;
use Main\Web\Trend;
use Main\Web\Ui;

$chart = TrafficChart::of($series, 'bytes');

$egressTrend = Trend::of($series, static fn($day) => $day->egress);
$hitsTrend = Trend::of($series, static fn($day) => $day->deliveries);

[$usedPercent, $usedState] = Fmt::quotaState($counts->used, $counts->quota);
$free = max(0, $counts->quota - $counts->used);

$topEgress = $top === [] ? 0 : max(array_map(static fn(TrafficBucket $row) => $row->egress, $top));

$quietShown = array_slice($quiet, 0, 4);
$quietRest = count($quiet) - count($quietShown);

$days = static fn(): string => sprintf(
    '<span data-i18n-plural="stats.days" data-count="%d">%d days</span>',
    $window,
    $window,
);

$delta = static function (Trend $trend): string {
    $vars = Fmt::e((string) json_encode(['delta' => $trend->delta()]));

    if (!$trend->known) {
        return '<span class="metric__delta metric__delta--flat" data-i18n="trend.unknown">'
            . Fmt::e($trend->label()) . '</span>';
    }

    return '<span class="metric__delta metric__delta--' . ($trend->up() ? 'up' : 'down') . '">'
        . '<svg class="icon icon--sm"><use href="#i-trending"/></svg> '
        . '<span data-i18n="trend.wow" data-i18n-vars="' . $vars . '">' . Fmt::e($trend->label()) . '</span></span>';
};
?>

<div class="dashtop mb-3">
    <div class="card card--brand">
        <div class="card__header">
            <div class="card__title" data-i18n="dash.space">Space</div>
            <div class="card__spacer"></div>
            <span class="tone" data-i18n="dash.ofQuota" data-i18n-vars="<?= Fmt::e((string) json_encode(['percent' => round($usedPercent)])) ?>"><?= round($usedPercent) ?>% of quota</span>
        </div>

        <div class="usage">
            <div class="usage__part">
                <span class="usage__label" data-i18n="dash.used">Used</span>
                <span class="usage__value" data-fmt="bytes" data-value="<?= $counts->used ?>"><?= Fmt::bytes($counts->used) ?></span>
            </div>
            <div class="usage__sep"></div>
            <div class="usage__part">
                <span class="usage__label" data-i18n="dash.free">Free</span>
                <span class="usage__value usage__value--free" data-fmt="bytes" data-value="<?= $free ?>"><?= Fmt::bytes($free) ?></span>
            </div>
        </div>

        <div class="quota <?= $usedState ?>">
            <div class="quota__bar"><div class="quota__fill" style="width: <?= $usedPercent ?>%"></div></div>
            <div class="quota__meta">
                <span><span data-i18n="dash.allocated">allocated</span> <span data-fmt="bytes" data-value="<?= $counts->quota ?>"><?= Fmt::bytes($counts->quota) ?></span></span>
                <span data-i18n-plural="buckets.count" data-count="<?= $counts->total ?>"><?= $counts->total ?> buckets</span>
            </div>
        </div>

        <?php if ($counts->full > 0): ?>
            <div class="mt-2">
                <span class="tone">
                    <svg class="icon"><use href="#i-alert-triangle"/></svg>
                    <span data-i18n-plural="dash.atLimit" data-count="<?= $counts->full ?>"><?= $counts->full ?> buckets at the limit</span>
                </span>
            </div>
        <?php endif; ?>

        <?php /* Экономия дедупликацией — про место, а не про трафик, и стоять ей
                 рядом с занятым, а не в ряду с egress. */ ?>
        <div class="text-sm mt-3">
            <span data-i18n="dash.dedupBefore">Deduplication saves</span>
            <b data-fmt="bytes" data-value="<?= $blobs->saved ?>"><?= Fmt::bytes($blobs->saved) ?></b>
            <span data-i18n="dash.dedupAfter" data-i18n-vars="<?= Fmt::e((string) json_encode(['percent' => round($blobs->ratio())])) ?>">— <?= round($blobs->ratio()) ?>% of what would land without it</span>
        </div>
    </div>

    <div class="grid grid--2">
        <div class="card">
            <div class="metric">
                <span class="metric__label"><span data-i18n="dash.egressFor">Egress over</span> <?= $days() ?></span>
                <span class="metric__value" data-fmt="bytes" data-value="<?= $totals->egress ?>"><?= Fmt::bytes($totals->egress) ?></span>
                <?= $delta($egressTrend) ?>
            </div>
        </div>

        <div class="card">
            <div class="metric">
                <span class="metric__label"><span data-i18n="dash.deliveriesFor">Delivery requests over</span> <?= $days() ?></span>
                <span class="metric__value" data-fmt="num" data-value="<?= $totals->deliveries ?>"><?= Fmt::num($totals->deliveries) ?></span>
                <?= $delta($hitsTrend) ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card__header">
        <div>
            <div class="card__title" data-i18n="dash.traffic">Traffic</div>
            <div class="card__subtitle"><span data-i18n="dash.allBuckets">all buckets</span>, <?= Fmt::e($from) ?> — <?= Fmt::e($to) ?></div>
        </div>
    </div>

    <?= Ui::chart($chart, 240, 'stats.emptyTraffic', 'No traffic in this period') ?>
    <?= Ui::legend($chart, $chart->isEmpty() ? [] : [
        [
            'key' => 'stats.peak',
            'label' => 'daily peak',
            'value' => sprintf('<span data-fmt="bytes" data-value="%d">%s</span>', $chart->peak, Fmt::e($chart->peakLabel())),
        ],
        [
            'key' => 'dash.avgPerDelivery',
            'label' => 'average per delivery',
            'value' => sprintf(
                '<span data-fmt="bytes" data-value="%d">%s</span>',
                $totals->averageServed(),
                Fmt::e(Fmt::bytes($totals->averageServed())),
            ),
        ],
        [
            'key' => 'dash.apiRequests',
            'label' => 'requests with a token',
            'value' => sprintf('<span data-fmt="num" data-value="%d">%s</span>', $totals->api, Fmt::e(Fmt::num($totals->api))),
        ],
    ]) ?>
</div>

<div class="grid grid--2 mb-3">
    <div class="card card--flex">
        <div class="card__header">
            <div>
                <div class="card__title" data-i18n="dash.topTitle">Who eats the bandwidth</div>
                <div class="card__subtitle"><span data-i18n="dash.byEgressFor">by outbound over</span> <?= $days() ?><?php if ($topTotal > count($top)): ?>,
                    <span data-i18n="dash.firstOf" data-i18n-vars="<?= Fmt::e((string) json_encode(['shown' => count($top), 'total' => $topTotal])) ?>">first <?= count($top) ?> of <?= $topTotal ?></span><?php endif; ?></div>
            </div>
        </div>

        <?php if ($top === []): ?>
            <p class="text-sm text-muted" data-i18n="dash.topEmpty">No bucket served anything in this period.</p>
        <?php else: ?>
            <div class="tbrank">
                <?php foreach ($top as $row): ?>
                    <a class="tbrank__row" href="/admin/ui/buckets/<?= Fmt::e($row->bucket_id) ?>/stats">
                        <span class="tbrank__name"><?= Fmt::e($row->name) ?></span>
                        <span class="tbrank__value" data-fmt="bytes" data-value="<?= $row->egress ?>"><?= Fmt::bytes($row->egress) ?></span>
                        <span class="tbrank__meter">
                            <i style="width: <?= $topEgress > 0 ? round($row->egress / $topEgress * 100, 2) : 0 ?>%"></i>
                        </span>
                        <span class="tbrank__meta">
                            <span data-i18n-plural="dash.deliveries" data-count="<?= $row->deliveries ?>"><?= $row->deliveries ?> deliveries</span>
                            <span class="dot-sep"></span><span data-fmt="bytes" data-value="<?= $row->averageServed() ?>"><?= Fmt::bytes($row->averageServed()) ?></span> <span data-i18n="dash.perDelivery">per delivery</span>
                            <span class="dot-sep"></span><span data-i18n="dash.received">received</span> <span data-fmt="bytes" data-value="<?= $row->ingress ?>"><?= Fmt::bytes($row->ingress) ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($quiet !== []): ?>
            <div class="text-sm text-muted quiet">
                <span data-i18n="dash.quiet">Quiet:</span> <?= Fmt::e(implode(', ', $quietShown)) ?><?php if ($quietRest > 0): ?>
                    <span data-i18n="dash.andMore" data-i18n-vars="<?= Fmt::e((string) json_encode(['n' => $quietRest])) ?>">and <?= $quietRest ?> more</span><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card__header">
            <div>
                <div class="card__title" data-i18n="dash.fillTitle">Quota fill</div>
                <div class="card__subtitle" data-i18n="dash.fullestOf" data-i18n-vars="<?= Fmt::e((string) json_encode(['shown' => count($fill), 'total' => $fillTotal])) ?>">the fullest <?= count($fill) ?> of <?= $fillTotal ?></div>
            </div>
            <div class="card__spacer"></div>
            <a class="btn btn--ghost btn--sm" href="/admin/ui/buckets">
                <span data-i18n="dash.allLink">all</span> <svg class="icon icon--sm"><use href="#i-arrow-right"/></svg>
            </a>
        </div>

        <?php if ($fill === []): ?>
            <p class="text-sm text-muted" data-i18n="dash.fillEmpty">No bucket has a quota allocated.</p>
        <?php else: ?>
            <div class="tbrank">
                <?php foreach ($fill as $card): ?>
                    <a class="tbrank__row <?= $card->quotaState() ?>" href="/admin/ui/buckets/<?= Fmt::e($card->id) ?>">
                        <span class="tbrank__name"><?= Fmt::e($card->name) ?></span>
                        <span class="tbrank__value"><?= round($card->percent()) ?>%</span>
                        <span class="tbrank__meter"><i style="width: <?= $card->percent() ?>%"></i></span>
                        <span class="tbrank__meta">
                            <span data-fmt="bytes" data-value="<?= $card->used ?>"><?= Fmt::bytes($card->used) ?></span> <span data-i18n="quota.of">of</span> <span data-fmt="bytes" data-value="<?= $card->quota ?>"><?= Fmt::bytes($card->quota) ?></span>
                            <span class="dot-sep"></span><span data-i18n-plural="files.count" data-count="<?= $card->files ?>"><?= $card->files ?> files</span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid--3">
    <div class="card">
        <div class="card__header">
            <div class="card__title" data-i18n="nav.tokens">Tokens</div>
            <div class="card__spacer"></div>
            <span class="tone tone--brand"><svg class="icon"><use href="#i-key"/></svg> <?= Fmt::num($tokenCounts['active']) ?></span>
        </div>
        <dl class="kv">
            <dt data-i18n="dash.tok.total">Issued in total</dt><dd><?= Fmt::num($tokenCounts['total']) ?></dd>
            <dt data-i18n="dash.tok.active">Valid</dt><dd><?= Fmt::num($tokenCounts['active']) ?></dd>
            <dt data-i18n="dash.tok.full">With full access</dt><dd><?= Fmt::num($tokenCounts['full']) ?></dd>
            <dt data-i18n="dash.tok.expired">Expired</dt>
            <dd><?= $tokenCounts['expired'] > 0
                ? '<span class="tone tone--warn">' . Fmt::num($tokenCounts['expired']) . '</span>'
                : '0' ?></dd>
            <dt data-i18n="dash.tok.off">Disabled</dt><dd><?= Fmt::num($tokenCounts['inactive']) ?></dd>
        </dl>
    </div>

    <div class="card">
        <div class="card__header">
            <div class="card__title" data-i18n="links.title">Temporary links</div>
            <div class="card__spacer"></div>
            <span class="tone tone--temp"><svg class="icon"><use href="#i-link"/></svg> <?= Fmt::num($linkCounts['active']) ?></span>
        </div>
        <dl class="kv">
            <dt data-i18n="dash.tok.total">Issued in total</dt><dd><?= Fmt::num($linkCounts['total']) ?></dd>
            <dt data-i18n="dash.lnk.alive">Alive</dt><dd><?= Fmt::num($linkCounts['active']) ?></dd>
            <dt data-i18n="dash.tok.expired">Expired</dt><dd><?= Fmt::num($linkCounts['expired']) ?></dd>
            <dt data-i18n="links.stat.revoked">Revoked</dt><dd><?= Fmt::num($linkCounts['revoked']) ?></dd>
        </dl>
        <div class="text-sm text-muted mt-3" data-i18n="dash.lnk.note">Spent links are removed by the sweeper; they take no space.</div>
    </div>

    <div class="card">
        <div class="card__header">
            <div class="card__title" data-i18n="dash.upl.title">Unfinished uploads</div>
            <div class="card__spacer"></div>
            <span class="tone <?= $uploadCounts->expired > 0 ? 'tone--warn' : 'tone--mute' ?>">
                <svg class="icon"><use href="#i-upload"/></svg> <?= Fmt::num($uploadCounts->total) ?>
            </span>
        </div>
        <dl class="kv">
            <dt data-i18n="dash.upl.running">In progress</dt><dd><?= Fmt::num($uploadCounts->total - $uploadCounts->expired) ?></dd>
            <dt data-i18n="dash.upl.expired">Expired</dt><dd><?= Fmt::num($uploadCounts->expired) ?></dd>
            <dt data-i18n="dash.upl.staged">Held in staging</dt><dd data-fmt="bytes" data-value="<?= $uploadCounts->staged ?>"><?= Fmt::bytes($uploadCounts->staged) ?></dd>
        </dl>
        <div class="text-sm text-muted mt-3" data-i18n="dash.upl.note">An abandoned part holds space but does not count against the bucket quota — it only does once the upload completes.</div>
    </div>
</div>
