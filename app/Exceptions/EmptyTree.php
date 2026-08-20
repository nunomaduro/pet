<?php

declare(strict_types=1);

namespace App\Exceptions;

final class EmptyTree extends PortoException
{
    public static function at(string $directory): self
    {
        return new self(sprintf('The directory [%s] contains no files; refusing to hash an empty tree.', $directory));
    }

    public static function missing(string $directory): self
    {
        return new self(sprintf('The directory [%s] does not exist.', $directory));
    }
}
