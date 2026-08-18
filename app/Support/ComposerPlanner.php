<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ComposerFailed;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final readonly class ComposerPlanner
{
    private const int TIMEOUT = 300;

    public function __construct(
        private string $binary,
    ) {}

    public static function default(): self
    {
        $binary = getenv('PET_COMPOSER_BINARY');

        return new self(is_string($binary) && $binary !== '' ? $binary : 'composer');
    }

    public function plan(string $rootPath): ComposerPlan
    {
        $process = new Process(
            [$this->executable(), 'update', '--dry-run', '--no-interaction', '--no-ansi', '--no-scripts'],
            $rootPath,
        );

        $process->setTimeout(self::TIMEOUT);
        $process->run();

        $output = $process->getOutput()."\n".$process->getErrorOutput();

        if (! $process->isSuccessful()) {
            throw ComposerFailed::planFailed($rootPath, $process->getExitCode(), $output);
        }

        return ComposerPlan::parse($output);
    }

    private function executable(): string
    {
        if (is_file($this->binary) && is_executable($this->binary)) {
            return $this->binary;
        }

        $executable = (new ExecutableFinder)->find($this->binary);

        if ($executable === null) {
            throw ComposerFailed::missing($this->binary);
        }

        return $executable;
    }
}
