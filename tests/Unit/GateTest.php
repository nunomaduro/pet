<?php

declare(strict_types=1);

use App\Composer\Gate;

function gateProject(bool $ledger, bool $binary): Gate
{
    $root = sys_get_temp_dir().'/porto-'.bin2hex(random_bytes(6));
    $binDir = $root.'/vendor/bin';

    mkdir($binDir, 0o777, true);

    if ($ledger) {
        file_put_contents($root.'/porto.json', '{"schema":3}');
    }

    if ($binary) {
        file_put_contents($binDir.'/porto', "#!/usr/bin/env php\n");
    }

    register_shutdown_function(static function () use ($root): void {
        @unlink($root.'/porto.json');
        @unlink($root.'/vendor/bin/porto');
        @rmdir($root.'/vendor/bin');
        @rmdir($root.'/vendor');
        @rmdir($root);
    });

    return new Gate($root, $binDir);
}

it('runs the audit when the project holds a ledger and the binary', function (): void {
    $gate = gateProject(ledger: true, binary: true);

    expect($gate->command(decorated: false))->toBe([PHP_BINARY, $gate->rootPath.'/vendor/bin/porto', 'audit'])
        ->and($gate->command(decorated: true))->toBe([PHP_BINARY, $gate->rootPath.'/vendor/bin/porto', 'audit', '--ansi'])
        ->and($gate->baselineNotice())->toBeNull();
});

it('passes the verbosity of composer to the audit', function (): void {
    $gate = gateProject(ledger: true, binary: true);

    expect($gate->command(decorated: false, verbose: true))
        ->toBe([PHP_BINARY, $gate->rootPath.'/vendor/bin/porto', 'audit', '-v'])
        ->and($gate->command(decorated: true, verbose: true))
        ->toBe([PHP_BINARY, $gate->rootPath.'/vendor/bin/porto', 'audit', '--ansi', '-v']);
});

it('tells the audit that composer runs it', function (): void {
    expect(gateProject(ledger: true, binary: true)->environment())
        ->toBe([Gate::ENVIRONMENT => '1']);
});

it('asks for a baseline rather than fail a project that holds no ledger', function (): void {
    $gate = gateProject(ledger: false, binary: true);

    expect($gate->command(decorated: false))->toBeNull()
        ->and($gate->baselineNotice())->toContain('porto trust');
});

it('does nothing when the binary is gone', function (): void {
    $gate = gateProject(ledger: true, binary: false);

    expect($gate->binary())->toBeNull()
        ->and($gate->command(decorated: false))->toBeNull()
        ->and($gate->baselineNotice())->toBeNull();
});

it('reads the binary of the repository of porto itself', function (): void {
    $root = dirname(__DIR__, 2);

    expect((new Gate($root, $root.'/vendor/bin'))->binary())->toBe($root.'/porto');
});
