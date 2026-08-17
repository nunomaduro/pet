<?php

declare(strict_types=1);

namespace App\Delta;

use App\Lock\Package;

final readonly class Classifier
{
    private const array OPAQUE_EXTENSIONS = [
        'phar', 'so', 'dll', 'exe', 'dylib', 'a', 'o', 'obj', 'lib',
        'wasm', 'node', 'jar', 'class', 'pyc', 'bin', 'msi', 'deb', 'rpm',
    ];

    private const array MEDIA_EXTENSIONS = [
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'bmp', 'svg', 'avif',
        'woff', 'woff2', 'ttf', 'otf', 'eot',
        'mp3', 'mp4', 'wav', 'ogg', 'webm', 'pdf',
    ];

    /**
     * @param  array<int, string>  $runtimeRoots
     * @param  bool  $wholePackage  an autoload prefix maps to the package root
     */
    public function __construct(
        private array $runtimeRoots,
        private bool $wholePackage = false,
    ) {}

    public static function forPackages(Package ...$packages): self
    {
        $roots = [];
        $wholePackage = false;

        foreach ($packages as $package) {
            foreach ($package->runtimeRoots() as $root) {
                $roots[] = $root;
            }

            $wholePackage = $wholePackage || $package->autoloadsPackageRoot();
        }

        return new self(array_values(array_unique($roots)), $wholePackage);
    }

    /**
     * @param  string  $path  relative to the package root
     * @param  string|null  $file  absolute path to the file, when it exists on disk
     */
    public function classify(string $path, ?string $file = null): Bucket
    {
        if ($this->isOpaque($path, $file)) {
            return Bucket::Opaque;
        }

        if ($path === 'composer.json') {
            return Bucket::InstallManifest;
        }

        if ($this->isRuntime($path)) {
            return Bucket::RuntimeSource;
        }

        return Bucket::Inert;
    }

    private function isOpaque(string $path, ?string $file): bool
    {
        $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, self::OPAQUE_EXTENSIONS, true)) {
            return true;
        }

        if (in_array($extension, self::MEDIA_EXTENSIONS, true)) {
            return false;
        }

        if ($file === null || ! is_file($file)) {
            return false;
        }

        return $this->looksMinified($extension, $file) || $this->looksBinary($file);
    }

    private function looksMinified(string $extension, string $file): bool
    {
        if (! in_array($extension, ['js', 'css', 'mjs', 'cjs'], true)) {
            return false;
        }

        $size = (int) @filesize($file);

        if ($size < 20_000) {
            return false;
        }

        $handle = @fopen($file, 'rb');

        if ($handle === false) {
            return false;
        }

        $lines = 0;
        $longest = 0;

        while (($line = fgets($handle)) !== false && $lines < 200) {
            $lines++;
            $longest = max($longest, mb_strlen($line));
        }

        fclose($handle);

        return $longest > 1000 || ($lines < 5 && $size > 20_000);
    }

    private function looksBinary(string $file): bool
    {
        $handle = @fopen($file, 'rb');

        if ($handle === false) {
            return false;
        }

        $head = (string) fread($handle, 8000);
        fclose($handle);

        return $head !== '' && str_contains($head, "\0");
    }

    private function isRuntime(string $path): bool
    {
        if ($this->wholePackage && str_ends_with(mb_strtolower($path), '.php')) {
            return true;
        }

        foreach ($this->runtimeRoots as $root) {
            if ($path === $root || str_starts_with($path, $root.'/')) {
                return true;
            }
        }

        return false;
    }
}
