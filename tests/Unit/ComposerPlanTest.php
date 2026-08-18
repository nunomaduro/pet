<?php

declare(strict_types=1);

use App\Support\ComposerChange;
use App\Support\ComposerPlan;

it('reads the operations of a real dry run', function (): void {
    $plan = ComposerPlan::parse((string) file_get_contents(__DIR__.'/../Fixtures/composer-update-dry-run.txt'));

    $operations = [];

    foreach ($plan->operations as $operation) {
        $operations[$operation->package] = [$operation->change, $operation->from, $operation->to];
    }

    expect($operations)->toBe([
        'psr/simple-cache' => [ComposerChange::Remove, '3.0.0', null],
        'carbonphp/carbon-doctrine-types' => [ComposerChange::Upgrade, '3.1.0', '3.2.0'],
        'psr/log' => [ComposerChange::Downgrade, '3.0.0', '2.0.0'],
        'psr/container' => [ComposerChange::Install, null, '2.0.2'],
    ]);
});

it('reports no operation when composer would change nothing', function (): void {
    $plan = ComposerPlan::parse(<<<'OUTPUT'
        Loading composer repositories with package information
        Updating dependencies
        Nothing to modify in lock file
        Installing dependencies from lock file (including require-dev)
        Nothing to install, update or remove
        OUTPUT);

    expect($plan->isEmpty())->toBeTrue()
        ->and($plan->operations)->toBeEmpty();
});

it('ignores the lines that name no operation', function (): void {
    $plan = ComposerPlan::parse(<<<'OUTPUT'
          - Locking acme/widget (2.0.0)
          - Downloading acme/widget (2.0.0)
          - Installing acme/widget (2.0.0): Extracting archive
        OUTPUT);

    expect($plan->operations)->toHaveCount(1)
        ->and($plan->operations[0]->change)->toBe(ComposerChange::Install)
        ->and($plan->operations[0]->to)->toBe('2.0.0');
});

it('drops the reference of a branch version', function (): void {
    $plan = ComposerPlan::parse('  - Upgrading acme/widget (dev-main 1234567 => dev-main 89abcde)');

    expect($plan->operations[0]->from)->toBe('dev-main')
        ->and($plan->operations[0]->to)->toBe('dev-main');
});
