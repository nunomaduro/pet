<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Enums\BucketType;
use App\Enums\ChangeStatus;

final readonly class Change
{
    public function __construct(
        public string       $path,
        public ChangeStatus $status,
        public BucketType   $bucket,
        public ?string      $oldHash,
        public ?string      $newHash,
        public ?string      $oldFile,
        public ?string      $newFile,
    ) {}

    public function annotation(?ManifestChange $manifestChange): ?string
    {
        if ($this->bucket !== BucketType::InstallManifest || ! $manifestChange instanceof ManifestChange) {
            return null;
        }

        $keys = $manifestChange->changedKeys();

        return $keys === [] ? null : implode(', ', $keys);
    }
}
