<?php

declare(strict_types=1);

namespace IfCastle\Monolog;

use IfCastle\Application\Bootloader\Builder\BootloaderBuilderInMemory;
use IfCastle\Application\MockApplication\TestBootloader;
use IfCastle\Application\Runner;
use IfCastle\Application\TestApplication;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BootloaderTest extends TestCase
{
    private string $appDir;

    private ?Runner $runner         = null;

    #[\Override]
    protected function setUp(): void
    {
        $this->appDir               = \sys_get_temp_dir() . '/ifcastle-monolog-' . \bin2hex(\random_bytes(6));

        // ApplicationAbstract refuses to start without a vendor directory.
        \mkdir($this->appDir . '/vendor', 0o777, true);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->runner?->dispose();

        foreach (['/logs/app.log', '/logs/critical.log', '/logs', '/vendor', ''] as $path) {
            $path                   = $this->appDir . $path;

            if (\is_file($path)) {
                \unlink($path);
            } elseif (\is_dir($path)) {
                \rmdir($path);
            }
        }
    }

    public function testErrorWithExceptionReachesTheConfiguredFile(): void
    {
        $logger                     = $this->bootLogger(['file' => 'logs/app.log', 'level' => 'warning']);

        $logger->info('below the level');
        $logger->error('request failed', ['exception' => new \RuntimeException('database is gone')]);

        $log                        = (string) \file_get_contents($this->appDir . '/logs/app.log');

        $this->assertStringNotContainsString('below the level', $log);
        $this->assertStringContainsString('request failed', $log);
        $this->assertStringContainsString('RuntimeException', $log);
        $this->assertStringContainsString('database is gone', $log);
        $this->assertStringContainsString(__FILE__, $log, 'the stack trace is written');
    }

    public function testNoFileWithoutConfiguration(): void
    {
        $this->bootLogger([])->error('nowhere');

        $this->assertDirectoryDoesNotExist($this->appDir . '/logs');
    }

    /**
     * @param array<string, string> $loggerConfig the [logger] section of main.ini
     */
    private function bootLogger(array $loggerConfig): LoggerInterface
    {
        $this->runner               = new Runner($this->appDir, 'server', TestApplication::class)
            ->defineBootloaderBuilder(new BootloaderBuilderInMemory(
                $this->appDir, 'server', [], [TestBootloader::class, Bootloader::class], ['logger' => $loggerConfig]
            ));

        $logger                     = $this->runner->run()->getSystemEnvironment()->resolveDependency(LoggerInterface::class);
        $this->assertInstanceOf(LoggerInterface::class, $logger);

        return $logger;
    }
}
