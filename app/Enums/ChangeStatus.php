<?php

declare(strict_types=1);

namespace App\Enums;

enum ChangeStatus: string
{
    case Added = 'added';
    case Removed = 'removed';
    case Modified = 'modified';

    public function symbol(): string
    {
        return match ($this) {
            self::Added => '+',
            self::Removed => '-',
            self::Modified => '~',
        };
    }
}
