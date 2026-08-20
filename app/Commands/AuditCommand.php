<?php

declare(strict_types=1);

namespace App\Commands;

use App\Delta\Delta;
use App\Delta\DeltaResolver;
use App\Exceptions\PortoException;
use App\Ledger\Auditor;
use App\Ledger\AuditStatus;
use App\Ledger\PackageAudit;
use App\Lock\Project;
use App\Support\Bytes;
use App\Support\ComposerOperation;
use App\Support\ComposerPlan;
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
        {--plan= : Audit the operations that this composer plan file holds}
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
        } catch (PortoException $portoException) {
            $this->components->error($portoException->getMessage());

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
            AuditStatus::Unknown => 0,
            AuditStatus::Changed => 1,
            AuditStatus::Ungranted => 2,
            AuditStatus::Covered => 3,
        };
    }

    private function statusColor(AuditStatus $status): string
    {
        return match ($status) {
            AuditStatus::Unknown, AuditStatus::Changed => 'red',
            AuditStatus::Ungranted, AuditStatus::Covered => 'yellow',
        };
    }

    /**
     * @param  array<string, PackageAudit>  $failing
     */
    private function holdsPending(array $failing): bool
    {
        foreach ($failing as $audit) {
            if ($audit->pending()) {
                return true;
            }
        }

        return false;
    }

    private function plan(): ?ComposerPlan
    {
        $path = $this->stringOption('plan');

        return $path === null ? null : ComposerPlan::fromFile($path);
    }

    private function auditProject(Project $project): int
    {
        try {
            $auditor = Auditor::forProject($project, $this->plan(), $this->option('no-cache') !== true);

            $discrepancies = $auditor->lockDiscrepancies();
            $report = $auditor->report();
        } catch (PortoException $portoException) {
            $this->components->error($portoException->getMessage());

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
                        'state' => $c->state->value,
                        'from' => $c->from,
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

        if ($discrepancies !== []) {
            $this->components->error('The installed tree does not match composer.lock.');
        }

        if (! $auditor->ledger->exists()) {
            $this->components->warn(sprintf(
                'No ledger yet. `porto trust` records every installed package in %s.',
                $this->relative($project->rootPath, $auditor->ledger->path),
            ));
            $this->newLine();
        }

        if ($failing === []) {
            $this->components->info(sprintf('All %d packages are covered.', $report->total()));
            $this->newLine();

            return $this->verdict($failing, $discrepancies);
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
                    $this->statusColor($audit->status),
                    $audit->package,
                    $audit->versions(),
                    $audit->dev ? ' <fg=gray>(dev)</>' : '',
                ),
                $audit->status === AuditStatus::Unknown
                    ? '<fg=red>bytes not readable</>'
                    : sprintf('<fg=gray>%d files to review (%s)</>', $review['files'], $review['scope']),
            );

            $this->line($audit->status === AuditStatus::Unknown
                ? sprintf('      <fg=gray>%s</>', $audit->reason())
                : sprintf(
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
            ? sprintf('%d package(s) are not covered. Record them with `porto trust`.', count($failing))
            : sprintf(
                '%d package(s) are not covered. Read every change with `%s`, then record them with `porto trust`.',
                count($failing),
                Invitation::verbose('porto audit -v'),
            ));

        if ($this->holdsPending($failing)) {
            $this->components->warn('composer holds those bytes out of vendor/ until you record them. Then run `composer install`.');
        }

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
            $auditor = Auditor::forProject($project, $this->plan(), $this->option('no-cache') !== true);
            $audit = $auditor->auditOfName($package);
        } catch (PortoException $portoException) {
            $this->components->error($portoException->getMessage());

            return self::FAILURE;
        }

        $requested = $this->stringOption('from') !== null;
        $renderer = new DeltaRenderer($this->output);
        $delta = null;
        $unresolved = null;

        if ($audit->status === AuditStatus::Unknown) {
            $this->newLine();
            $this->components->error($audit->cause ?? 'porto cannot read those bytes.');

            return self::FAILURE;
        }

        $from = $this->deltaFrom($audit);

        if ($audit->pending() && ! $requested) {
            $delta = $this->incomingDelta($project, $auditor, $audit);
        } elseif ($from !== null) {
            try {
                $delta = DeltaResolver::forProject($project)->resolve(
                    package: $audit->package,
                    from: $from,
                    to: $this->stringOption('to') ?? ($audit->pending() ? $audit->version : null),
                    useCache: $this->option('no-cache') !== true,
                );
            } catch (PortoException $portoException) {
                if ($requested) {
                    $this->components->error($portoException->getMessage());

                    return self::FAILURE;
                }

                $unresolved = sprintf(
                    'Could not build the delta from the granted %s: %s',
                    $from,
                    $portoException->getMessage(),
                );
            }
        }

        $covered = $audit->status === AuditStatus::Covered;

        if ($this->option('json') === true) {
            $this->output->write(Json::encode([
                'package' => $audit->package,
                'version' => $audit->version,
                'state' => $audit->state->value,
                'from' => $audit->from,
                'source' => $audit->source->value,
                'hash' => (string) $audit->hash,
                'files' => $audit->files,
                'bytes' => $audit->bytes,
                'path' => $audit->path,
                'delta' => $delta instanceof Delta ? $renderer->toArray($delta) : null,
            ]));

            return $covered ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->components->twoColumnDetail(
            sprintf('<fg=default;options=bold>%s</>', $audit->package),
            sprintf('<fg=gray>%s</>', $audit->versions()),
        );

        if ($audit->package !== $package) {
            $this->components->twoColumnDetail('provides', $package);
        }

        if ($audit->pending()) {
            $this->components->twoColumnDetail('state', 'composer would write these bytes to vendor/');
        }

        $this->components->twoColumnDetail('hash', (string) $audit->hash);
        $this->components->twoColumnDetail('source', $audit->source->value);
        $this->components->twoColumnDetail(
            'contents',
            sprintf('%d files, %s', $audit->files, Bytes::human($audit->bytes)),
        );
        $this->components->twoColumnDetail(
            'path',
            $this->relative($project->rootPath, $audit->path ?? ''),
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
            $this->components->info(sprintf('Record these bytes with `porto trust %s`.', $audit->package));
        }

        return $covered ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{files: int, scope: string, delta: ?Delta}
     */
    private function review(Project $project, Auditor $auditor, PackageAudit $audit): array
    {
        if ($audit->status === AuditStatus::Unknown) {
            return ['files' => 0, 'scope' => 'not readable', 'delta' => null];
        }

        if ($audit->pending()) {
            return $this->pendingReview($project, $auditor, $audit);
        }

        $from = $auditor->ledger->grantFor($audit->package)?->version;

        if ($from === null) {
            return $this->wholePackage($audit);
        }

        try {
            $delta = DeltaResolver::forProject($project)->resolve($audit->package, $from);
        } catch (PortoException) {
            return $this->wholePackage($audit);
        }

        return [
            'files' => count($delta->changes()),
            'scope' => sprintf('delta from %s', $delta->from),
            'delta' => $delta,
        ];
    }

    /**
     * @return array{files: int, scope: string, delta: ?Delta}
     */
    private function pendingReview(Project $project, Auditor $auditor, PackageAudit $audit): array
    {
        $delta = $this->incomingDelta($project, $auditor, $audit);

        return $delta instanceof Delta
            ? [
                'files' => count($delta->changes()),
                'scope' => sprintf('delta from %s', $delta->from),
                'delta' => $delta,
            ]
            : $this->wholePackage($audit);
    }

    private function incomingDelta(Project $project, Auditor $auditor, PackageAudit $audit): ?Delta
    {
        $operation = $auditor->plan()->of($audit->package);

        if (! $operation instanceof ComposerOperation) {
            return null;
        }

        $installed = $auditor->installed();

        try {
            return DeltaResolver::forProject($project)->incoming(
                target: $auditor->target($operation, $audit->version, $audit->dev),
                installed: $installed->has($audit->package) ? $installed->get($audit->package) : null,
                useCache: $this->option('no-cache') !== true,
            );
        } catch (PortoException) {
            return null;
        }
    }

    /**
     * @return array{files: int, scope: string, delta: null}
     */
    private function wholePackage(PackageAudit $audit): array
    {
        return ['files' => $audit->files, 'scope' => 'whole package', 'delta' => null];
    }

    private function deltaFrom(PackageAudit $audit): ?string
    {
        $requested = $this->stringOption('from');

        if ($requested !== null) {
            return $requested;
        }

        $granted = $audit->grant?->version;

        return $granted === null || $granted === $audit->version ? null : $granted;
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
