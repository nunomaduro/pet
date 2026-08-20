<?php

declare(strict_types=1);

$core = [
    'App\Delta',
    'App\Enums',
    'App\Exceptions',
    'App\Identity',
    'App\Ledger',
    'App\Lock',
    'App\Registry',
];

arch('core is dependency-free')
    /** @phpstan-ignore method.notFound */
    ->expect($core)
    ->toOnlyUse([...$core, 'App\Support']);

arch('exceptions carry the suffix')
    /** @phpstan-ignore method.notFound */
    ->expect('App\Exceptions')
    ->toHaveSuffix('Exception');
