<?php

declare(strict_types=1);

namespace App\Support;

final readonly class ComposerPlan
{
    /**
     * @param  array<int, ComposerOperation>  $operations
     */
    private function __construct(
        public array $operations,
    ) {}

    public static function parse(string $output): self
    {
        $lines = preg_split('/\R/', $output);
        $operations = [];

        foreach ($lines === false ? [] : $lines as $line) {
            $operation = ComposerOperation::parse($line);

            if ($operation instanceof ComposerOperation) {
                $operations[$operation->package] = $operation;
            }
        }

        return new self(array_values($operations));
    }

    public function isEmpty(): bool
    {
        return $this->operations === [];
    }
}
