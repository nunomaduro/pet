<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\FailureException;

final readonly class Constraint
{
    /**
     * @param  array<int, array<int, callable(string): bool>>  $alternatives  OR of ANDs
     */
    private function __construct(
        private array $alternatives,
        public string $expression,
    ) {}

    public static function parse(string $expression): self
    {
        $trimmed = trim($expression);

        if ($trimmed === '') {
            throw new FailureException('An empty version constraint matches nothing; say "*" if you mean everything.');
        }

        $alternatives = [];

        foreach (preg_split('/\s*\|\|?\s*/', $trimmed) ?: [] as $alternative) {
            $clauses = [];

            foreach (preg_split('/(?:\s*,\s*|\s+)/', trim($alternative)) ?: [] as $clause) {
                if ($clause !== '') {
                    $clauses[] = self::clause($clause);
                }
            }

            if ($clauses !== []) {
                $alternatives[] = $clauses;
            }
        }

        if ($alternatives === []) {
            throw new FailureException(sprintf('Could not read the version constraint [%s].', $expression));
        }

        return new self($alternatives, $trimmed);
    }

    public static function normalize(string $version): string
    {
        $version = ltrim(trim($version), 'vV');
        $plus = mb_strpos($version, '+');

        return $plus === false ? $version : mb_substr($version, 0, $plus);
    }

    public function matches(string $version): bool
    {
        $normalized = self::normalize($version);

        foreach ($this->alternatives as $clauses) {
            $all = true;

            foreach ($clauses as $clause) {
                if (! $clause($normalized)) {
                    $all = false;

                    break;
                }
            }

            if ($all) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return callable(string): bool
     */
    private static function clause(string $clause): callable
    {
        if ($clause === '*' || $clause === '') {
            return static fn (): bool => true;
        }

        if ($clause === '-') {
            return static fn (): bool => true;
        }

        if (preg_match('/^(>=|<=|!=|<>|>|<|==|=)\s*(.+)$/', $clause, $matches) === 1) {
            $operator = $matches[1] === '<>' ? '!=' : ($matches[1] === '==' ? '=' : $matches[1]);
            $bound = self::normalize($matches[2]);

            return static fn (string $version): bool => version_compare($version, $bound, $operator);
        }

        if (str_starts_with($clause, '^')) {
            return self::between(self::normalize(mb_substr($clause, 1)), caret: true);
        }

        if (str_starts_with($clause, '~')) {
            return self::between(self::normalize(mb_substr($clause, 1)), caret: false);
        }

        if (str_contains($clause, '*')) {
            $prefix = rtrim(mb_substr($clause, 0, (int) mb_strpos($clause, '*')), '.');

            return static fn (string $version): bool => $prefix === '' || $version === $prefix || str_starts_with($version, $prefix.'.');
        }

        $exact = self::normalize($clause);

        return static fn (string $version): bool => version_compare($version, $exact, '=');
    }

    /**
     * @return callable(string): bool
     */
    private static function between(string $base, bool $caret): callable
    {
        $parts = array_map(intval(...), explode('.', (string) preg_replace('/[-+].*$/', '', $base)));
        $major = $parts[0];
        $minor = $parts[1] ?? 0;

        $upper = match (true) {
            $caret && $major === 0 && count($parts) > 1 => sprintf('0.%d.0', $minor + 1),
            $caret => sprintf('%d.0.0', $major + 1),
            count($parts) >= 3 => sprintf('%d.%d.0', $major, $minor + 1),
            default => sprintf('%d.0.0', $major + 1),
        };

        return static fn (string $version): bool => version_compare($version, $base, '>=')
            && version_compare($version, $upper, '<');
    }
}
