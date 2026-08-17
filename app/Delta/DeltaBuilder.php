<?php

declare(strict_types=1);

namespace App\Delta;

use App\Identity\InstallSource;
use App\Identity\Manifest;
use App\Lock\Package;
use App\Support\Json;

final class DeltaBuilder
{
    public function build(
        string $package,
        string $fromVersion,
        string $fromDirectory,
        Package $fromMetadata,
        string $toVersion,
        string $toDirectory,
        Package $toMetadata,
        InstallSource $source,
    ): Delta {
        $before = Manifest::ofDirectory($fromDirectory);
        $after = Manifest::ofDirectory($toDirectory);

        $classifier = Classifier::forPackages($fromMetadata, $toMetadata);

        $oldEntries = $before->entries();
        $newEntries = $after->entries();

        $changes = [];

        foreach ($newEntries as $path => $hash) {
            $oldHash = $oldEntries[$path] ?? null;

            if ($oldHash === $hash) {
                continue;
            }

            $newFile = $toDirectory.'/'.$path;
            $oldFile = $oldHash === null ? null : $fromDirectory.'/'.$path;

            $changes[] = new Change(
                path: $path,
                status: $oldHash === null ? ChangeStatus::Added : ChangeStatus::Modified,
                bucket: $classifier->classify($path, $newFile),
                oldHash: $oldHash,
                newHash: $hash,
                oldFile: $oldFile,
                newFile: $newFile,
            );
        }

        foreach ($oldEntries as $path => $hash) {
            if (isset($newEntries[$path])) {
                continue;
            }

            $oldFile = $fromDirectory.'/'.$path;

            $changes[] = new Change(
                path: $path,
                status: ChangeStatus::Removed,
                bucket: $classifier->classify($path, $oldFile),
                oldHash: $hash,
                newHash: null,
                oldFile: $oldFile,
                newFile: null,
            );
        }

        return new Delta(
            package: $package,
            from: $fromVersion,
            to: $toVersion,
            fromHash: $before->hash(),
            toHash: $after->hash(),
            source: $source,
            changes: $changes,
            manifestChange: $this->manifestChange($fromDirectory, $toDirectory),
        );
    }

    private function manifestChange(string $fromDirectory, string $toDirectory): ?ManifestChange
    {
        $oldPath = $fromDirectory.'/composer.json';
        $newPath = $toDirectory.'/composer.json';

        if (! is_file($oldPath) || ! is_file($newPath)) {
            return null;
        }

        $change = ManifestChange::between(
            Json::readFile($oldPath, 'the previous package manifest'),
            Json::readFile($newPath, 'the package manifest'),
        );

        return $change->isEmpty() ? null : $change;
    }
}
