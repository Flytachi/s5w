<?php

declare(strict_types=1);

namespace Main\Web;

/**
 * Серверная сортировка списка: заголовки таблицы на широком экране и
 * выпадающий список на узком — одна разметка на все панели.
 *
 * Партиалы видят только данные контроллера, поэтому это класс, а не вьюха:
 * страница создаёт его из своего Request и зовёт th() / menu() прямо в разметке.
 */
final class Sorting
{
    /**
     * @param array<string, array{0: string, 1: string}> $labels   ключ сортировки => [ключ перевода, подпись]
     * @param \Closure(array): string $url    адрес списка с переопределёнными параметрами
     */
    public function __construct(
        private readonly array $labels,
        private readonly string $sort,
        private readonly bool $desc,
        private readonly \Closure $url,
    ) {
    }

    public function isActive(string $key): bool
    {
        return $this->sort === $key;
    }

    /** Тот же столбец — переворот порядка, другой — сортировка по нему. */
    public function sortUrl(string $key): string
    {
        return ($this->url)([
            'sort' => $key,
            'dir' => $this->sort === $key && $this->desc ? 'asc' : 'desc',
            'page' => 1,
        ]);
    }

    /** Заголовок-ссылка. Пустой $key — обычный <th>. */
    public function th(string $key, string $i18nKey, string $label, string $class = ''): string
    {
        $attrs = $class === '' ? '' : ' class="' . Fmt::e($class) . '"';
        $text = '<span data-i18n="' . Fmt::e($i18nKey) . '">' . Fmt::e($label) . '</span>';

        if ($key === '' || !array_key_exists($key, $this->labels)) {
            return '<th' . $attrs . '>' . $text . '</th>';
        }

        $active = $this->isActive($key);
        $aria = $active ? ($this->desc ? 'descending' : 'ascending') : 'none';
        $arrow = $active ? '<span class="th-sort__arrow" aria-hidden="true">' . ($this->desc ? '↓' : '↑') . '</span>' : '';

        return sprintf(
            '<th%s aria-sort="%s"><a href="%s" class="th-sort%s">%s%s</a></th>',
            $attrs,
            $aria,
            Fmt::e($this->sortUrl($key)),
            $active ? ' is-active' : '',
            $text,
            $arrow,
        );
    }

    /** Кнопка с меню: порядок и направление. Показывается там, где шапки таблицы нет. */
    public function menu(string $class = ''): string
    {
        $items = '';
        foreach ($this->labels as $key => [$i18nKey, $label]) {
            $active = $this->isActive($key);
            $items .= sprintf(
                '<a class="dropdown__item%s" role="menuitemradio" aria-checked="%s" href="%s"><span data-i18n="%s">%s</span>%s</a>',
                $active ? ' is-selected' : '',
                $active ? 'true' : 'false',
                Fmt::e(($this->url)(['sort' => $key, 'dir' => $active ? ($this->desc ? 'desc' : 'asc') : 'desc', 'page' => 1])),
                Fmt::e($i18nKey),
                Fmt::e($label),
                $active ? '<svg class="icon icon--sm"><use href="#i-check"/></svg>' : '',
            );
        }

        [$currentKey, $currentLabel] = $this->labels[$this->sort] ?? ['sort.order', 'order'];

        $flip = ($this->url)(['dir' => $this->desc ? 'asc' : 'desc', 'page' => 1]);

        return sprintf(
            '<div class="dropdown%s">'
            . '<button type="button" class="btn btn--ghost btn--sm" data-dropdown-toggle aria-haspopup="menu" aria-expanded="false">'
            . '<svg class="icon icon--sm"><use href="#i-sort"/></svg>'
            . '<span data-i18n="%s">%s</span><span class="text-muted" aria-hidden="true">%s</span>'
            . '<svg class="icon icon--sm"><use href="#i-chevron-down"/></svg>'
            . '</button>'
            . '<div class="dropdown__menu dropdown__menu--check" role="menu">%s'
            . '<div class="dropdown__divider"></div>'
            . '<a class="dropdown__item" role="menuitem" href="%s"><span data-i18n="%s">%s</span>'
            . '<svg class="icon icon--sm%s"><use href="#i-chevron-down"/></svg></a>'
            . '</div></div>',
            $class === '' ? '' : ' ' . Fmt::e($class),
            Fmt::e($currentKey),
            Fmt::e($currentLabel),
            $this->desc ? '↓' : '↑',
            $items,
            Fmt::e($flip),
            $this->desc ? 'sort.asc' : 'sort.desc',
            $this->desc ? 'ascending' : 'descending',
            $this->desc ? ' icon--flip' : '',
        );
    }
}
