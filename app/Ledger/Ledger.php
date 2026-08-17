<?php

declare(strict_types=1);

namespace App\Ledger;

use App\Lock\Project;

final class Ledger
{
    private const array SECTIONS = ['require' => false, 'require-dev' => true];

    /**
     * @param  array<string, Grant>  $grants
     */
    private function __construct(
        public readonly string $path,
        private readonly Document $document,
        private array $grants,
    ) {}

    public static function forProject(Project $project): self
    {
        return self::fromDocument(Document::forProject($project));
    }

    public static function atPath(string $path): self
    {
        return self::fromDocument(Document::atPath($path));
    }

    public static function fromDocument(Document $document): self
    {
        $grants = [];

        foreach (self::SECTIONS as $section => $dev) {
            foreach ($document->section($section) as $package => $entry) {
                if (! is_string($package) || ! is_array($entry)) {
                    continue;
                }

                /** @var array<string, mixed> $entry */
                $grants[$package] = Grant::fromArray($entry, $package, $dev);
            }
        }

        return new self($document->path, $document, $grants);
    }

    public function exists(): bool
    {
        return $this->document->has('require') || $this->document->has('require-dev');
    }

    public function has(string $package): bool
    {
        return isset($this->grants[$package]);
    }

    public function grantFor(string $package): ?Grant
    {
        return $this->grants[$package] ?? null;
    }

    /**
     * @return array<string, Grant>
     */
    public function all(): array
    {
        return $this->grants;
    }

    public function count(): int
    {
        return count($this->grants);
    }

    public function record(Grant $grant): void
    {
        $this->grants[$grant->package] = $grant;
    }

    public function save(): void
    {
        $sections = [];

        foreach (array_keys(self::SECTIONS) as $section) {
            $sections[$section] = [];
        }

        $grants = $this->grants;
        ksort($grants, SORT_STRING);

        foreach ($grants as $package => $grant) {
            $sections[$grant->section()][$package] = $grant->toArray();
        }

        foreach ($sections as $section => $entries) {
            if ($entries === []) {
                $sections[$section] = (object) [];
            }
        }

        $this->document->write($sections);
    }
}
