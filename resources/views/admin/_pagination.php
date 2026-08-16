<?php

use Main\Web\Fmt;

/**
 * Общая постраничная навигация.
 * Ждёт: $meta (WrapMeta), $pageUrl (callable: int → string).
 */
if ($meta->pages <= 1) {
    return;
}
?>

<div class="pagination">
    <span class="text-sm text-muted ml-auto">
        страница <?= $meta->current ?> из <?= $meta->pages ?>
    </span>

    <a class="page-btn<?= $meta->previous === null ? ' is-disabled' : '' ?>"
       href="<?= Fmt::e($pageUrl($meta->previous ?? 1)) ?>" aria-label="Назад">
        <svg class="icon icon--sm"><use href="#i-chevron-left"/></svg>
    </a>

    <?php foreach (Fmt::pages($meta->current, $meta->pages) as $number): ?>
        <?php if ($number === null): ?>
            <span class="page-btn is-gap">…</span>
        <?php else: ?>
            <a class="page-btn<?= $number === $meta->current ? ' active' : '' ?>"
               href="<?= Fmt::e($pageUrl($number)) ?>"><?= $number ?></a>
        <?php endif ?>
    <?php endforeach ?>

    <a class="page-btn<?= $meta->next === null ? ' is-disabled' : '' ?>"
       href="<?= Fmt::e($pageUrl($meta->next ?? $meta->pages)) ?>" aria-label="Вперёд">
        <svg class="icon icon--sm"><use href="#i-chevron-right"/></svg>
    </a>
</div>
