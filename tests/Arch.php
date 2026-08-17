<?php

declare(strict_types=1);

$core = [
    'App\Delta',
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
