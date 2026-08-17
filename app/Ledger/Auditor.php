<?php

declare(strict_types=1);

namespace App\Ledger;

use App\Identity\Fingerprint;
use App\Identity\Fingerprinter;
use App\Lock\InstalledRepository;
use App\Lock\LockFile;
use App\Lock\Package;
use App\Lock\Project;

final readonly class Auditor
{
    public function __construct(
        public Project $project,
        public Ledger $ledger,
        private InstalledRepository $installed,
        private Fingerprinter $fingerprinter,
    ) {}

    public static function forProject(Project $project): self
    {
        $installed = InstalledRepository::fromProject($project);

        return new self(
            project: $project,
            ledger: Ledger::forProject($project),
            installed: $installed,
            fingerprinter: new Fingerprinter($installed),
        );
    }

    public function installed(): InstalledRepository
    {
        return $this->installed;
    }

    public function fingerprinter(): Fingerprinter
    {
        return $this->fingerprinter;
    }

    public function report(): AuditReport
    {
        $results = [];

        foreach ($this->installed->all() as $name => $package) {
            $results[$name] = $this->auditOf($package);
        }

        return new AuditReport($results);
    }

    public function auditOf(Package $package): PackageAudit
    {
        $fingerprint = $this->fingerprinter->ofPackage($package);
        $grant = $this->ledger->grantFor($package->name);

        return new PackageAudit(
            package: $package->name,
            version: $fingerprint->version,
            hash: $fingerprint->hash,
            dev: $package->dev,
            status: $this->statusOf($grant, $fingerprint),
            files: $fingerprint->files,
            bytes: $fingerprint->bytes,
            grant: $grant,
            source: $fingerprint->source,
        );
    }

    /**
     * @return array<int, string> the discrepancies found
     */
    public function lockDiscrepancies(): array
    {
        $locked = LockFile::fromProject($this->project)->packages();
        $installed = $this->installed->all();

        $problems = [];

        foreach ($locked as $name => $package) {
            if (! isset($installed[$name])) {
                $problems[] = sprintf('%s is in composer.lock at %s but is not installed', $name, $package->version);

                continue;
            }

            if ($installed[$name]->version !== $package->version) {
                $problems[] = sprintf(
                    '%s is installed at %s but composer.lock says %s',
                    $name,
                    $installed[$name]->version,
                    $package->version,
                );
            }
        }

        foreach ($installed as $name => $package) {
            if (! isset($locked[$name])) {
                $problems[] = sprintf('%s is installed at %s but is not in composer.lock', $name, $package->version);
            }
        }

        return $problems;
    }

    private function statusOf(?Grant $grant, Fingerprint $fingerprint): AuditStatus
    {
        if (! $grant instanceof Grant) {
            return AuditStatus::Ungranted;
        }

        return $grant->covers($fingerprint->hash) ? AuditStatus::Covered : AuditStatus::Changed;
    }
}
