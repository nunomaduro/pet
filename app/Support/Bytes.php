<?php

declare(strict_types=1);

namespace App\Support;

final class Bytes
{
    public static function human(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return $unit === 0
            ? sprintf('%d %s', $value, $units[$unit])
            : sprintf('%.1f %s', $value, $units[$unit]);
    }
}
