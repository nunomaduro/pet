<?php

declare(strict_types=1);

namespace App\Support;

final readonly class ComposerOperation
{
    private const string LINE = '/^\s*-\s+(Installing|Upgrading|Downgrading|Removing)\s+([^\s\/]+\/[^\s\/]+)\s+\(([^)]+)\)/';

    public function __construct(
        public string $package,
        public ComposerChange $change,
        public ?string $from,
        public ?string $to,
    ) {}

    public static function parse(string $line): ?self
    {
        if (preg_match(self::LINE, $line, $matches) !== 1) {
            return null;
        }

        $change = ComposerChange::fromVerb($matches[1]);
        $versions = array_map(self::version(...), explode('=>', $matches[3]));

        if (count($versions) > 1) {
            return new self($matches[2], $change, $versions[0], $versions[1]);
        }

        return $change === ComposerChange::Remove
            ? new self($matches[2], $change, $versions[0], null)
            : new self($matches[2], $change, null, $versions[0]);
    }

    private static function version(string $version): string
    {
        $trimmed = mb_trim($version);
        $space = mb_strpos($trimmed, ' ');

        return $space === false ? $trimmed : mb_substr($trimmed, 0, $space);
    }
}
