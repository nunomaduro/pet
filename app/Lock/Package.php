<?php

declare(strict_types=1);

namespace App\Lock;

use App\Identity\InstallSource;
use App\Support\Json;
use App\Support\Path;

final readonly class Package
{
    /**
     * @param  array<string, string>  $replace
     * @param  array<string, string>  $provide
     * @param  array<string, mixed>  $autoload
     * @param  array<int, string>  $bin
     */
    public function __construct(
        public string $name,
        public string $version,
        public string $type,
        public bool $dev,
        public ?string $distUrl,
        public ?string $distReference,
        public ?string $distShasum,
        public ?string $sourceUrl,
        public ?string $sourceReference,
        public array $replace,
        public array $provide,
        public array $autoload,
        public array $bin,
        public ?InstallSource $installSource = null,
        public ?string $installPath = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  a package entry from composer.lock
     */
    public static function fromLockEntry(array $data, bool $dev): self
    {
        $dist = Json::array($data, 'dist');
        $source = Json::array($data, 'source');

        return new self(
            name: Json::string($data, 'name') ?? '',
            version: Json::string($data, 'version') ?? '',
            type: Json::string($data, 'type') ?? 'library',
            dev: $dev,
            distUrl: Json::string($dist, 'url'),
            distReference: Json::string($dist, 'reference'),
            distShasum: self::nonEmpty(Json::string($dist, 'shasum')),
            sourceUrl: Json::string($source, 'url'),
            sourceReference: Json::string($source, 'reference'),
            replace: self::constraints($data, 'replace'),
            provide: self::constraints($data, 'provide'),
            autoload: Json::array($data, 'autoload'),
            bin: self::strings($data, 'bin'),
        );
    }

    /**
     * @param  array<string, mixed>  $data  a package entry from installed.json
     * @param  string  $vendorComposerPath  the directory `install-path` is relative to
     */
    public static function fromInstalledEntry(array $data, bool $dev, string $vendorComposerPath): self
    {
        $base = self::fromLockEntry($data, $dev);

        $installPath = Json::string($data, 'install-path');

        return new self(
            name: $base->name,
            version: $base->version,
            type: $base->type,
            dev: $base->dev,
            distUrl: $base->distUrl,
            distReference: $base->distReference,
            distShasum: $base->distShasum,
            sourceUrl: $base->sourceUrl,
            sourceReference: $base->sourceReference,
            replace: $base->replace,
            provide: $base->provide,
            autoload: $base->autoload,
            bin: $base->bin,
            installSource: InstallSource::fromComposer(Json::string($data, 'installation-source')),
            installPath: $installPath === null
                ? null
                : Path::normalize(Path::join($vendorComposerPath, $installPath)),
        );
    }

    /**
     * @return array<int, string>
     */
    public function aliases(): array
    {
        return array_values(array_unique([
            ...array_keys($this->replace),
            ...array_keys($this->provide),
        ]));
    }

    /**
     * @return array<int, string>
     */
    public function runtimeRoots(): array
    {
        $roots = [];

        foreach ($this->autoloadPaths() as $path) {
            $roots[] = mb_trim($path, '/.');
        }

        foreach ($this->bin as $path) {
            $roots[] = mb_trim($path, '/');
        }

        return array_values(array_unique(array_filter($roots, static fn (string $root): bool => $root !== '')));
    }

    public function autoloadsPackageRoot(): bool
    {
        foreach ($this->autoloadPaths() as $path) {
            if (mb_trim($path, '/.') === '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private static function constraints(array $data, string $key): array
    {
        $result = [];

        foreach (Json::array($data, $key) as $name => $constraint) {
            if (is_string($name) && is_string($constraint)) {
                $result[$name] = $constraint;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private static function strings(array $data, string $key): array
    {
        return array_values(array_filter(Json::array($data, $key), is_string(...)));
    }

    private static function nonEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }

    /**
     * @return array<int, string>
     */
    private function autoloadPaths(): array
    {
        $paths = [];

        foreach (['psr-4', 'psr-0'] as $standard) {
            foreach (Json::array($this->autoload, $standard) as $entry) {
                foreach ((array) $entry as $path) {
                    if (is_string($path)) {
                        $paths[] = $path;
                    }
                }
            }
        }

        foreach (['files', 'classmap'] as $standard) {
            foreach (Json::array($this->autoload, $standard) as $path) {
                if (is_string($path)) {
                    $paths[] = $path;
                }
            }
        }

        return $paths;
    }
}
