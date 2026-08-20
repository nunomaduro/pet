<?php

declare(strict_types=1);

namespace App\Ledger;

use App\Exceptions\PortoException;
use App\Identity\Fingerprint;
use App\Identity\Fingerprinter;
use App\Lock\InstalledRepository;
use App\Lock\LockFile;
use App\Lock\Package;
use App\Lock\Project;
use App\Registry\ArchiveFetcher;
use App\Registry\Packagist;
use App\Support\ComposerOperation;
use App\Support\ComposerPlan;

final readonly class Auditor
{
    public function __construct(
        public Project $project,
        public Ledger $ledger,
        private InstalledRepository $installed,
        private Fingerprinter $fingerprinter,
        private LockFile $lock,
        private ComposerPlan $plan,
        private Packagist $packagist,
        private bool $useCache = true,
    ) {}

    public static function forProject(Project $project, ?ComposerPlan $plan = null, bool $useCache = true): self
    {
        $installed = InstalledRepository::fromProject($project);
        $lock = LockFile::fromProject($project);

        return new self(
            project: $project,
            ledger: Ledger::forProject($project),
            installed: $installed,
            fingerprinter: new Fingerprinter($installed, ArchiveFetcher::default()),
            lock: $lock,
            plan: $plan ?? ComposerPlan::between($lock, $installed),
            packagist: Packagist::default(),
            useCache: $useCache,
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

    public function plan(): ComposerPlan
    {
        return $this->plan;
    }

    public function report(): AuditReport
    {
        $results = [];

        foreach ($this->installed->all() as $name => $package) {
            if ($this->plan->touches($name)) {
                continue;
            }

            $results[$name] = $this->auditOf($package);
        }

        foreach ($this->plan->incoming() as $operation) {
            $results[$operation->package] = $this->auditOfIncoming($operation);
        }

        ksort($results, SORT_STRING);

        return new AuditReport($results);
    }

    public function auditOfName(string $name): PackageAudit
    {
        $operation = $this->plan->of($name);

        return $operation instanceof ComposerOperation && $operation->to !== null
            ? $this->auditOfIncoming($operation)
            : $this->auditOf($this->installed->get($name));
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
            path: $fingerprint->path,
        );
    }

    public function auditOfIncoming(ComposerOperation $operation): PackageAudit
    {
        $grant = $this->ledger->grantFor($operation->package);
        $version = $operation->to ?? '';
        $dev = $this->isDev($operation->package);

        try {
            $target = $this->target($operation, $version, $dev);
            $fingerprint = $this->fingerprinter->ofIncoming($target, $this->useCache);
        } catch (PortoException $portoException) {
            return new PackageAudit(
                package: $operation->package,
                version: $version,
                hash: null,
                dev: $dev,
                status: AuditStatus::Unknown,
                files: 0,
                bytes: 0,
                grant: $grant,
                state: PackageState::Pending,
                from: $operation->from,
                cause: sprintf(
                    'composer would install %s and porto cannot read those bytes: %s',
                    $version,
                    $portoException->getMessage(),
                ),
            );
        }

        return new PackageAudit(
            package: $operation->package,
            version: $fingerprint->version,
            hash: $fingerprint->hash,
            dev: $dev,
            status: $this->statusOf($grant, $fingerprint),
            files: $fingerprint->files,
            bytes: $fingerprint->bytes,
            grant: $grant,
            source: $fingerprint->source,
            state: PackageState::Pending,
            from: $operation->from,
            path: $fingerprint->path,
        );
    }

    public function target(ComposerOperation $operation, string $version, bool $dev): Package
    {
        $locked = $this->lock->packages()[$operation->package] ?? null;

        $metadata = $locked instanceof Package && $locked->version === $version
            ? $locked
            : $this->packagist->version($operation->package, $version);

        return $metadata
            ->withDist($operation->distUrl, $operation->distReference)
            ->withDev($dev);
    }

    /**
     * @return array<int, string> the discrepancies found
     */
    public function lockDiscrepancies(): array
    {
        $locked = $this->lock->packages();
        $installed = $this->installed->all();

        $problems = [];

        $explained = $this->plan->explains();

        foreach ($locked as $name => $package) {
            if ($explained && $this->plan->touches($name)) {
                continue;
            }

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
            if (! isset($locked[$name]) && (! $explained || ! $this->plan->touches($name))) {
                $problems[] = sprintf('%s is installed at %s but is not in composer.lock', $name, $package->version);
            }
        }

        return $problems;
    }

    private function isDev(string $package): bool
    {
        $locked = $this->lock->packages()[$package] ?? null;

        if ($locked instanceof Package) {
            return $locked->dev;
        }

        return $this->installed->has($package) && $this->installed->get($package)->dev;
    }

    private function statusOf(?Grant $grant, Fingerprint $fingerprint): AuditStatus
    {
        if (! $grant instanceof Grant) {
            return AuditStatus::Ungranted;
        }

        return $grant->covers($fingerprint->hash) ? AuditStatus::Covered : AuditStatus::Changed;
    }
}
