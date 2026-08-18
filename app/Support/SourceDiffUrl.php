<?php

declare(strict_types=1);

namespace App\Support;

final class SourceDiffUrl
{
    public static function between(?string $sourceUrl, string $from, string $to): ?string
    {
        if ($sourceUrl === null || $from === $to) {
            return null;
        }

        $parts = parse_url($sourceUrl);

        if (! is_array($parts)) {
            return null;
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        $path = $parts['path'] ?? null;

        if (! is_string($scheme) || $scheme !== 'https' || ! is_string($host) || ! is_string($path)) {
            return null;
        }

        $host = mb_strtolower($host);
        $path = mb_trim($path, '/');
        $path = preg_replace('/\.git$/', '', $path) ?? $path;

        if (preg_match('/^[A-Za-z0-9._-]+(?:\/[A-Za-z0-9._-]+)+$/', $path) !== 1) {
            return null;
        }

        $template = match ($host) {
            'github.com' => mb_substr_count($path, '/') === 1
                ? 'https://github.com/%s/compare/%s...%s'
                : null,
            'gitlab.com' => 'https://gitlab.com/%s/-/compare/%s...%s',
            default => null,
        };

        return $template === null
            ? null
            : sprintf($template, $path, rawurlencode($from), rawurlencode($to));
    }
}
