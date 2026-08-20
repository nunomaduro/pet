<?php

declare(strict_types=1);

namespace App\Enums;

enum PackageStatus: string
{
    case Installed = 'installed';

    case Pending = 'pending';
}
