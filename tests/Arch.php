<?php

declare(strict_types=1);

$core = [
    'App\Enums',
    'App\Exceptions',
    'App\ValueObjects',
];

arch('the data holds no dependency')
    /** @phpstan-ignore method.notFound */
    ->expect($core)
    ->toOnlyUse([...$core, 'App\Actions', 'App\Support']);

arch('exceptions carry the suffix')
    /** @phpstan-ignore method.notFound */
    ->expect('App\Exceptions')
    ->toHaveSuffix('Exception');
