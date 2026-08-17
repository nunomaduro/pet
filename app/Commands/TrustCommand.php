<?php

declare(strict_types=1);

namespace App\Commands;

use App\Delta\Delta;
use App\Delta\DeltaResolver;
use App\Exceptions\PetException;
use App\Identity\Fingerprint;
use App\Ledger\Auditor;
use App\Ledger\AuditStatus;
use App\Ledger\Grant;
use App\Ledger\PackageAudit;
use App\Lock\Project;
use App\Support\DeltaRenderer;
use LaravelZero\Framework\Commands\Command;

final class TrustCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'trust
        {packages?* : Trust these installed packages, as vendor/name}
        {--from= : Show the delta from this version rather than the trusted one}
        {--notes= : A note to record alongside the entry}
        {--path= : The project directory to audit (defaults to the current one)}';

    /**
     * @var string
     */
    protected $description = 'Record the bytes of the installed packages that you trust, or baseline every one';

    public function handle(): int
    {
        try {
            $project = Project::locate($this->stringOption('path') ?? (string) getcwd());
            $auditor = Auditor::forProject($project);
        } catch (PetException $petException) {
            $this->components->error($petException->getMessage());

            return self::FAILURE;
        }

        $packages = $this->packages();

        return $packages === []
            ? $this->trustProject($project, $auditor)
            : $this->trustPackages($project, $auditor, $packages);
    }

    private function trustProject(Project $project, Auditor $auditor): int
    {
        if ($this->stringOption('from') !== null) {
            $this->components->error('The --from option needs one package. Run `pet trust <package>`.');

            return self::FAILURE;
        }

        try {
            $report = $auditor->report();
        } catch (PetException $petException) {
            $this->components->error($petException->getMessage());

            return self::FAILURE;
        }

        $targets = $report->failing();

        $this->newLine();

        if ($targets === []) {
            $this->components->info(sprintf('All %d installed packages are already covered.', $report->total()));
            $this->newLine();

            return self::SUCCESS;
        }

        $this->renderTargets($targets);

        $created = ! $auditor->ledger->exists();

        try {
            foreach ($targets as $audit) {
                $auditor->ledger->record(new Grant(
                    package: $audit->package,
                    version: $audit->version,
                    hash: $audit->hash,
                    dev: $audit->dev,
                    notes: $this->stringOption('notes') ?? $audit->grant?->notes,
                ));
            }

            $auditor->ledger->save();
        } catch (PetException $petException) {
            $this->components->error($petException->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info($created
            ? sprintf(
                'Trusted %d package(s), and wrote %s.',
                count($targets),
                $this->relative($project->rootPath, $auditor->ledger->path),
            )
            : sprintf('Trusted %d package(s).', count($targets)));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, PackageAudit>  $targets
     */
    private function renderTargets(array $targets): void
    {
        $this->line(sprintf('  <options=bold>to trust</> <fg=gray>(%d)</>', count($targets)));
        $this->newLine();

        foreach ($targets as $audit) {
            $this->components->twoColumnDetail(
                sprintf(
                    '<fg=%s>%s</> <fg=gray>%s</>%s',
                    $audit->status === AuditStatus::Changed ? 'red' : 'yellow',
                    $audit->package,
                    $audit->version,
                    $audit->dev ? ' <fg=gray>(dev)</>' : '',
                ),
                sprintf('<fg=gray>%s</>', $audit->reason()),
            );
        }

        $this->newLine();
    }

    /**
     * @param  array<int, string>  $names
     */
    private function trustPackages(Project $project, Auditor $auditor, array $names): int
    {
        if (count($names) > 1 && $this->stringOption('from') !== null) {
            $this->components->error('The --from option needs one package. Run `pet trust <package> --from=<version>`.');

            return self::FAILURE;
        }

        $recorded = [];

        foreach ($names as $name) {
            try {
                $package = $auditor->installed()->get($name);
                $fingerprint = $auditor->fingerprinter()->ofPackage($package);
                $audit = $auditor->auditOf($package);
            } catch (PetException $petException) {
                $this->components->error($petException->getMessage());

                return self::FAILURE;
            }

            if ($audit->status === AuditStatus::Covered) {
                $this->newLine();
                $this->components->info(sprintf(
                    '%s %s is already covered (%s).',
                    $package->name,
                    $fingerprint->version,
                    $audit->reason(),
                ));

                continue;
            }

            $this->renderSubject($fingerprint, $audit);

            $delta = $this->delta($auditor, $project, $package->name);

            if ($delta instanceof Delta) {
                (new DeltaRenderer($this->output))->report($delta);
            } else {
                $this->newLine();
                $this->components->warn(sprintf('Review the tree at %s before you trust it.', $fingerprint->path));
            }

            $grant = new Grant(
                package: $package->name,
                version: $fingerprint->version,
                hash: $fingerprint->hash,
                dev: $package->dev,
                notes: $this->stringOption('notes') ?? $audit->grant?->notes,
            );

            $auditor->ledger->record($grant);

            $recorded[] = $grant;
        }

        if ($recorded === []) {
            return self::SUCCESS;
        }

        try {
            $auditor->ledger->save();
        } catch (PetException $petException) {
            $this->components->error($petException->getMessage());

            return self::FAILURE;
        }

        $this->components->info(count($recorded) === 1
            ? sprintf(
                'Recorded %s %s at %s.',
                $recorded[0]->package,
                $recorded[0]->version,
                $recorded[0]->hash->short(),
            )
            : sprintf('Recorded %d package(s).', count($recorded)));

        return self::SUCCESS;
    }

    private function delta(Auditor $auditor, Project $project, string $package): ?Delta
    {
        $from = $this->stringOption('from') ?? $auditor->ledger->grantFor($package)?->version;

        if ($from === null) {
            return null;
        }

        try {
            return DeltaResolver::forProject($project)->resolve($package, $from);
        } catch (PetException $petException) {
            $this->components->warn(sprintf('Could not build a delta from %s: %s', $from, $petException->getMessage()));

            return null;
        }
    }

    private function renderSubject(Fingerprint $fingerprint, PackageAudit $audit): void
    {
        $this->newLine();
        $this->components->twoColumnDetail(
            sprintf('<options=bold>%s</>', $fingerprint->package),
            sprintf(
                '%s <fg=gray>(%s)</>',
                $fingerprint->version,
                $audit->status === AuditStatus::Ungranted ? 'never trusted' : 'bytes changed',
            ),
        );
        $this->components->twoColumnDetail(
            '<fg=gray>hash</>',
            sprintf('<fg=gray>%s (%s)</>', $fingerprint->hash, $fingerprint->source->value),
        );
        $this->components->twoColumnDetail(
            '<fg=gray>contents</>',
            sprintf('<fg=gray>%d files</>', $fingerprint->files),
        );
    }

    /**
     * @return array<int, string>
     */
    private function packages(): array
    {
        $packages = $this->argument('packages');

        return is_array($packages)
            ? array_values(array_filter($packages, static fn (mixed $package): bool => is_string($package) && $package !== ''))
            : [];
    }

    private function relative(string $root, string $path): string
    {
        return str_starts_with($path, $root.'/') ? mb_substr($path, mb_strlen($root) + 1) : $path;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
