<?php

declare(strict_types=1);

namespace Main\Request;

use Flytachi\Winter\Kernel\Http\Request\Validation\In;
use Flytachi\Winter\Kernel\Http\Request\Validation\Max;
use Flytachi\Winter\Kernel\Http\Request\Validation\Min;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;

/**
 * Список бакетов для панели: страница, поиск и сортировка.
 *
 * Сортировка серверная, потому что она обязана быть сквозной: если сортировать
 * на клиенте, «самые заполненные» окажутся самыми заполненными только на
 * текущей странице, а это неправда.
 */
final class BucketListRequest
{
    /** Разрешённые поля: только реальные колонки таблицы. */
    public const array SORTS = ['name', 'used', 'quota', 'created'];

    /** Строк на странице по умолчанию — размер не тащим в адрес. */
    public const int PER_PAGE = 10;

    public function __construct(
        #[Min(1)]
        public int $page = 1,

        #[Min(5)]
        #[Max(100)]
        public int $limit = self::PER_PAGE,

        #[Size(min: 0, max: 100)]
        public ?string $search = null,

        #[In(self::SORTS)]
        public string $sort = 'created',

        #[In(['asc', 'desc'])]
        public string $dir = 'desc',
    ) {
    }

    /** Фрагмент ORDER BY — имена колонок, а не то, что пришло от клиента. */
    public function orderBy(): string
    {
        $column = match ($this->sort) {
            'name' => 'name',
            'used' => 'used_bytes',
            'quota' => 'quota_bytes',
            default => 'created_at',
        };

        return $column . ' ' . ($this->dir === 'asc' ? 'ASC' : 'DESC');
    }

    /** Ссылка на ту же страницу с изменёнными параметрами. */
    public function url(array $override = []): string
    {
        $params = array_filter(
            [
                'search' => $this->search,
                'sort' => $this->sort,
                'dir' => $this->dir,
                'page' => $this->page,
                'limit' => $this->limit === self::PER_PAGE ? null : $this->limit,
            ] + [],
            static fn($value) => $value !== null && $value !== '',
        );

        $params = array_filter($override + $params, static fn($value) => $value !== null && $value !== '');

        return '/admin/ui/buckets?' . http_build_query($params);
    }

    /** Куда ведёт клик по заголовку колонки: тот же столбец — переворот. */
    public function sortUrl(string $sort): string
    {
        return $this->url([
            'sort' => $sort,
            'dir' => $this->sort === $sort && $this->dir === 'desc' ? 'asc' : 'desc',
            'page' => 1,
        ]);
    }

    /** Стрелка у заголовка: null — по этой колонке не сортируем. */
    public function sortArrow(string $sort): ?string
    {
        return $this->sort === $sort ? ($this->dir === 'asc' ? '↑' : '↓') : null;
    }
}
