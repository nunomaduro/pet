<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\FailureException;

final readonly class Cache
{
    private function __construct(
        public string $rootPath,
    ) {}

    public static function default(): self
    {
        $override = getenv('PORTO_CACHE_DIR');

        if (is_string($override) && $override !== '') {
            return new self(Path::normalize($override));
        }

        $xdg = getenv('XDG_CACHE_HOME');
        $home = getenv('HOME');

        $base = match (true) {
            is_string($xdg) && $xdg !== '' => $xdg,
            is_string($home) && $home !== '' => Path::join($home, '.cache'),
            default => sys_get_temp_dir(),
        };

        return new self(Path::normalize(Path::join($base, 'porto')));
    }

    public function path(string ...$segments): string
    {
        return Path::normalize(Path::join($this->rootPath, ...$segments));
    }

    public function directory(string ...$segments): string
    {
        $path = $this->path(...$segments);

        if (! is_dir($path) && ! @mkdir($path, 0o777, true) && ! is_dir($path)) {
            throw new FailureException(sprintf('Could not create the cache directory [%s].', $path));
        }

        return $path;
    }

    public function fresh(string $path, int $seconds): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $modified = @filemtime($path);

        if ($modified === false || time() - $modified > $seconds) {
            return null;
        }

        $contents = @file_get_contents($path);

        return $contents === false || $contents === '' ? null : $contents;
    }

    public function put(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0o777, true) && ! is_dir($directory)) {
            throw new FailureException(sprintf('Could not create the cache directory [%s].', $directory));
        }

        if (@file_put_contents($path, $contents) === false) {
            throw new FailureException(sprintf('Could not write to the cache file [%s].', $path));
        }
    }
}
