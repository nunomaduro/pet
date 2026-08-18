<?php

declare(strict_types=1);

use App\Support\Invitation;

afterEach(function (): void {
    putenv(Invitation::ENVIRONMENT);
});

it('invites the command of pet outside composer', function (): void {
    expect(Invitation::verbose())->toBe('-v')
        ->and(Invitation::verbose('pet audit -v'))->toBe('pet audit -v');
});

it('invites the verbose flag of composer inside composer', function (): void {
    putenv(Invitation::ENVIRONMENT.'=1');

    expect(Invitation::verbose())->toBe('composer update -v')
        ->and(Invitation::verbose('pet audit -v'))->toBe('composer update -v');
});
