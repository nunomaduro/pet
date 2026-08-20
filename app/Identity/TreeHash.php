<?php

declare(strict_types=1);

namespace App\Identity;

use App\Exceptions\Failure;
use Stringable;

final readonly class TreeHash implements Stringable
{
    public const string ALGORITHM = 'tree-v1';

    public const int LENGTH = 32;

    private function __construct(
        public string $algorithm,
        public string $digest,
    ) {}

    public function __toString(): string
    {
        return $this->algorithm.':'.$this->digest;
    }

    public static function fromManifest(string $manifest): self
    {
        return new self(
            self::ALGORITHM,
            mb_substr(hash('sha256', $manifest), 0, self::LENGTH),
        );
    }

    public static function parse(string $value): self
    {
        $parts = explode(':', $value, 2);

        if (count($parts) !== 2) {
            throw new Failure(sprintf('Malformed tree hash [%s]: expected "<algorithm>:<digest>".', $value));
        }

        [$algorithm, $digest] = $parts;

        if ($algorithm !== self::ALGORITHM) {
            throw new Failure(sprintf('Unknown tree hash algorithm [%s]; this build of porto understands [%s].', $algorithm, self::ALGORITHM));
        }

        if (preg_match('/^[0-9a-f]{'.self::LENGTH.'}$/', $digest) !== 1) {
            throw new Failure(sprintf('Malformed tree hash digest [%s]: expected %d lowercase hex characters.', $digest, self::LENGTH));
        }

        return new self($algorithm, $digest);
    }

    public function equals(self $other): bool
    {
        return $this->algorithm === $other->algorithm
            && hash_equals($this->digest, $other->digest);
    }

    public function short(): string
    {
        return mb_substr($this->digest, 0, 12);
    }
}
