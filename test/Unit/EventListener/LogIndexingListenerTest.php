<?php

namespace Hakam\AiLogInspectorBundle\Tests\Unit\EventListener;

use Hakam\AiLogInspector\Service\LogProcessorService;
use Hakam\AiLogInspectorBundle\EventListener\LogIndexingListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class LogIndexingListenerTest extends TestCase
{
    private LogProcessorService $processor;
    private LoggerInterface $logger;
    private LogIndexingListener $listener;
    private string $tempDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->processor = $this->createMock(LogProcessorService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/log-indexing-test-' . uniqid();
        $this->filesystem->mkdir($this->tempDir);

        $indexingConfig = [
            'auto_index' => true,
            'batch_size' => 10,
            'excluded_patterns' => ['/health', '/metrics']
        ];

        $logSources = [
            ['path' => $this->tempDir, 'pattern' => '*.log']
        ];

        $this->listener = new LogIndexingListener(
            $this->processor,
            $indexingConfig,
            $logSources,
            $this->logger
        );
    }

    protected function tearDown(): void
    {
        if ($this->filesystem->exists($this->tempDir)) {
            $this->filesystem->remove($this->tempDir);
        }
    }

    public function testOnKernelTerminateWithAutoIndexEnabled(): void
    {
        $this->createLogFile('app.log', '[2024-01-01] ERROR: Test error');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $event = new TerminateEvent($kernel, $request, $response);

        $this->processor->expects($this->atLeastOnce())
            ->method('process');

        $this->listener->onKernelTerminate($event);
    }

    public function testOnKernelTerminateWithAutoIndexDisabled(): void
    {
        $listener = new LogIndexingListener(
            $this->processor,
            ['auto_index' => false], // Disabled
            [['path' => $this->tempDir, 'pattern' => '*.log']],
            $this->logger
        );

        $this->createLogFile('app.log', '[2024-01-01] ERROR: Test error');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $event = new TerminateEvent($kernel, $request, $response);

        $this->processor->expects($this->never())
            ->method('process');

        $listener->onKernelTerminate($event);
    }

    public function testOnConsoleTerminateWithAutoIndexEnabled(): void
    {
        $this->createLogFile('console.log', '[2024-01-01] INFO: Console command executed');

        $command = $this->createMock(Command::class);
        $command->expects($this->once())
            ->method('getName')
            ->willReturn('cache:clear');

        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        $event = new ConsoleTerminateEvent($command, $input, $output, 0);

        $this->processor->expects($this->atLeastOnce())
            ->method('process');

        $this->listener->onConsoleTerminate($event);
    }

    public function testOnConsoleTerminateSkipsIndexingCommand(): void
    {
        $this->createLogFile('console.log', '[2024-01-01] INFO: Indexing logs');

        $command = $this->createMock(Command::class);
        $command->expects($this->once())
            ->method('getName')
            ->willReturn('ai:log-inspector:index'); // Should be skipped

        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        $event = new ConsoleTerminateEvent($command, $input, $output, 0);

        $this->processor->expects($this->never())
            ->method('process');

        $this->listener->onConsoleTerminate($event);
    }

    public function testOnKernelTerminateHandlesProcessorException(): void
    {
        $this->createLogFile('error.log', '[2024-01-01] ERROR: Test error');

        $this->processor->expects($this->once())
            ->method('process')
            ->willThrowException(new \Exception('Processing failed'));

        // Individual file processing errors are logged as warnings, not top-level errors
        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Failed to process log file for auto-indexing',
                $this->callback(function($context) {
                    return isset($context['error']) && $context['error'] === 'Processing failed';
                })
            );

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $event = new TerminateEvent($kernel, $request, $response);

        // Should not throw exception but log warning for file processing errors
        $this->listener->onKernelTerminate($event);
    }

    public function testOnConsoleTerminateHandlesProcessorException(): void
    {
        $this->createLogFile('console.log', '[2024-01-01] INFO: Console log');

        $this->processor->expects($this->once())
            ->method('process')
            ->willThrowException(new \Exception('Console processing failed'));

        // Individual file processing errors are logged as warnings, not top-level errors
        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Failed to process log file for auto-indexing',
                $this->callback(function($context) {
                    return isset($context['error']) && $context['error'] === 'Console processing failed';
                })
            );

        $command = $this->createMock(Command::class);
        $command->expects($this->once())
            ->method('getName')
            ->willReturn('test:command');

        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        $event = new ConsoleTerminateEvent($command, $input, $output, 0);

        $this->listener->onConsoleTerminate($event);
    }

    public function testProcessRecentLogsWithExcludedPatterns(): void
    {
        $logContent = "[2024-01-01] ERROR: Database failed\n";
        $logContent .= "[2024-01-01] INFO: /health endpoint\n"; // Should be excluded
        $logContent .= "[2024-01-01] INFO: /metrics request\n"; // Should be excluded
        $logContent .= "[2024-01-01] WARN: User failed\n";

        $this->createLogFile('app.log', $logContent);

        $this->processor->expects($this->once())
            ->method('process')
            ->willReturnCallback(function($virtualDocs) {
                // Verify excluded patterns are not included
                foreach ($virtualDocs as $doc) {
                    $content = $doc->message;
                    $this->assertStringNotContainsString('/health', $content);
                    $this->assertStringNotContainsString('/metrics', $content);
                }
                // Should have 2 entries (database error and user warning)
                $this->assertCount(2, $virtualDocs);
            });

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $event = new TerminateEvent($kernel, $request, $response);
        $this->listener->onKernelTerminate($event);
    }

    public function testProcessRecentLogsHandlesBatching(): void
    {
        // Create log content larger than batch size (10)
        $logContent = '';
        for ($i = 1; $i <= 25; $i++) {
            $logContent .= "[2024-01-01] INFO: Log entry {$i}\n";
        }

        $this->createLogFile('large.log', $logContent);

        $processCalls = 0;
        $this->processor->expects($this->atLeastOnce())
            ->method('process')
            ->willReturnCallback(function($virtualDocs) use (&$processCalls) {
                $processCalls++;
                // Each batch should be <= 10 (batch_size)
                $this->assertLessThanOrEqual(10, count($virtualDocs));
            });

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $event = new TerminateEvent($kernel, $request, $response);
        $this->listener->onKernelTerminate($event);

        // Should have been called multiple times for batching
        $this->assertGreaterThan(1, $processCalls);
    }

    public function testProcessRecentLogsSkipsUnreadableFiles(): void
    {
        $logFile = $this->tempDir . '/unreadable.log';
        file_put_contents($logFile, '[2024-01-01] ERROR: Test');
        chmod($logFile, 0000); // Make unreadable

        // The processor should not be called for unreadable files
        $this->processor->expects($this->never())
            ->method('process');

        // No warning is logged because getRecentLogLines returns empty array for unreadable files
        // and the listener just skips them silently
        $this->logger->expects($this->never())
            ->method('warning');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $event = new TerminateEvent($kernel, $request, $response);
        $this->listener->onKernelTerminate($event);

        chmod($logFile, 0644); // Restore for cleanup
    }

    public function testProcessRecentLogsWithEmptyFiles(): void
    {
        $this->createLogFile('empty.log', ''); // Empty file

        $this->processor->expects($this->never())
            ->method('process');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $event = new TerminateEvent($kernel, $request, $response);
        $this->listener->onKernelTerminate($event);
    }

    public function testProcessRecentLogsWithNonExistentDirectory(): void
    {
        $listener = new LogIndexingListener(
            $this->processor,
            ['auto_index' => true],
            [['path' => '/nonexistent/path', 'pattern' => '*.log']],
            $this->logger
        );

        $this->processor->expects($this->never())
            ->method('process');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $event = new TerminateEvent($kernel, $request, $response);
        $listener->onKernelTerminate($event);
    }

    public function testLogLevelExtraction(): void
    {
        $logContent = "[2024-01-01] ERROR: Error message\n";
        $logContent .= "[2024-01-01] WARNING: Warning message\n";
        $logContent .= "[2024-01-01] INFO: Info message\n";
        $logContent .= "[2024-01-01] Something else\n"; // Unknown level

        $this->createLogFile('levels.log', $logContent);

        $this->processor->expects($this->once())
            ->method('process')
            ->willReturnCallback(function($virtualDocs) {
                $levels = array_map(fn($doc) => strtolower($doc->level), $virtualDocs);
                $this->assertContains('error', $levels);
                $this->assertContains('warning', $levels);
                $this->assertContains('info', $levels);
                $this->assertContains('unknown', $levels);
            });

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $event = new TerminateEvent($kernel, $request, $response);
        $this->listener->onKernelTerminate($event);
    }

    public function testCategoryExtraction(): void
    {
        $logContent = "[2024-01-01] ERROR: Database connection failed\n";
        $logContent .= "[2024-01-01] INFO: Payment completed\n";
        $logContent .= "[2024-01-01] WARN: Authentication failed\n";
        $logContent .= "[2024-01-01] ERROR: API timeout\n";
        $logContent .= "[2024-01-01] INFO: Security alert\n";
        $logContent .= "[2024-01-01] DEBUG: General message\n";

        $this->createLogFile('categories.log', $logContent);

        $this->processor->expects($this->once())
            ->method('process')
            ->willReturnCallback(function($virtualDocs) {
                $categories = array_map(fn($doc) => $doc->channel, $virtualDocs);
                $this->assertContains('database', $categories);
                $this->assertContains('payment', $categories);
                $this->assertContains('authentication', $categories);
                $this->assertContains('api', $categories);
                $this->assertContains('security', $categories);
                $this->assertContains('general', $categories);
            });

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $event = new TerminateEvent($kernel, $request, $response);
        $this->listener->onKernelTerminate($event);
    }

    public function testProcessRecentLogsLimitsToRecentLines(): void
    {
        // Create a large log file with 100 lines, listener should only process last 50
        $logContent = '';
        for ($i = 1; $i <= 100; $i++) {
            $logContent .= "[2024-01-01] INFO: Log entry {$i}\n";
        }

        $this->createLogFile('large.log', $logContent);

        $totalProcessedEntries = 0;
        $this->processor->expects($this->atLeastOnce())
            ->method('process')
            ->willReturnCallback(function($virtualDocs) use (&$totalProcessedEntries) {
                $totalProcessedEntries += count($virtualDocs);
            });

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $event = new TerminateEvent($kernel, $request, $response);
        $this->listener->onKernelTerminate($event);

        // Should process at most 50 recent entries
        $this->assertLessThanOrEqual(50, $totalProcessedEntries);
    }

    public function testAutoIndexedMetadata(): void
    {
        $logContent = "[2024-01-01] INFO: Test message\n";
        $this->createLogFile('auto.log', $logContent);

        $this->processor->expects($this->once())
            ->method('process')
            ->willReturnCallback(function($virtualDocs) {
                foreach ($virtualDocs as $doc) {
                    $this->assertTrue($doc->context['auto_indexed']);
                    $this->assertArrayHasKey('source_file', $doc->context);
                    $this->assertArrayHasKey('line_number', $doc->context);
                    // timestamp is now part of the VirtualLogDocument timestamp property
                    $this->assertInstanceOf(\DateTimeInterface::class, $doc->timestamp);
                }
            });

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $event = new TerminateEvent($kernel, $request, $response);
        $this->listener->onKernelTerminate($event);
    }

    public function testWithoutLogger(): void
    {
        $listener = new LogIndexingListener(
            $this->processor,
            ['auto_index' => true],
            [['path' => $this->tempDir, 'pattern' => '*.log']],
            null // No logger
        );

        $this->createLogFile('no-logger.log', '[2024-01-01] INFO: Test');

        $this->processor->expects($this->once())
            ->method('process')
            ->willThrowException(new \Exception('Test error'));

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $event = new TerminateEvent($kernel, $request, $response);

        // Should not throw exception even without logger
        $listener->onKernelTerminate($event);
    }

    private function createLogFile(string $filename, string $content): void
    {
        $filepath = $this->tempDir . '/' . $filename;
        file_put_contents($filepath, $content);
    }
}
