<?php

use Main\Web\Fmt;

?>

<div class="crumbs">
    <a href="/admin/ui/buckets">Бакеты</a>
    <svg class="icon"><use href="#i-chevron-right"/></svg>
    <a href="/admin/ui/buckets/<?= Fmt::e($bucket['id']) ?>"><?= Fmt::e($bucket['name']) ?></a>
    <span class="tone tone--<?= $bucket['status']['name'] === 'ACTIVE' ? 'ok' : 'warn' ?>">
        <?= Fmt::e($bucket['status']['name']) ?>
    </span>
</div>
