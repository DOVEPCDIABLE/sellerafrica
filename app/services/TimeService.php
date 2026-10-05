<?php

namespace App;

use DateTimeImmutable;

final class TimeService
{
    private static string $timezone = 'Africa/Lagos';

    public static function boot(string $timezone): void
    {
        self::$timezone = $timezone;
        date_default_timezone_set($timezone);
    }

    public static function timezone(): string
    {
        return self::$timezone;
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new \DateTimeZone(self::$timezone));
    }

    public static function format(DateTimeImmutable|\DateTimeInterface|string|null $time = null, string $format = 'Y-m-d H:i:s'): string
    {
        if ($time instanceof \DateTimeInterface) {
            return $time->format($format);
        }

        if (is_string($time) && $time !== '') {
            return (new DateTimeImmutable($time, new \DateTimeZone(self::$timezone)))->format($format);
        }

        return self::now()->format($format);
    }

    public static function sqlNow(): string
    {
        return self::now()->format('Y-m-d H:i:s');
    }
}
