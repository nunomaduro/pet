<?php

declare(strict_types=1);

namespace App\Support;

final readonly class ControlSafe
{
    private const string CONTROL_CHARACTERS = '/[\x00-\x08\x0b-\x1f\x7f]/';

    public static function text(string $text): string
    {
        return (string) preg_replace(self::CONTROL_CHARACTERS, '?', $text);
    }
}
