<?php

declare(strict_types=1);

namespace Main\Image;

/**
 * Итог обработки: что класть в хранилище и что показать клиенту.
 *
 * `path` — всегда валидный путь к байтам, которые пойдут в блоб: либо новый
 * временный файл (`temporary`), либо исходный, если обработки не было.
 */
final class ProcessedImage
{
    /**
     * @param string[] $operations
     * @param array{width: int, height: int, size: int, mime: string}|null $source
     * @param array{width: int, height: int, size: int, mime: string}|null $result
     */
    private function __construct(
        public readonly string $path,
        public readonly bool $applied,
        public readonly bool $temporary,
        public readonly ?string $reason,
        public readonly array $operations,
        public readonly ?array $source,
        public readonly ?array $result,
    ) {
    }

    public static function skipped(string $path, string $reason): self
    {
        return new self($path, false, false, $reason, [], null, null);
    }

    /**
     * @param string[] $operations
     * @param array{width: int, height: int, size: int, mime: string} $source
     * @param array{width: int, height: int, size: int, mime: string} $result
     */
    public static function done(string $path, array $operations, array $source, array $result): self
    {
        return new self($path, true, true, null, $operations, $source, $result);
    }

    public function toArray(): array
    {
        if (!$this->applied) {
            return ['applied' => false, 'reason' => $this->reason];
        }

        return [
            'applied' => true,
            'operations' => $this->operations,
            'source' => $this->source,
            'result' => $this->result,
        ];
    }
}
