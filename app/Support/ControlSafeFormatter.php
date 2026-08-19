<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyleInterface;
use Symfony\Component\Console\Formatter\WrappableOutputFormatterInterface;

final readonly class ControlSafeFormatter implements WrappableOutputFormatterInterface
{
    private const string CONTROL_CHARACTERS = '/[\x00-\x08\x0b-\x1f\x7f]/';

    public function __construct(
        private OutputFormatterInterface $formatter,
    ) {}

    public function setDecorated(bool $decorated): void
    {
        $this->formatter->setDecorated($decorated);
    }

    public function isDecorated(): bool
    {
        return $this->formatter->isDecorated();
    }

    public function setStyle(string $name, OutputFormatterStyleInterface $style): void
    {
        $this->formatter->setStyle($name, $style);
    }

    public function hasStyle(string $name): bool
    {
        return $this->formatter->hasStyle($name);
    }

    public function getStyle(string $name): OutputFormatterStyleInterface
    {
        return $this->formatter->getStyle($name);
    }

    public function format(?string $message): ?string
    {
        return $this->formatter->format($this->readable($message));
    }

    public function formatAndWrap(?string $message, int $width): string
    {
        $readable = $this->readable($message);

        return $this->formatter instanceof WrappableOutputFormatterInterface
            ? $this->formatter->formatAndWrap($readable, $width)
            : (string) $this->formatter->format($readable);
    }

    private function readable(?string $message): ?string
    {
        return $message === null
            ? null
            : (string) preg_replace(self::CONTROL_CHARACTERS, '?', $message);
    }
}
