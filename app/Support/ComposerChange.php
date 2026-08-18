<?php

declare(strict_types=1);

namespace App\Support;

enum ComposerChange: string
{
    case Install = 'install';

    case Upgrade = 'upgrade';

    case Downgrade = 'downgrade';

    case Remove = 'remove';

    public static function fromVerb(string $verb): self
    {
        return match (mb_strtolower($verb)) {
            'installing' => self::Install,
            'upgrading' => self::Upgrade,
            'downgrading' => self::Downgrade,
            default => self::Remove,
        };
    }

    public function comparesTrees(): bool
    {
        return $this === self::Upgrade || $this === self::Downgrade;
    }

    public function weight(): int
    {
        return match ($this) {
            self::Upgrade, self::Downgrade => 0,
            self::Install => 1,
            self::Remove => 2,
        };
    }
}
