<?php

declare(strict_types=1);

namespace App\Commands;

use App\Delta\Delta;
use App\Delta\DeltaResolver;
use App\Exceptions\PetException;
use App\Identity\Fingerprinter;
use App\Ledger\Auditor;
use App\Ledger\AuditStatus;
use App\Ledger\Grant;
use App\Ledger\Ledger;
use App\Ledger\PackageAudit;
use App\Lock\Package;
use App\Lock\Project;
use App\Support\Bytes;
use App\Support\DeltaRenderer;
use App\Support\Invitation;
use App\Support\Json;

final class AuditCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'audit
        {package? : Audit one installed package, as vendor/name}
        {--from= : Show the delta from this version rather than the trusted one}
        {--to= : The version to compare to (defaults to the installed one)}
        {--path= : The project directory to audit (defaults to the current one)}
        {--bucket= : Limit the delta to one bucket: install-manifest, opaque, runtime-source, inert}
        {--no-cache : Re-download archives instead of reusing the cache}
        {--json : Emit machine-readable output}';

    /**
     * @var string
     */
    protected $description = 'Show what is unaudited, worst first, or audit one package';

    public function handle(): int
    {
        try {
            $project = Project::locate($this->stringOption('path') ?? (string) getcwd());
        } catch (PetException $petException) {
            $this->components->error($petException->getMessage());

            return self::FAILURE;
        }

        $package = $this->stringArgument('package');

        return $package === null
            ? $this->auditProject($project)
            : $this->auditPackage($project, $package);
    }

    private static function statusWeight(AuditStatus $status): int
    {
        return match ($status) {
            AuditStatus::Changed => 0,
            AuditStatus::Ungranted => 1,
            AuditStatus::Covered => 2,
        };
    }

    private function auditProject(Project $project): int
    {
        try {
            $auditor = Auditor::forProject($project);

            $discrepancies = $auditor->lockDiscrepancies();
            $report = $auditor->report();
        } catch (PetException $petException) {
            $this->components->error($petException->getMessage());

            return self::FAILURE;
        }

        $failing = $report->failing();
        $reviews = [];

        foreach ($failing as $audit) {
            $reviews[$audit->package] = $this->review($project, $auditor, $audit);
        }

        uasort($failing, static fn (PackageAudit $a, PackageAudit $b): int => [
            self::statusWeight($a->status),
            $reviews[$b->package]['files'],
            $a->package,
        ] <=> [
            self::statusWeight($b->status),
            $reviews[$a->package]['files'],
            $b->package,
        ]);

        $renderer = new DeltaRenderer($this->output);

        if ($this->option('json') === true) {
            $this->output->write(Json::encode([
                'total' => $report->total(),
                'covered' => $report->coveredCount(),
                'percentage' => $report->percentage(),
                'counts' => $report->counts(),
                'lock_discrepancies' => $discrepancies,
                'unaudited' => array_values(array_map(static function (PackageAudit $c) use ($reviews, $renderer): array {
                    $review = $reviews[$c->package];

                    return [
                        'package' => $c->package,
                        'version' => $c->version,
                        'status' => $c->status->value,
                        'dev' => $c->dev,
                        'files' => $c->files,
                        'files_to_review' => $review['files'],
                        'scope' => $review['scope'],
                        'delta' => $review['delta'] instanceof Delta ? $renderer->toArray($review['delta']) : null,
                    ];
                }, $failing)),
            ]));

            return $this->verdict($failing, $discrepancies);
        }

        $this->newLine();

        foreach ($discrepancies as $discrepancy) {
            $this->components->error($discrepancy);
        }

        if (! $auditor->ledger->exists()) {
            $this->components->warn(sprintf(
                'No ledger yet. `pet trust` records every installed package in %s.',
                $this->relative($project->rootPath, $auditor->ledger->path),
            ));
            $this->newLine();
        }

        if ($failing === []) {
            $this->components->info(sprintf('All %d packages are covered.', $report->total()));
            $this->newLine();

            if ($this->verdict($failing, $discrepancies) === self::FAILURE) {
                $this->components->error('The installed tree does not match composer.lock.');

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $this->line(sprintf('  <options=bold>to review</> <fg=gray>(%d, worst first)</>', count($failing)));
        $this->newLine();

        $endsWithDelta = false;

        foreach ($failing as $audit) {
            $review = $reviews[$audit->package];
            $endsWithDelta = $review['delta'] instanceof Delta;

            $this->components->twoColumnDetail(
                sprintf(
                    '<fg=%s>%s</> <fg=gray>%s</>%s',
                    $audit->status === AuditStatus::Changed ? 'red' : 'yellow',
                    $audit->package,
                    $audit->version,
                    $audit->dev ? ' <fg=gray>(dev)</>' : '',
                ),
                sprintf('<fg=gray>%d files to review (%s)</>', $review['files'], $review['scope']),
            );

            $this->line(sprintf(
                '      <fg=gray>%s  ·  %s</>',
                $audit->reason(),
                Bytes::human($audit->bytes),
            ));

            if ($review['delta'] instanceof Delta) {
                $this->newLine();
                $renderer->buckets($review['delta']);
            }
        }

        if (! $endsWithDelta) {
            $this->newLine();
        }

        $this->components->twoColumnDetail(
            '<options=bold>audited</>',
            sprintf('%d / %d  <fg=gray>(%s%%)</>', $report->coveredCount(), $report->total(), $report->percentage()),
        );
        $this->newLine();

        $this->components->error($this->output->isVerbose()
            ? sprintf('%d package(s) are not covered. Record them with `pet trust`.', count($failing))
            : sprintf(
                '%d package(s) are not covered. Read every change with `%s`, then record them with `pet trust`.',
                count($failing),
                Invitation::verbose('pet audit -v'),
            ));

        return self::FAILURE;
    }

    /**
     * @param  array<string, PackageAudit>  $failing
     * @param  array<int, string>  $discrepancies
     */
    private function verdict(array $failing, array $discrepancies): int
    {
        return $failing === [] && $discrepancies === [] ? self::SUCCESS : self::FAILURE;
    }

    private function auditPackage(Project $project, string $package): int
    {
        try {
            $fingerprinter = Fingerprinter::forProject($project);
            $installed = $fingerprinter->repository()->get($package);
            $fingerprint = $fingerprinter->ofPackage($installed);
            $grant = Ledger::forProject($project)->grantFor($installed->name);
        } catch (PetException $petException) {
            $this->components->error($petException->getMessage());

            return self::FAILURE;
        }

        $covered = $grant instanceof Grant && $grant->covers($fingerprint->hash);
        $from = $this->deltaFrom($grant, $installed);
        $requested = $this->stringOption('from') !== null;
        $renderer = new DeltaRenderer($this->output);
        $delta = null;
        $unresolved = null;

        if ($from !== null) {
            try {
                $delta = DeltaResolver::forProject($project)->resolve(
                    package: $installed->name,
                    from: $from,
                    to: $this->stringOption('to'),
                    useCache: $this->option('no-cache') !== true,
                );
            } catch (PetException $petException) {
                if ($requested) {
                    $this->components->error($petException->getMessage());

                    return self::FAILURE;
                }

                $unresolved = sprintf(
                    'Could not build the delta from the granted %s: %s',
                    $from,
                    $petException->getMessage(),
                );
            }
        }

        if ($this->option('json') === true) {
            $this->output->write(Json::encode([
                'package' => $fingerprint->package,
                'version' => $fingerprint->version,
                'source' => $fingerprint->source->value,
                'hash' => (string) $fingerprint->hash,
                'files' => $fingerprint->files,
                'bytes' => $fingerprint->bytes,
                'path' => $fingerprint->path,
                'delta' => $delta instanceof Delta ? $renderer->toArray($delta) : null,
            ]));

            return $covered ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->components->twoColumnDetail(
            sprintf('<fg=default;options=bold>%s</>', $fingerprint->package),
            sprintf('<fg=gray>%s</>', $fingerprint->version),
        );

        if ($installed->name !== $package) {
            $this->components->twoColumnDetail('provides', $package);
        }

        $this->components->twoColumnDetail('hash', (string) $fingerprint->hash);
        $this->components->twoColumnDetail('source', $fingerprint->source->value);
        $this->components->twoColumnDetail(
            'contents',
            sprintf('%d files, %s', $fingerprint->files, Bytes::human($fingerprint->bytes)),
        );
        $this->components->twoColumnDetail(
            'path',
            $this->relative($project->rootPath, $fingerprint->path),
        );

        if ($delta instanceof Delta) {
            $renderer->report($delta, $this->stringOption('bucket'));
        } else {
            $this->newLine();

            if ($unresolved !== null) {
                $this->components->warn($unresolved);
            }
        }

        if (! $covered) {
            $this->components->info(sprintf('Record these bytes with `pet trust %s`.', $installed->name));
        }

        return $covered ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{files: int, scope: string, delta: ?Delta}
     */
    private function review(Project $project, Auditor $auditor, PackageAudit $audit): array
    {
        $from = $auditor->ledger->grantFor($audit->package)?->version;

        if ($from === null) {
            return $this->wholePackage($audit);
        }

        try {
            $delta = DeltaResolver::forProject($project)->resolve($audit->package, $from);
        } catch (PetException) {
            return $this->wholePackage($audit);
        }

        return [
            'files' => count($delta->changes()),
            'scope' => sprintf('delta from %s', $delta->from),
            'delta' => $delta,
        ];
    }

    /**
     * @return array{files: int, scope: string, delta: null}
     */
    private function wholePackage(PackageAudit $audit): array
    {
        return ['files' => $audit->files, 'scope' => 'whole package', 'delta' => null];
    }

    private function deltaFrom(?Grant $grant, Package $installed): ?string
    {
        $requested = $this->stringOption('from');

        if ($requested !== null) {
            return $requested;
        }

        $granted = $grant?->version;

        return $granted === null || $granted === $installed->version ? null : $granted;
    }

    private function relative(string $root, string $path): string
    {
        return str_starts_with($path, $root.'/') ? mb_substr($path, mb_strlen($root) + 1) : $path;
    }

    private function stringArgument(string $name): ?string
    {
        $value = $this->argument($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
