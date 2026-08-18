<?php

declare(strict_types=1);

namespace Acme\Media;

final class Logo
{
    public function path(): string
    {
        return __DIR__.'/logo.png';
    }
}
