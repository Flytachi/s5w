<?php

declare(strict_types=1);

namespace Main\Sweepers;

interface Sweeper
{
    /**
     * @param bool $force Снять предохранитель там, где он есть; остальные уборщики его не замечают.
     * @return int Сколько убрано или исправлено.
     */
    public function sweep(bool $force = false): int;
}
