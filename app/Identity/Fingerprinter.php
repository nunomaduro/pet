<?php

declare(strict_types=1);

namespace App\Identity;

use App\Enums\InstallSourceType;
use App\Exceptions\FailureException;
use App\Lock\InstalledRepository;
use App\Lock\Package;
use App\Lock\Project;
use App\Registry\ArchiveFetcher;

final readonly class Fingerprinter
{
    public function __construct(
        private InstalledRepository $installed,
        private ArchiveFetcher $fetcher,
    ) {}

    public static function forProject(Project $project): self
    {
        return new self(InstalledRepository::fromProject($project), ArchiveFetcher::default());
    }

    public function of(string $package): Fingerprint
    {
        return $this->ofPackage($this->installed->get($package));
    }

    public function ofPackage(Package $package): Fingerprint
    {
        if ($package->installPath === null) {
            throw new FailureException(sprintf('The package [%s] has no recorded install path.', $package->name));
        }

        $manifest = Manifest::ofDirectory($package->installPath);

        return new Fingerprint(
            package: $package->name,
            version: $package->version,
            source: $package->installSource ?? InstallSourceType::Dist,
            hash: $manifest->hash(),
            path: $package->installPath,
            files: $manifest->count(),
            bytes: $manifest->bytes(),
        );
    }

    public function ofIncoming(Package $target, bool $useCache = true): Fingerprint
    {
        $directory = $this->fetcher->fetch($target, $useCache);
        $manifest = Manifest::ofDirectory($directory);

        return new Fingerprint(
            package: $target->name,
            version: $target->version,
            source: InstallSourceType::Dist,
            hash: $manifest->hash(),
            path: $directory,
            files: $manifest->count(),
            bytes: $manifest->bytes(),
        );
    }

    public function repository(): InstalledRepository
    {
        return $this->installed;
    }
}
