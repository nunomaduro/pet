<?php

declare(strict_types=1);

namespace App\Exceptions;

final class PackageNotInstalled extends PortoException
{
    public static function named(string $name): self
    {
        return new self(sprintf('The package [%s] is not present in the installed package list.', $name));
    }
}
