<?php

declare(strict_types=1);

namespace App\Ledger;

enum AuditStatus: string
{
    case Covered = 'covered';

    case Ungranted = 'ungranted';

    case Changed = 'changed';

    case Unknown = 'unknown';

    public function fails(): bool
    {
        return $this !== self::Covered;
    }

    public function label(): string
    {
        return $this->value;
    }
}
