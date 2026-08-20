<?php

declare(strict_types=1);

namespace App\Delta;

use App\Exceptions\Failure;
use App\Identity\InstallSource;
use App\Lock\InstalledRepository;
use App\Lock\Package;
use App\Lock\Project;
use App\Registry\ArchiveFetcher;
use App\Registry\Packagist;

final readonly class DeltaResolver
{
    public function __construct(
        private Packagist $packagist,
        private ArchiveFetcher $fetcher,
        private DeltaBuilder $builder,
        private ?InstalledRepository $installed = null,
    ) {}

    public static function forProject(?Project $project): self
    {
        $installed = null;

        if ($project instanceof Project && is_file($project->installedJsonPath())) {
            $installed = InstalledRepository::fromProject($project);
        }

        return new self(
            Packagist::default(),
            ArchiveFetcher::default(),
            new DeltaBuilder,
            $installed,
        );
    }

    public function resolve(string $package, ?string $from = null, ?string $to = null, bool $useCache = true): Delta
    {
        $installed = $this->installed instanceof InstalledRepository && $this->installed->has($package)
            ? $this->installed->get($package)
            : null;

        $versions = $this->packagist->versions($package);

        $toVersion = $to ?? $installed->version ?? array_key_first($versions);

        if (! is_string($toVersion)) {
            throw new Failure(sprintf('Could not determine which version of [%s] to compare to.', $package));
        }

        $toMetadata = $this->packagist->version($package, $toVersion);
        $toVersion = $toMetadata->version;

        $fromVersion = $from ?? $this->packagist->previousVersion($package, $toVersion);

        if ($fromVersion === null) {
            throw new Failure(sprintf(
                '[%s@%s] has no earlier release to compare against. Pass an explicit version: `porto audit %s <from>`.',
                $package,
                $toVersion,
                $package,
            ));
        }

        $fromMetadata = $this->packagist->version($package, $fromVersion);
        $fromVersion = $fromMetadata->version;

        if ($fromVersion === $toVersion) {
            throw new Failure(sprintf('[%s] %s and %s are the same version.', $package, $fromVersion, $toVersion));
        }

        $notes = [];
        [$toDirectory, $toIsLocal, $source] = $this->toTree($installed, $toMetadata, $to, $useCache, $notes);

        $delta = $this->builder->build(
            package: $package,
            fromVersion: $fromVersion,
            fromDirectory: $this->fetcher->fetch($fromMetadata, $useCache),
            fromMetadata: $fromMetadata,
            toVersion: $toVersion,
            toDirectory: $toDirectory,
            toMetadata: $toMetadata,
            source: $source,
        );

        return $delta->withResolution($toIsLocal, $notes);
    }

    public function incoming(Package $target, ?Package $installed, bool $useCache = true): ?Delta
    {
        if (! $installed instanceof Package || $installed->installPath === null || ! is_dir($installed->installPath)) {
            return null;
        }

        $delta = $this->builder->build(
            package: $target->name,
            fromVersion: $installed->version,
            fromDirectory: $installed->installPath,
            fromMetadata: $installed,
            toVersion: $target->version,
            toDirectory: $this->fetcher->fetch($target, $useCache),
            toMetadata: $target,
            source: $installed->installSource ?? InstallSource::Dist,
        );

        return $delta->withResolution(false, []);
    }

    /**
     * @param  array<int, string>  $notes
     * @return array{0: string, 1: bool, 2: InstallSource}
     */
    private function toTree(
        ?Package $installed,
        Package $toMetadata,
        ?string $explicitTo,
        bool $useCache,
        array &$notes,
    ): array {
        $usable = $explicitTo === null
            && $installed instanceof Package
            && $installed->version === $toMetadata->version
            && $installed->installPath !== null
            && is_dir($installed->installPath);

        if (! $usable) {
            return [$this->fetcher->fetch($toMetadata, $useCache), false, InstallSource::Dist];
        }

        /** @var Package $installed */
        $source = $installed->installSource ?? InstallSource::Dist;

        if ($source === InstallSource::Source) {
            $notes[] = sprintf(
                '%s is installed from source; comparing dist archives instead. An audit of this delta does not cover your source install.',
                $installed->name,
            );

            return [$this->fetcher->fetch($toMetadata, $useCache), false, InstallSource::Source];
        }

        /** @var string $path */
        $path = $installed->installPath;

        return [$path, true, InstallSource::Dist];
    }
}
