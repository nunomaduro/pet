<?php

declare(strict_types=1);

namespace App\Support;

final readonly class Invitation
{
    public const string ENVIRONMENT = 'PET_INSIDE_COMPOSER';

    public static function verbose(string $command = '-v'): string
    {
        return self::insideComposer() ? 'composer update -v' : $command;
    }

    private static function insideComposer(): bool
    {
        return getenv(self::ENVIRONMENT) === '1';
    }
}
