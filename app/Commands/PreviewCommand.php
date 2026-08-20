<?php

declare(strict_types=1);

namespace App\Commands;

use App\Delta\Delta;
use App\Delta\DeltaResolver;
use App\Exceptions\ComposerFailed;
use App\Exceptions\PortoException;
use App\Ledger\Ledger;
use App\Lock\InstalledRepository;
use App\Lock\Project;
use App\Support\ComposerOperation;
use App\Support\ComposerPlan;
use App\Support\ComposerPlanner;
use App\Support\DeltaRenderer;
use App\Support\Invitation;
use App\Support\Json;
use App\Support\PlannedReview;
use Symfony\Component\Console\Formatter\OutputFormatter;

final class PreviewCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'preview
        {--path= : The project directory to preview (defaults to the current one)}
        {--no-cache : Re-download archives instead of reusing the cache}
        {--json : Emit machine-readable output}';

    /**
     * @var string
     */
    protected $description = 'Show what the next composer update changes, before vendor/ is touched';

    public function handle(): int
    {
        try {
            $project = Project::locate($this->stringOption('path') ?? (string) getcwd());
            $plan = ComposerPlanner::default()->plan($project->rootPath);
            $reviews = $this->reviews($project, $plan);
        } catch (ComposerFailed $composerFailed) {
            $this->components->error($composerFailed->getMessage());

            foreach ($composerFailed->output as $line) {
                $this->line(sprintf('  <fg=gray>%s</>', OutputFormatter::escape($line)));
            }

            $this->newLine();

            return self::FAILURE;
        } catch (PortoException $portoException) {
            $this->components->error($portoException->getMessage());

            return self::FAILURE;
        }

        return $this->option('json') === true
            ? $this->renderJson($reviews)
            : $this->render($reviews);
    }

    /**
     * @return array<int, PlannedReview>
     */
    private function reviews(Project $project, ComposerPlan $plan): array
    {
        $ledger = Ledger::forProject($project);
        $resolver = DeltaResolver::forProject($project);
        $installed = is_file($project->installedJsonPath())
            ? InstalledRepository::fromProject($project)
            : null;

        $reviews = [];

        foreach ($plan->operations as $operation) {
            $trusted = $ledger->grantFor($operation->package)?->version;

            $reviews[] = new PlannedReview(
                operation: $operation,
                trusted: $trusted,
                delta: $operation->change->comparesTrees()
                    ? $resolver->resolve(
                        package: $operation->package,
                        from: $trusted ?? $this->installedVersion($installed, $operation),
                        to: $operation->to,
                        useCache: $this->option('no-cache') !== true,
                    )
                    : null,
            );
        }

        usort($reviews, static fn (PlannedReview $a, PlannedReview $b): int => [
            $a->weight(),
            $b->files() ?? 0,
            $a->operation->package,
        ] <=> [
            $b->weight(),
            $a->files() ?? 0,
            $b->operation->package,
        ]);

        return $reviews;
    }

    private function installedVersion(?InstalledRepository $installed, ComposerOperation $operation): ?string
    {
        if ($installed instanceof InstalledRepository && $installed->has($operation->package)) {
            return $installed->get($operation->package)->version;
        }

        return $operation->from;
    }

    /**
     * @param  array<int, PlannedReview>  $reviews
     */
    private function render(array $reviews): int
    {
        $this->newLine();

        if ($reviews === []) {
            $this->components->info('The next `composer update` changes nothing in vendor/.');
            $this->newLine();

            return self::SUCCESS;
        }

        $renderer = new DeltaRenderer($this->output);

        $this->line(sprintf('  <options=bold>to review</> <fg=gray>(%d, worst first)</>', count($reviews)));
        $this->newLine();

        foreach ($reviews as $review) {
            $this->components->twoColumnDetail(
                sprintf(
                    '<fg=yellow>%s</> <fg=gray>%s</>',
                    $review->operation->package,
                    $review->versions(),
                ),
                sprintf('<fg=gray>%s</>', $review->cost()),
            );

            $this->line(sprintf('      <fg=gray>%s</>', $review->reason()));

            if ($review->delta instanceof Delta) {
                $this->newLine();
                $renderer->buckets($review->delta);
            }
        }

        $this->newLine();
        $this->components->info($this->output->isVerbose()
            ? sprintf('%d package(s) change. Run `composer update`, then record them with `porto trust`.', count($reviews))
            : sprintf(
                '%d package(s) change. Read every change with `%s`, then run `composer update`.',
                count($reviews),
                Invitation::verbose('porto preview -v'),
            ));

        return self::SUCCESS;
    }

    /**
     * @param  array<int, PlannedReview>  $reviews
     */
    private function renderJson(array $reviews): int
    {
        $renderer = new DeltaRenderer($this->output);

        $this->output->write(Json::encode([
            'operations' => count($reviews),
            'packages' => array_map(
                static fn (PlannedReview $review): array => $review->toArray($renderer),
                $reviews,
            ),
        ]));

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
