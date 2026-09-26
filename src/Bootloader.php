<?php

declare(strict_types=1);

namespace IfCastle\Monolog;

use IfCastle\Application\Bootloader\BootloaderContextInterface;
use IfCastle\Application\Bootloader\BootloaderExecutorInterface;
use IfCastle\Application\Bootloader\BootloaderInterface;
use IfCastle\Application\EngineRolesEnum;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;

/**
 * Binds a Monolog logger. A console application logs to stdout; any application logs to a file
 * when the [logger] section of the main config names one:
 *
 *     [logger]
 *     file = "logs/app.log"  ; relative to the application directory, or absolute
 *     level = "info"         ; the lowest level written, a PSR-3 level name; "info" when omitted
 */
final class Bootloader implements BootloaderInterface
{
    public const string SECTION     = 'logger';

    private const string DEFAULT_LEVEL = 'info';

    public function buildBootloader(BootloaderExecutorInterface $bootloaderExecutor): void
    {
        $bootloaderExecutor->getBootloaderContext()->getSystemEnvironmentBootBuilder()
            ->bindObject(
                LoggerInterface::class,
                $this->buildLogger($bootloaderExecutor->getBootloaderContext())
            );
    }

    private function buildLogger(BootloaderContextInterface $bootloaderContext): LoggerInterface
    {
        $logger                     = new Logger($bootloaderContext->getApplicationType());
        $configuration              = $bootloaderContext->getApplicationConfig()->findSection(self::SECTION);
        $roles                      = $bootloaderContext->getExecutionRoles();

        if (\in_array(EngineRolesEnum::CONSOLE->value, $roles, true)) {
            $logHandler             = new StreamHandler('php://stdout');
            $logHandler->pushProcessor(new PsrLogMessageProcessor());
            $logHandler->setFormatter(new LineFormatter());

            $logger->pushHandler($logHandler);
        }

        $file                       = $configuration['file'] ?? '';

        if (\is_string($file) && $file !== '') {
            $level                  = $configuration['level'] ?? self::DEFAULT_LEVEL;

            $logger->pushHandler($this->buildFileHandler(
                $bootloaderContext->getApplicationDirectory(), $file, \is_string($level) ? $level : self::DEFAULT_LEVEL
            ));
        }

        return $logger;
    }

    private function buildFileHandler(string $appDir, string $file, string $level): StreamHandler
    {
        if (!\str_starts_with($file, '/')) {
            $file                   = $appDir . '/' . $file;
        }

        // StreamHandler creates the missing directories on the first record.
        $fileHandler                = new StreamHandler($file, Level::fromName($level));
        $fileHandler->pushProcessor(new PsrLogMessageProcessor());

        $formatter                  = new LineFormatter();
        $formatter->includeStacktraces();
        $fileHandler->setFormatter($formatter);

        return $fileHandler;
    }
}
