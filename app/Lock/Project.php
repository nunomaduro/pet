<?php

declare(strict_types=1);

namespace App\Lock;

use App\Exceptions\FileNotFound;
use App\Support\Json;
use App\Support\Path;

final readonly class Project
{
    private function __construct(
        public string $rootPath,
    ) {}

    public static function at(string $path): self
    {
        $resolved = realpath($path);

        return new self(Path::normalize($resolved === false ? $path : $resolved));
    }

    public static function locate(string $from): self
    {
        $resolved = realpath($from);
        $directory = Path::normalize($resolved === false ? $from : $resolved);

        while (true) {
            if (is_file($directory.'/composer.json')) {
                return new self($directory);
            }

            $parent = dirname($directory);

            if ($parent === $directory) {
                throw FileNotFound::at($from.'/composer.json', 'the project manifest');
            }

            $directory = $parent;
        }
    }

    public function composerJsonPath(): string
    {
        return $this->rootPath.'/composer.json';
    }

    public function lockPath(): string
    {
        return $this->rootPath.'/composer.lock';
    }

    public function vendorPath(): string
    {
        $vendorDir = 'vendor';

        if (is_file($this->composerJsonPath())) {
            $config = Json::array(Json::readFile($this->composerJsonPath(), 'the project manifest'), 'config');
            $configured = is_string($config['vendor-dir'] ?? null) ? $config['vendor-dir'] : null;

            if ($configured !== null && $configured !== '') {
                $vendorDir = str_replace('$HOME', (string) getenv('HOME'), $configured);
            }
        }

        return str_starts_with($vendorDir, '/')
            ? Path::normalize($vendorDir)
            : Path::normalize(Path::join($this->rootPath, $vendorDir));
    }

    public function installedJsonPath(): string
    {
        return $this->vendorPath().'/composer/installed.json';
    }

    public function portoFilePath(): string
    {
        return $this->rootPath.'/porto.json';
    }
}
