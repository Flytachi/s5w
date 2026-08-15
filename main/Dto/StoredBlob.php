<?php

declare(strict_types=1);

namespace Main\Dto;

use Main\Entity\Blob;

/**
 * Результат укладки содержимого.
 *
 * `deduplicated = false` значит ещё и «байты на диск положили мы»: если внешняя
 * транзакция потом откатится, файл по этому пути ничей и его убирает тот, кто
 * откатывает.
 */
final class StoredBlob
{
    public function __construct(
        public Blob $blob,
        public bool $deduplicated,
    ) {
    }
}
