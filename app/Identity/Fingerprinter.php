<?php

declare(strict_types=1);

namespace App\Identity;

use App\Exceptions\Failure;
use App\Lock\InstalledRepository;
use App\Lock\Package;
use App\Lock\Project;

final readonly class Fingerprinter
{
    public function __construct(
        private InstalledRepository $installed,
    ) {}

    public static function forProject(Project $project): self
    {
        return new self(InstalledRepository::fromProject($project));
    }

    public function of(string $package): Fingerprint
    {
        return $this->ofPackage($this->installed->get($package));
    }

    public function ofPackage(Package $package): Fingerprint
    {
        if ($package->installPath === null) {
            throw new Failure(sprintf('The package [%s] has no recorded install path.', $package->name));
        }

        $manifest = Manifest::ofDirectory($package->installPath);

        return new Fingerprint(
            package: $package->name,
            version: $package->version,
            source: $package->installSource ?? InstallSource::Dist,
            hash: $manifest->hash(),
            path: $package->installPath,
            files: $manifest->count(),
            bytes: $manifest->bytes(),
        );
    }

    public function repository(): InstalledRepository
    {
        return $this->installed;
    }
}
