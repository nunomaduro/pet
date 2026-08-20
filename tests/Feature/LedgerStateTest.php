<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Tests\Fixture;

it('tells the user what to do with a ledger of an older schema', function (): void {
    $fixture = Fixture::open('legacy-ledger');

    try {
        $status = Artisan::call('audit', ['--path' => $fixture->rootPath]);
        $output = Artisan::output();
    } finally {
        $fixture->remove();
    }

    expect($status)->toBe(1)
        ->and($output)
        ->toContain('declares schema 2')
        ->toContain('Delete the file and run `porto trust` again.');
});

it('names the entry that holds no hash', function (): void {
    $fixture = Fixture::open('broken-ledger');

    try {
        $status = Artisan::call('audit', ['--path' => $fixture->rootPath]);
        $output = Artisan::output();
    } finally {
        $fixture->remove();
    }

    expect($status)->toBe(1)
        ->and($output)->toContain('The entry for [acme/widget] needs a "version" and a "hash".');
});

it('ignores an entry of a package that the project does not install', function (): void {
    $fixture = Fixture::open('orphan-ledger');

    try {
        $status = Artisan::call('audit', ['--path' => $fixture->rootPath]);
        $output = Artisan::output();
    } finally {
        $fixture->remove();
    }

    expect($status)->toBe(0)
        ->and($output)->toContain('All 1 packages are covered.');
});

it('keeps the notes of an entry that the user records again', function (): void {
    $fixture = Fixture::open('partly-audited');

    try {
        $status = Artisan::call('trust', ['packages' => ['acme/widget'], '--path' => $fixture->rootPath]);
        $ledger = $fixture->read('porto.json');
    } finally {
        $fixture->remove();
    }

    expect($status)->toBe(0)
        ->and($ledger)
        ->toContain('"notes": "Reviewed with the team."')
        ->toContain('"version": "2.0.0"');
});

it('replaces the notes of an entry when the user gives --notes', function (): void {
    $fixture = Fixture::open('partly-audited');

    try {
        $status = Artisan::call('trust', [
            'packages' => ['acme/widget'],
            '--notes' => 'Read the phar too.',
            '--path' => $fixture->rootPath,
        ]);
        $ledger = $fixture->read('porto.json');
    } finally {
        $fixture->remove();
    }

    expect($status)->toBe(0)
        ->and($ledger)->toContain('"notes": "Read the phar too."')
        ->and(str_contains($ledger, 'Reviewed with the team.'))->toBeFalse();
});

it('writes the dev package of a baseline in require-dev', function (): void {
    $fixture = Fixture::open('no-ledger');

    try {
        $status = Artisan::call('trust', ['--path' => $fixture->rootPath]);

        /** @var array{require: array<string, mixed>, require-dev: array<string, mixed>} $ledger */
        $ledger = json_decode($fixture->read('porto.json'), true);
    } finally {
        $fixture->remove();
    }

    expect($status)->toBe(0)
        ->and($ledger['require'])->toHaveKey('acme/widget')
        ->and($ledger['require-dev'])->toHaveKey('acme/lint');
});
