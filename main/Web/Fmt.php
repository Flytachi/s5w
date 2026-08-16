<?php

declare(strict_types=1);

namespace Main\Web;

/** Форматирование для вьюх админки. */
final class Fmt
{
    public static function bytes(int|float $value, int $precision = 1): string
    {
        $units = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
        $i = 0;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) (int) $value : number_format($value, $precision, ',', ' '))
            . ' ' . $units[$i];
    }

    public static function num(int $value): string
    {
        return number_format($value, 0, ',', ' ');
    }

    /** «14 минут назад», «3 дня назад» — без сторонних библиотек. */
    public static function ago(string $datetime): string
    {
        $diff = time() - strtotime($datetime);
        if ($diff < 60) {
            return 'только что';
        }

        foreach ([[31536000, 'г'], [2592000, 'мес'], [86400, 'д'], [3600, 'ч'], [60, 'мин']] as [$size, $label]) {
            if ($diff >= $size) {
                return intdiv($diff, $size) . ' ' . $label . ' назад';
            }
        }

        return 'только что';
    }

    /** Сколько осталось: для сроков хранения и временных ссылок. */
    public static function left(?string $datetime): string
    {
        if ($datetime === null) {
            return 'бессрочно';
        }

        $diff = strtotime($datetime) - time();
        if ($diff <= 0) {
            return 'истёк';
        }

        foreach ([[86400, 'д'], [3600, 'ч'], [60, 'мин']] as [$size, $label]) {
            if ($diff >= $size) {
                return 'ещё ' . intdiv($diff, $size) . ' ' . $label;
            }
        }

        return 'меньше минуты';
    }

    public static function date(string $datetime): string
    {
        return date('d.m.Y H:i', strtotime($datetime));
    }

    /** Класс и иконка плитки по mime — по нему же красится строка файла. */
    public static function kind(string $mime): array
    {
        return match (true) {
            str_starts_with($mime, 'image/') => ['image', 'i-image'],
            str_starts_with($mime, 'video/') => ['video', 'i-film'],
            str_starts_with($mime, 'audio/') => ['audio', 'i-music'],
            str_contains($mime, 'zip'), str_contains($mime, 'gzip'), str_contains($mime, 'tar')
                => ['arch', 'i-archive'],
            default => ['doc', 'i-file'],
        };
    }

    /** Насколько заполнена квота и каким цветом это показывать. */
    public static function quotaState(int $used, int $quota): array
    {
        $percent = $quota > 0 ? min(100, $used / $quota * 100) : 0;
        $class = match (true) {
            $percent >= 90 => 'is-danger',
            $percent >= 70 => 'is-warn',
            default => '',
        };

        return [$percent, $class];
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
