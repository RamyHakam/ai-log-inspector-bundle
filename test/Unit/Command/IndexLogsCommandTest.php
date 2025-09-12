<?php

namespace Hakam\AiLogInspectorBundle\Tests\Unit\Command;

use Hakam\AiLogInspector\Service\LogProcessorService;
use Hakam\AiLogInspectorBundle\Command\IndexLogsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class IndexLogsCommandTest extends TestCase
{
    private LogProcessorService $processor;
    private IndexLogsCommand $command;
    private CommandTester $commandTester;
    private string $tempDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->processor = $this->createMock(LogProcessorService::class);
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/log-inspector-test-' . uniqid();
        $this->filesystem->mkdir($this->tempDir);
        
        $logSources = [
            ['path' => $this->tempDir, 'pattern' => '*.log', 'recursive' => true]
        ];
        
        $indexingConfig = [
            'batch_size' => 10,
            'excluded_patterns' => ['/health', '/metrics']
        ];
        
        $this->command = new IndexLogsCommand($this->processor, $logSources, $indexingConfig);
        
        $application = new Application();
        $application->add($this->command);
        
        $this->commandTester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        if ($this->filesystem->exists($this->tempDir)) {
            $this->filesystem->remove($this->tempDir);
        }
    }

    public function testCommandConfiguration(): void
    {
        $this->assertEquals('ai:log-inspector:index', $this->command->getName());
        $this->assertEquals('Index log files for AI analysis', $this->command->getDescription());
        
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('path'));
        $this->assertTrue($definition->hasOption('pattern'));
        $this->assertTrue($definition->hasOption('force'));
        
        $pathOption = $definition->getOption('path');
        $this->assertTrue($pathOption->acceptValue());
        $this->assertEquals('p', $pathOption->getShortcut());
        
        $patternOption = $definition->getOption('pattern');
        $this->assertEquals('*.log', $patternOption->getDefault());
        
        $forceOption = $definition->getOption('force');
        $this->assertEquals('f', $forceOption->getShortcut());
        $this->assertFalse($forceOption->acceptValue());
    }

    public function testExecuteWithNoLogFiles(): void
    {
        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('No log files found to index!', $output);
        $this->assertStringContainsString('Make sure your log sources are configured correctly', $output);
    }

    public function testExecuteWithLogFiles(): void
    {
        // Create test log files
        $logFile1 = $this->tempDir . '/app.log';
        $logFile2 = $this->tempDir . '/error.log';
        
        file_put_contents($logFile1, "[2024-01-01 10:00:00] ERROR: Database connection failed\n[2024-01-01 10:01:00] INFO: Application started\n");
        file_put_contents($logFile2, "[2024-01-01 10:02:00] CRITICAL: Payment system down\n");

        $this->processor->expects($this->atLeastOnce())
            ->method('process');

        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('📁 Discovering log files from configured sources...', $output);
        $this->assertStringContainsString('✨ Log indexing completed!', $output);
        $this->assertStringContainsString('Files processed', $output);
        $this->assertStringContainsString('Log entries indexed', $output);
    }

    public function testExecuteWithSpecificPath(): void
    {
        $logFile = $this->tempDir . '/specific.log';
        file_put_contents($logFile, "[2024-01-01 10:00:00] ERROR: Test error\n");

        $this->processor->expects($this->once())
            ->method('process');

        $exitCode = $this->commandTester->execute([
            '--path' => $logFile
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('📄 Indexing specific log file:', $output);
        $this->assertStringContainsString($logFile, $output);
        $this->assertStringContainsString('✨ Log indexing completed!', $output);
    }

    public function testExecuteWithPattern(): void
    {
        // Create files with different patterns
        $errorLog = $this->tempDir . '/error.log';
        $accessLog = $this->tempDir . '/access.log';
        $debugLog = $this->tempDir . '/debug.txt';
        
        file_put_contents($errorLog, "[2024-01-01] ERROR: Test\n");
        file_put_contents($accessLog, "[2024-01-01] INFO: Test\n");  
        file_put_contents($debugLog, "[2024-01-01] DEBUG: Test\n");

        $this->processor->expects($this->atLeastOnce())
            ->method('process');

        $exitCode = $this->commandTester->execute([
            '--pattern' => 'error*.log'
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('✨ Log indexing completed!', $output);
    }

    public function testExecuteHandlesProcessorException(): void
    {
        $logFile = $this->tempDir . '/failing.log';
        file_put_contents($logFile, "[2024-01-01] ERROR: Test\n");

        $this->processor->expects($this->once())
            ->method('process')
            ->willThrowException(new \Exception('Processing failed'));

        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode); // Still success as it handles partial failures
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Failed to process', $output);
        $this->assertStringContainsString('Processing failed', $output);
    }

    public function testExecuteWithUnreadableFile(): void
    {
        $logFile = $this->tempDir . '/unreadable.log';
        file_put_contents($logFile, "test content\n");
        chmod($logFile, 0000); // Make unreadable

        // Should not call processor for unreadable files
        $this->processor->expects($this->never())
            ->method('process');

        $exitCode = $this->commandTester->execute([]);

        chmod($logFile, 0644); // Restore permissions for cleanup
        $this->assertEquals(Command::SUCCESS, $exitCode);
    }

    public function testExecuteWithExcludedPatterns(): void
    {
        $logFile = $this->tempDir . '/app.log';
        $content = "[2024-01-01] ERROR: Database failed\n";
        $content .= "[2024-01-01] INFO: /health endpoint called\n"; // Should be excluded
        $content .= "[2024-01-01] INFO: /metrics request\n"; // Should be excluded
        $content .= "[2024-01-01] WARN: User login failed\n";
        
        file_put_contents($logFile, $content);

        $this->processor->expects($this->atLeastOnce())
            ->method('process')
            ->willReturnCallback(function($virtualDocs) {
                // Verify excluded patterns are not included
                foreach ($virtualDocs as $doc) {
                    $content = $doc->message;
                    $this->assertStringNotContainsString('/health', $content);
                    $this->assertStringNotContainsString('/metrics', $content);
                }
            });

        $this->commandTester->execute([]);
    }

    public function testExecuteWithBatchProcessing(): void
    {
        $logFile = $this->tempDir . '/large.log';
        $content = '';
        
        // Create content larger than batch size (10)
        for ($i = 1; $i <= 25; $i++) {
            $content .= "[2024-01-01] INFO: Log entry {$i}\n";
        }
        
        file_put_contents($logFile, $content);

        $processCalls = 0;
        $this->processor->expects($this->atLeastOnce())
            ->method('process')
            ->willReturnCallback(function($virtualDocs) use (&$processCalls) {
                $processCalls++;
                // Each batch should have at most 10 entries (batch_size)
                $this->assertLessThanOrEqual(10, count($virtualDocs));
            });

        $this->commandTester->execute([]);
        
        // Should have been called multiple times due to batching
        $this->assertGreaterThan(1, $processCalls);
    }

    public function testLogLevelExtraction(): void
    {
        $logFile = $this->tempDir . '/levels.log';
        $content = "[2024-01-01] ERROR: Error message\n";
        $content .= "[2024-01-01] WARNING: Warning message\n";
        $content .= "[2024-01-01] INFO: Info message\n";
        $content .= "[2024-01-01] DEBUG: Debug message\n";
        $content .= "[2024-01-01] Something else\n"; // Unknown level
        
        file_put_contents($logFile, $content);

        $this->processor->expects($this->once())
            ->method('process')
            ->willReturnCallback(function($virtualDocs) {
                $levels = array_map(fn($doc) => strtolower($doc->level), $virtualDocs);
                $this->assertContains('error', $levels);
                $this->assertContains('warning', $levels);
                $this->assertContains('info', $levels);
                $this->assertContains('debug', $levels);
                $this->assertContains('unknown', $levels);
            });

        $this->commandTester->execute([]);
    }

    public function testCategoryExtraction(): void
    {
        $logFile = $this->tempDir . '/categories.log';
        $content = "[2024-01-01] ERROR: Database connection failed\n";
        $content .= "[2024-01-01] INFO: Payment transaction completed\n";
        $content .= "[2024-01-01] WARN: Authentication failed for user\n";
        $content .= "[2024-01-01] ERROR: API request timeout\n";
        $content .= "[2024-01-01] INFO: Security breach detected\n";
        $content .= "[2024-01-01] DEBUG: General debug message\n";
        
        file_put_contents($logFile, $content);

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

        $this->commandTester->execute([]);
    }

    public function testHelpText(): void
    {
        $help = $this->command->getHelp();
        $this->assertStringContainsString('This command indexes log files for AI analysis', $help);
        $this->assertStringContainsString('php bin/console ai:log-inspector:index', $help);
        $this->assertStringContainsString('-p /var/logs/app.log', $help);
        $this->assertStringContainsString('--pattern="error*.log"', $help);
    }

    public function testExecuteWithVerboseOutput(): void
    {
        $logFile = $this->tempDir . '/verbose.log';
        file_put_contents($logFile, "[2024-01-01] ERROR: Test\n");

        $this->processor->expects($this->once())
            ->method('process')
            ->willThrowException(new \Exception('Detailed error message'));

        $exitCode = $this->commandTester->execute([], [
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        // Check that the detailed error message appears in the verbose output
        // The output may be wrapped, so check for the key part of the message
        $this->assertStringContainsString('Detailed', $output);
        $this->assertStringContainsString('error message', $output);
    }

    public function testExecuteHandlesMajorException(): void
    {
        // Create a scenario where the entire process fails
        $command = new IndexLogsCommand(
            $this->processor,
            [['path' => '/nonexistent/path', 'pattern' => '*.log']], // Invalid path
            []
        );
        
        $application = new Application();
        $application->add($command);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);

        // Should handle gracefully and show no files found
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No log files found to index!', $output);
    }

    public function testProgressBarDisplay(): void
    {
        // Create multiple log files to see progress
        for ($i = 1; $i <= 3; $i++) {
            $logFile = $this->tempDir . "/app{$i}.log";
            file_put_contents($logFile, "[2024-01-01] INFO: Test entry {$i}\n");
        }

        $this->processor->expects($this->atLeastOnce())
            ->method('process');

        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        
        // Check for progress indicators
        $this->assertStringContainsString('Files processed', $output);
        $this->assertStringContainsString('Log entries indexed', $output);
        $this->assertStringContainsString('Total files found', $output);
    }
}
