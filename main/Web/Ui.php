<?php

declare(strict_types=1);

namespace Main\Web;

/**
 * Куски разметки, которые повторяются на нескольких страницах.
 * Партиалы не видят локальных переменных страницы, поэтому — методы.
 */
final class Ui
{
    /** График трафика по суткам: ось, столбики, подписи. */
    public static function chart(
        TrafficChart $chart,
        int $height,
        string $emptyKey = '',
        string $emptyText = '',
    ): string {
        if ($chart->isEmpty()) {
            return '<div class="tchart-empty" data-i18n="' . Fmt::e($emptyKey) . '" style="--tchart-h: '
                . $height . 'px">' . Fmt::e($emptyText) . '</div>';
        }

        $axis = '';
        foreach ($chart->grid as $line) {
            $axis .= '<span>' . Fmt::e($line) . '</span>';
        }
        $axis .= '<span>0</span>';

        $columns = '';
        foreach ($chart->columns as $col) {
            $bars = '';
            if ($col->isEmpty) {
                $bars = '<div class="tchart__zero"></div>';
            } else {
                if ($col->bottomPercent > 0) {
                    $bars .= '<div class="tchart__bar tchart__bar--in tchart__bar--set" style="height: '
                        . $col->bottomPercent . '%"></div>';
                }
                if ($col->topPercent > 0) {
                    $bars .= '<div class="tchart__bar tchart__bar--out tchart__bar--set" style="height: '
                        . $col->topPercent . '%"></div>';
                }
            }

            $columns .= sprintf(
                '<div class="tchart__col" data-day="%s" data-title="%s" data-a-value="%s" data-b-value="%s">'
                . '<div class="tchart__stack">%s</div><span class="tchart__label">%s</span></div>',
                Fmt::e($col->day),
                Fmt::e($col->dayTitle),
                Fmt::e($col->topValue),
                Fmt::e($col->bottomValue),
                $bars,
                Fmt::e($col->label),
            );
        }

        return sprintf(
            '<div class="tchart-wrap" style="--tchart-h: %dpx">'
            . '<div class="tchart-axis" aria-hidden="true">%s</div>'
            . '<div class="tchart" data-tchart data-a-label="%s" data-a-color="var(--brand)" '
            . 'data-b-label="%s" data-b-color="var(--chart-4)" role="img" '
            . 'data-i18n-attr="aria-label:chart.aria" data-i18n-vars=\'%s\' aria-label="%s">%s</div>'
            . '</div>',
            $height,
            $axis,
            Fmt::e($chart->topLabel),
            Fmt::e($chart->bottomLabel),
            Fmt::e((string) json_encode(['a' => $chart->topLabel, 'b' => $chart->bottomLabel])),
            Fmt::e($chart->topLabel . ' and ' . $chart->bottomLabel . ' by day'),
            $columns,
        );
    }

    /** Легенда графика: два ряда и подсказки. */
    public static function legend(TrafficChart $chart, array $hints = []): string
    {
        $html = '<div class="legend mt-2">'
            . '<span class="legend__item"><span class="legend__swatch legend__swatch--out"></span>'
            . Fmt::e($chart->topLabel) . ' <span class="legend__hint">· <span data-i18n="'
            . Fmt::e($chart->hintKey($chart->topLabel)) . '">' . Fmt::e($chart->topHint) . '</span></span></span>'
            . '<span class="legend__item"><span class="legend__swatch legend__swatch--in"></span>'
            . Fmt::e($chart->bottomLabel) . ' <span class="legend__hint">· <span data-i18n="'
            . Fmt::e($chart->hintKey($chart->bottomLabel)) . '">' . Fmt::e($chart->bottomHint) . '</span></span></span>';

        foreach ($hints as $hint) {
            $html .= '<span class="legend__item legend__hint"><span data-i18n="' . Fmt::e($hint['key']) . '">'
                . Fmt::e($hint['label']) . '</span> — ' . $hint['value'] . '</span>';
        }

        return $html . '</div>';
    }

    /** Цветной значок в шапке модалки. */
    public static function modalIcon(string $tone, string $icon): string
    {
        return '<span class="modal__icon tone tone--' . Fmt::e($tone) . '"><svg class="icon"><use href="#'
            . Fmt::e($icon) . '"/></svg></span>';
    }

    /** Кнопка закрытия модалки. */
    public static function modalClose(): string
    {
        return '<button type="button" class="icon-btn icon-btn--ghost modal__close" data-modal-close '
            . 'data-i18n-attr="aria-label:common.close" aria-label="Close">'
            . '<svg class="icon"><use href="#i-x"/></svg></button>';
    }

    /** Кнопка меню действий строки. */
    public static function rowMenu(string $icon = 'i-more-h', string $extra = ''): string
    {
        return '<button type="button" class="icon-btn icon-btn--ghost' . ($extra === '' ? '' : ' ' . Fmt::e($extra))
            . '" data-dropdown-toggle data-i18n-attr="aria-label:common.actions" aria-label="Actions" '
            . 'aria-haspopup="menu" aria-expanded="false">'
            . '<svg class="icon"><use href="#' . Fmt::e($icon) . '"/></svg></button>';
    }

    /** Поисковая строка панели. */
    public static function search(
        string $action,
        string $placeholderKey,
        string $placeholder,
        ?string $value,
        array $hidden = [],
    ): string {
        $inputs = '';
        foreach ($hidden as $name => $val) {
            if ($val === null || $val === '') {
                continue;
            }
            $inputs .= '<input type="hidden" name="' . Fmt::e($name) . '" value="' . Fmt::e((string) $val) . '">';
        }

        return '<form class="search-pill" method="get" action="' . Fmt::e($action) . '" role="search">'
            . '<svg class="icon icon--sm"><use href="#i-search"/></svg>'
            . '<input type="search" name="search" data-i18n-attr="placeholder:' . Fmt::e($placeholderKey)
            . ';aria-label:' . Fmt::e($placeholderKey) . '" placeholder="' . Fmt::e($placeholder) . '" aria-label="'
            . Fmt::e($placeholder) . '" value="' . Fmt::e($value ?? '') . '" enterkeyhint="search">'
            . $inputs . '</form>';
    }

    /** Сброс поиска — крестик рядом со строкой. */
    public static function searchReset(string $href): string
    {
        return '<a class="icon-btn icon-btn--ghost" href="' . Fmt::e($href) . '" '
            . 'data-i18n-attr="aria-label:search.reset;title:search.reset" aria-label="Clear search" title="Clear search">'
            . '<svg class="icon"><use href="#i-x"/></svg></a>';
    }
}
