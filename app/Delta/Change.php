<?php

declare(strict_types=1);

namespace App\Delta;

final readonly class Change
{
    public function __construct(
        public string $path,
        public ChangeStatus $status,
        public Bucket $bucket,
        public ?string $oldHash,
        public ?string $newHash,
        public ?string $oldFile,
        public ?string $newFile,
    ) {}

    public function annotation(?ManifestChange $manifestChange): ?string
    {
        if ($this->bucket !== Bucket::InstallManifest || ! $manifestChange instanceof ManifestChange) {
            return null;
        }

        $keys = $manifestChange->changedKeys();

        return $keys === [] ? null : implode(', ', $keys);
    }
}
