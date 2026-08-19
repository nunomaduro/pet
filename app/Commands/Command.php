<?php

declare(strict_types=1);

namespace App\Commands;

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
}
