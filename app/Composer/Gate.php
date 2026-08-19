<?php

declare(strict_types=1);

namespace App\Composer;

final readonly class Gate
{
    public const string ENVIRONMENT = 'PET_INSIDE_COMPOSER';

    public function __construct(
        public string $rootPath,
        private string $binDir,
    ) {}

    public function binary(): ?string
    {
        foreach ([$this->binDir.'/pet', $this->rootPath.'/pet'] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function hasLedger(): bool
    {
        return is_file($this->rootPath.'/pet.json');
    }

    /**
     * @return array<int, string>|null
     */
    public function command(bool $decorated, bool $verbose = false): ?array
    {
        $binary = $this->binary();

        if ($binary === null || ! $this->hasLedger()) {
            return null;
        }

        $command = [PHP_BINARY, $binary, 'audit'];

        if ($decorated) {
            $command[] = '--ansi';
        }

        if ($verbose) {
            $command[] = '-v';
        }

        return $command;
    }

    /**
     * @return array<string, string>
     */
    public function environment(): array
    {
        return [self::ENVIRONMENT => '1'];
    }

    public function baselineNotice(): ?string
    {
        if ($this->binary() === null || $this->hasLedger()) {
            return null;
        }

        return 'pet has no ledger in this project yet. Run `pet trust` to record what you trust today.';
    }
}
