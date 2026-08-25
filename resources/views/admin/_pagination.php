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

<nav class="pagination" aria-label="Страницы">
    <span class="pagination__info">
        страница <?= $meta->current ?> из <?= $meta->pages ?>
    </span>

    <?php if ($meta->previous === null): ?>
        <span class="page-btn is-disabled" aria-hidden="true"><svg class="icon icon--sm"><use href="#i-chevron-left"/></svg></span>
    <?php else: ?>
        <a class="page-btn" href="<?= Fmt::e($pageUrl($meta->previous)) ?>" aria-label="Назад">
            <svg class="icon icon--sm"><use href="#i-chevron-left"/></svg>
        </a>
    <?php endif ?>

    <?php foreach (Fmt::pages($meta->current, $meta->pages) as $number): ?>
        <?php if ($number === null): ?>
            <span class="page-btn is-gap" aria-hidden="true">…</span>
        <?php elseif ($number === $meta->current): ?>
            <span class="page-btn active" aria-current="page"><?= $number ?></span>
        <?php else: ?>
            <a class="page-btn" href="<?= Fmt::e($pageUrl($number)) ?>" aria-label="Страница <?= $number ?>"><?= $number ?></a>
        <?php endif ?>
    <?php endforeach ?>

    <?php if ($meta->next === null): ?>
        <span class="page-btn is-disabled" aria-hidden="true"><svg class="icon icon--sm"><use href="#i-chevron-right"/></svg></span>
    <?php else: ?>
        <a class="page-btn" href="<?= Fmt::e($pageUrl($meta->next)) ?>" aria-label="Вперёд">
            <svg class="icon icon--sm"><use href="#i-chevron-right"/></svg>
        </a>
    <?php endif ?>
</nav>
