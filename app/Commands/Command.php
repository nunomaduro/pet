<?php

declare(strict_types=1);

namespace App\Commands;

use App\Enums\BucketType;
use App\Exceptions\FailureException;
use App\Support\ControlSafeComponents;
use App\Support\ControlSafeFormatter;
use LaravelZero\Framework\Commands\Command as LaravelZeroCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Command extends LaravelZeroCommand
{
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $formatter = $output->getFormatter();

        if (! $formatter instanceof ControlSafeFormatter) {
            $output->setFormatter(new ControlSafeFormatter($formatter));
        }

        $this->components = new ControlSafeComponents($this->output);
    }

    protected function bucketOption(): ?BucketType
    {
        $value = $this->option('bucket');

        if (! is_string($value) || $value === '') {
            return null;
        }

        $bucket = BucketType::tryFrom($value);

        if ($bucket instanceof BucketType) {
            return $bucket;
        }

        throw new FailureException(sprintf(
            'The --bucket option accepts one of: %s.',
            implode(', ', array_map(static fn (BucketType $bucket): string => $bucket->value, BucketType::cases())),
        ));
    }
}
