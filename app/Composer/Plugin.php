<?php

declare(strict_types=1);

namespace App\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\EventDispatcher\ScriptExecutionException;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Symfony\Component\Process\Process;

final class Plugin implements EventSubscriberInterface, PluginInterface
{
    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [ScriptEvents::POST_UPDATE_CMD => 'audit'];
    }

    public function activate(Composer $composer, IOInterface $io): void {}

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}

    public function audit(Event $event): void
    {
        $io = $event->getIO();
        $binDir = $event->getComposer()->getConfig()->get('bin-dir');

        $gate = new Gate((string) getcwd(), is_string($binDir) ? $binDir : '');

        $notice = $gate->baselineNotice();

        if ($notice !== null) {
            $io->writeError('<comment>'.$notice.'</comment>');

            return;
        }

        $command = $gate->command($io->isDecorated(), $io->isVerbose());

        if ($command === null) {
            return;
        }

        $process = new Process($command, $gate->rootPath, $gate->environment());
        $process->setTimeout(null);
        $process->run(static function (string $type, string $buffer) use ($io): void {
            $io->write($buffer, false);
        });

        if (! $process->isSuccessful()) {
            throw new ScriptExecutionException(
                'porto found packages that your ledger does not cover.',
                $process->getExitCode() ?? 1,
            );
        }
    }
}
