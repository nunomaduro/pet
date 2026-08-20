<?php

declare(strict_types=1);

namespace App\Enums;

enum InstallSourceType: string
{
    case Dist = 'dist';
    case Source = 'source';

    public static function fromComposer(?string $value): self
    {
        return $value === 'source' ? self::Source : self::Dist;
    }
}
