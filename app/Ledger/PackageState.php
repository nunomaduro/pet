<?php

declare(strict_types=1);

namespace App\Ledger;

enum PackageState: string
{
    case Installed = 'installed';

    case Pending = 'pending';
}
