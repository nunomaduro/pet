<?php

declare(strict_types=1);

namespace App\Ledger;

use App\Exceptions\Failure;
use App\Identity\TreeHash;
use App\Support\Json;

final readonly class Grant
{
    public function __construct(
        public string $package,
        public string $version,
        public TreeHash $hash,
        public bool $dev,
        public ?string $notes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $package, bool $dev): self
    {
        $version = Json::string($data, 'version');
        $hash = Json::string($data, 'hash');

        if ($version === null || $hash === null) {
            throw new Failure(sprintf('The entry for [%s] needs a "version" and a "hash".', $package));
        }

        return new self(
            package: $package,
            version: $version,
            hash: TreeHash::parse($hash),
            dev: $dev,
            notes: Json::string($data, 'notes'),
        );
    }

    public function covers(TreeHash $hash): bool
    {
        return $this->hash->equals($hash);
    }

    public function section(): string
    {
        return $this->dev ? 'require-dev' : 'require';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'version' => $this->version,
            'hash' => (string) $this->hash,
        ];

        if ($this->notes !== null) {
            $data['notes'] = $this->notes;
        }

        return $data;
    }
}
