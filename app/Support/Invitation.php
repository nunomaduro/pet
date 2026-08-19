<?php

declare(strict_types=1);

namespace App\Support;

use App\Composer\Gate;

final readonly class Invitation
{
    public static function verbose(string $command = '-v'): string
    {
        return self::insideComposer() ? 'composer update -v' : $command;
    }

    private static function insideComposer(): bool
    {
        return getenv(Gate::ENVIRONMENT) === '1';
    }
}
