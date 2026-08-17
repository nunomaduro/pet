<?php

declare(strict_types=1);

namespace App\Identity;

final readonly class Fingerprint
{
    public function __construct(
        public string $package,
        public string $version,
        public InstallSource $source,
        public TreeHash $hash,
        public string $path,
        public int $files,
        public int $bytes,
    ) {}

    public function key(): string
    {
        return $this->package.'@'.$this->version.' ['.$this->source->value.'] '.$this->hash;
    }
}
