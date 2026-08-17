<?php

declare(strict_types=1);

namespace App\Exceptions;

final class InvalidJson extends PetException
{
    public static function at(string $path, string $reason): self
    {
        return new self(sprintf('The file [%s] does not contain valid JSON: %s', $path, $reason));
    }

    public static function shape(string $path, string $expectation): self
    {
        return new self(sprintf('Unexpected structure in [%s]: %s', $path, $expectation));
    }
}
