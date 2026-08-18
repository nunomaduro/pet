<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

test('the verbose project audit renders source diffs for changed packages', function (): void {
    $root = sys_get_temp_dir().'/pet-audit-'.bin2hex(random_bytes(6));
    $cache = $root.'/cache';
    $package = $root.'/vendor/acme/library';
    $fromUrl = 'https://example.test/acme/library-1.0.0.zip';

    $write = static function (string $path, string $contents): void {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0o777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Could not create %s.', $directory));
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException(sprintf('Could not write %s.', $path));
        }
    };

    $remove = static function (string $directory) use (&$remove): void {
        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $path = $directory.'/'.$name;

            if (is_dir($path) && ! is_link($path)) {
                $remove($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    };

    try {
        $manifest = [
            'name' => 'acme/library',
            'autoload' => ['psr-4' => ['Acme\\' => 'src/']],
        ];
        $manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR);
        $fromMetadata = [
            ...$manifest,
            'name' => 'acme/library',
            'version' => '1.0.0',
        'source' => ['url' => 'https://github.com/acme/library.git', 'reference' => 'from'],
            'dist' => ['url' => $fromUrl, 'reference' => 'from'],
        ];
        $toMetadata = [
            ...$manifest,
            'name' => 'acme/library',
            'version' => '2.0.0',
            'source' => ['url' => 'https://github.com/acme/library.git', 'reference' => 'to'],
            'dist' => ['url' => 'https://example.test/acme/library-2.0.0.zip', 'reference' => 'to'],
        ];

        $write($root.'/composer.json', "{}\n");
        $write($root.'/composer.lock', json_encode([
            'content-hash' => 'fixture',
            'packages' => [$toMetadata],
            'packages-dev' => [],
        ], JSON_THROW_ON_ERROR)."\n");
        $write($root.'/pet.json', json_encode([
            'schema' => 3,
            'require' => [
                'acme/library' => [
                    'version' => '1.0.0',
                    'hash' => 'tree-v1:00000000000000000000000000000000',
                ],
            ],
            'require-dev' => (object) [],
        ], JSON_THROW_ON_ERROR)."\n");
        $write($package.'/composer.json', $manifestJson);
        $write($package.'/src/Thing.php', "<?php\nreturn 'new';\n");
        $write($root.'/vendor/composer/installed.json', json_encode([
            'packages' => [
                [
                    ...$toMetadata,
                    'install-path' => '../acme/library',
                    'installation-source' => 'dist',
                ],
            ],
            'dev-package-names' => [],
        ], JSON_THROW_ON_ERROR)."\n");
        $write($cache.'/metadata/acme-library.json', json_encode([
            'packages' => ['acme/library' => [$toMetadata, $fromMetadata]],
        ], JSON_THROW_ON_ERROR)."\n");

        $archivePath = sprintf(
            '%s/downloads/acme-library-1.0.0-%s.zip',
            $cache,
            mb_substr(hash('sha256', $fromUrl.'|from'), 0, 16),
        );
        $write($archivePath, '');

        $archive = new ZipArchive;

        if ($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the package archive.');
        }

        $archive->addFromString('acme-library-1.0.0/composer.json', $manifestJson);
        $archive->addFromString('acme-library-1.0.0/src/Thing.php', "<?php\nreturn 'old';\n");
        $archive->close();

        $process = new Process([
            PHP_BINARY,
            dirname(__DIR__, 2).'/pet',
            '--path='.$root,
            'audit',
            '-v',
        ], null, ['PET_CACHE_DIR' => $cache]);
        $process->run();
        $output = $process->getOutput().$process->getErrorOutput();

        expect($process->getExitCode())->toBe(1)
            ->and($output)->toContain('runtime source (1)')
            ->and($output)->toContain("      -return 'old';")
            ->and($output)->toContain("      +return 'new';")
            ->and($output)->toContain('      Diff: https://github.com/acme/library/compare/1.0.0...2.0.0');

        $packageProcess = new Process([
                PHP_BINARY,
                dirname(__DIR__, 2).'/pet',
                '--path='.$root,
                'audit',
                'acme/library',
                '--ansi',
            ], null, ['PET_CACHE_DIR' => $cache]
        );
        $packageProcess->run();
        $packageOutput = $packageProcess->getOutput().$packageProcess->getErrorOutput();
        expect($packageProcess->getExitCode())->toBe(1)
            ->and($packageOutput)->toContain("\033]8;;https://github.com/acme/library/compare/1.0.0...2.0.0\033\\https://github.com/acme/library/compare/1.0.0...2.0.0\033]8;;\033\\");
        } finally {
            $remove($root);
        }
    }
);
