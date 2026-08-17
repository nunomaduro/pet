<?php

declare(strict_types=1);

namespace App\Ledger;

use App\Identity\InstallSource;
use App\Identity\TreeHash;

final readonly class PackageAudit
{
    public function __construct(
        public string $package,
        public string $version,
        public TreeHash $hash,
        public bool $dev,
        public AuditStatus $status,
        public int $files,
        public int $bytes,
        public ?Grant $grant = null,
        public InstallSource $source = InstallSource::Dist,
    ) {}

    public function fails(): bool
    {
        return $this->status->fails();
    }

    public function reason(): string
    {
        return match ($this->status) {
            AuditStatus::Covered => sprintf('trusted at %s', $this->hash->short()),
            AuditStatus::Ungranted => sprintf('no entry; this tree is %s', $this->hash->short()),
            AuditStatus::Changed => $this->changedReason(),
        };
    }

    private function changedReason(): string
    {
        $granted = $this->grant instanceof Grant ? $this->grant->version : 'nothing';

        if (! $this->grant instanceof Grant || $this->grant->version !== $this->version) {
            return sprintf('%s was trusted, %s is installed', $granted, $this->version);
        }

        $reason = sprintf(
            '%s is still installed but its bytes changed (%s, not %s)',
            $this->version,
            $this->hash->short(),
            $this->grant->hash->short(),
        );

        if ($this->source === InstallSource::Source) {
            $reason .= '; this tree came from --prefer-source';
        }

        return $reason;
    }
}
