<?php

declare(strict_types=1);

namespace App\Support;

use App\Delta\Bucket;
use App\Delta\Change;
use App\Delta\Delta;
use App\Delta\ManifestChange;
use App\Delta\UnifiedDiff;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;

final readonly class DeltaRenderer
{
    private const int MAX_PATHS = 20;

    private Factory $components;

    public function __construct(
        private OutputStyle $output,
    ) {
        $this->components = new Factory($output);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Delta $delta): array
    {
        return [
            'from' => $delta->from,
            'to' => $delta->to,
            'from_hash' => (string) $delta->fromHash,
            'to_hash' => (string) $delta->toHash,
            'source' => $delta->source->value,
            'compared_against_install' => $delta->toIsLocalInstall,
            'notes' => $delta->notes,
            'counts' => $delta->counts(),
            'inert_only' => $delta->isInertOnly(),
            'needs_no_review' => $delta->needsNoReview(),
            'review_blockers' => $delta->reviewBlockers(),
            'manifest_keys' => $delta->manifestChange?->changedKeys() ?? [],
            'changes' => array_map(static fn (Change $change): array => [
                'path' => $change->path,
                'status' => $change->status->value,
                'bucket' => $change->bucket->value,
            ], $delta->changes()),
        ];
    }

    public function report(Delta $delta, ?string $only = null): void
    {
        $this->output->newLine();
        $this->output->writeln(sprintf(
            '  <options=bold>delta</> <fg=gray>(%s → %s)</>',
            $delta->from,
            $delta->to,
        ));

        $this->components->twoColumnDetail(
            '<fg=gray>identity</>',
            sprintf('<fg=gray>%s → %s (%s)</>', $delta->fromHash->short(), $delta->toHash->short(), $delta->source->value),
        );

        if ($delta->toIsLocalInstall) {
            $this->components->twoColumnDetail('<fg=gray>compared against</>', '<fg=gray>your installed tree</>');
        }

        foreach ($delta->notes as $note) {
            $this->components->warn($note);
        }

        $this->output->newLine();

        if ($delta->isEmpty()) {
            $this->components->info(sprintf('No files differ between %s and %s.', $delta->from, $delta->to));

            return;
        }

        $verbose = $this->output->isVerbose();

        foreach (Bucket::inReviewOrder() as $bucket) {
            if ($only !== null && $only !== $bucket->value) {
                continue;
            }

            $changes = $delta->inBucket($bucket);

            if ($changes === []) {
                continue;
            }

            $this->output->writeln(sprintf(
                '  <options=bold>%s</> <fg=gray>(%d)</>%s',
                $bucket->label(),
                count($changes),
                $bucket === Bucket::Opaque ? '  <fg=red>cannot be reviewed — trust and provenance only</>' : '',
            ));

            $shown = $verbose ? $changes : array_slice($changes, 0, self::MAX_PATHS);

            foreach ($shown as $change) {
                $this->renderChange($delta, $change);

                if ($verbose && $bucket !== Bucket::Opaque) {
                    $this->renderPatch($change);
                }
            }

            $hidden = count($changes) - count($shown);

            if ($hidden > 0) {
                $this->output->writeln(sprintf('    <fg=gray>… and %d more</>', $hidden));
            }

            $this->output->newLine();
        }

        $this->renderVerdict($delta, $verbose);
    }

    private function renderChange(Delta $delta, Change $change): void
    {
        $color = match ($change->status->value) {
            'added' => 'green',
            'removed' => 'red',
            default => 'yellow',
        };

        $annotation = $change->annotation($delta->manifestChange);

        $this->output->writeln(sprintf(
            '    <fg=%s>%s</> %s%s',
            $color,
            $change->status->symbol(),
            $change->path,
            $annotation === null ? '' : sprintf('  <fg=gray>%s</>', $annotation),
        ));

        if ($change->bucket === Bucket::InstallManifest && $delta->manifestChange instanceof ManifestChange) {
            foreach ($delta->manifestChange->changedKeys() as $key) {
                $this->output->writeln(sprintf('        <fg=gray>%s:</> %s', $key, $delta->manifestChange->render($key)));
            }
        }
    }

    private function renderPatch(Change $change): void
    {
        $diff = UnifiedDiff::between(
            $change->oldFile === null ? null : $this->read($change->oldFile),
            $change->newFile === null ? null : $this->read($change->newFile),
            'a/'.$change->path,
            'b/'.$change->path,
        );

        if ($diff === '') {
            return;
        }

        foreach (array_slice(explode("\n", mb_rtrim($diff, "\n")), 2) as $line) {
            $this->output->writeln('      '.match (true) {
                str_starts_with($line, '+') => sprintf('<fg=green>%s</>', $this->escape($line)),
                str_starts_with($line, '-') => sprintf('<fg=red>%s</>', $this->escape($line)),
                str_starts_with($line, '@@') => sprintf('<fg=cyan>%s</>', $this->escape($line)),
                default => sprintf('<fg=gray>%s</>', $this->escape($line)),
            });
        }

        $this->output->newLine();
    }

    private function renderVerdict(Delta $delta, bool $verbose): void
    {
        $blockers = $delta->reviewBlockers();

        if ($blockers !== []) {
            foreach ($blockers as $blocker) {
                $this->components->warn($blocker);
            }

            return;
        }

        if ($delta->isInertOnly()) {
            $this->components->info('Nothing outside tests, docs and CI changed.');

            return;
        }

        if (! $verbose) {
            $this->components->info(sprintf(
                'Read the source of these %d change(s) with -v.',
                count($delta->changes()),
            ));
        }
    }

    private function read(string $file): ?string
    {
        $contents = @file_get_contents($file);

        return $contents === false ? null : $contents;
    }

    private function escape(string $line): string
    {
        return str_replace(['<', '>'], ['\\<', '\\>'], $line);
    }
}
