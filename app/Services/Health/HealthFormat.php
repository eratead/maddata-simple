<?php

namespace App\Services\Health;

/** Value formatting shared by the check classes, the CLI table and the alert mail. */
final class HealthFormat
{
    /** 45 → "45s", 3900 → "1h 5m", 180000 → "2d 2h". Never returns a bare number. */
    public static function age(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }

        if ($seconds < 3600) {
            return intdiv($seconds, 60).'m';
        }

        if ($seconds < 86400) {
            $h = intdiv($seconds, 3600);
            $m = intdiv($seconds % 3600, 60);

            return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
        }

        $d = intdiv($seconds, 86400);
        $h = intdiv($seconds % 86400, 3600);

        return $h > 0 ? "{$d}d {$h}h" : "{$d}d";
    }

    public static function percent(float $value): string
    {
        return round($value, 1).'%';
    }

    public static function bytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, $i === 0 ? 0 : 1).$units[$i];
    }

    public static function ms(float $ms): string
    {
        return round($ms).'ms';
    }
}
