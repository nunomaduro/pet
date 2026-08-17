<?php

declare(strict_types=1);

namespace App\Delta;

enum Bucket: string
{
    case InstallManifest = 'install-manifest';

    case Opaque = 'opaque';

    case RuntimeSource = 'runtime-source';

    case Inert = 'inert';

    /**
     * @return array<int, self>
     */
    public static function inReviewOrder(): array
    {
        $cases = self::cases();

        usort($cases, static fn (self $a, self $b): int => $a->weight() <=> $b->weight());

        return $cases;
    }

    public function label(): string
    {
        return match ($this) {
            self::InstallManifest => 'install-time manifest',
            self::Opaque => 'opaque artifact',
            self::RuntimeSource => 'runtime source',
            self::Inert => 'inert',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::InstallManifest => 0,
            self::Opaque => 1,
            self::RuntimeSource => 2,
            self::Inert => 3,
        };
    }
}
