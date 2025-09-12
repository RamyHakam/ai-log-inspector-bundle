<?php

namespace Hakam\AiLogInspectorBundle\Tests\Unit\Command;

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspectorBundle\Command\AskLogAgentCommand;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class AskLogAgentCommandTest extends TestCase
{
    private LogInspectorAgent $agent;
    private AskLogAgentCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->agent = $this->createMock(LogInspectorAgent::class);
        $this->command = new AskLogAgentCommand($this->agent);
        
        $application = new Application();
        $application->add($this->command);
        
        $this->commandTester = new CommandTester($this->command);
    }

    public function testCommandConfiguration(): void
    {
        $this->assertEquals('ai:log-inspector:ask', $this->command->getName());
        $this->assertEquals('Ask the AI agent questions about your logs', $this->command->getDescription());
        
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasArgument('question'));
        $this->assertTrue($definition->hasOption('json'));
        
        $questionArgument = $definition->getArgument('question');
        $this->assertTrue($questionArgument->isRequired());
        $this->assertEquals('The question to ask about the logs', $questionArgument->getDescription());
        
        $jsonOption = $definition->getOption('json');
        $this->assertFalse($jsonOption->acceptValue());
        $this->assertEquals('Output response as JSON', $jsonOption->getDescription());
    }

    public function testExecuteWithSimpleQuestion(): void
    {
        $question = 'What errors occurred today?';
        $response = 'Found 3 database connection errors and 2 payment failures.';
        
        $mockResult = $this->createMock(ResultInterface::class);
        $mockResult->expects($this->once())
            ->method('getContent')
            ->willReturn($response);
        
        $this->agent->expects($this->once())
            ->method('ask')
            ->with($question)
            ->willReturn($mockResult);

        $exitCode = $this->commandTester->execute([
            'question' => $question
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('🤖 Analyzing logs for: ' . $question, $output);
        $this->assertStringContainsString('🧠 AI Analysis Result', $output);
        $this->assertStringContainsString($response, $output);
        $this->assertStringContainsString('Analysis completed successfully!', $output);
    }

    public function testExecuteWithJsonOutput(): void
    {
        $question = 'Show me payment errors';
        $response = 'Payment gateway timeout errors detected.';
        
        $mockResult = $this->createMock(ResultInterface::class);
        $mockResult->expects($this->once())
            ->method('getContent')
            ->willReturn($response);
        
        $this->agent->expects($this->once())
            ->method('ask')
            ->with($question)
            ->willReturn($mockResult);

        $exitCode = $this->commandTester->execute([
            'question' => $question,
            '--json' => true
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        
        $jsonOutput = json_decode(trim($output), true);
        $this->assertNotNull($jsonOutput);
        $this->assertEquals($question, $jsonOutput['question']);
        $this->assertEquals($response, $jsonOutput['response']);
        $this->assertArrayHasKey('timestamp', $jsonOutput);
    }

    public function testExecuteHandlesAgentException(): void
    {
        $question = 'What happened?';
        $errorMessage = 'Agent service unavailable';
        
        $this->agent->expects($this->once())
            ->method('ask')
            ->with($question)
            ->willThrowException(new \Exception($errorMessage));

        $exitCode = $this->commandTester->execute([
            'question' => $question
        ]);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Failed to analyze logs: ' . $errorMessage, $output);
        $this->assertStringContainsString('Make sure logs are indexed first', $output);
    }

    public function testExecuteHandlesAgentExceptionWithJsonOutput(): void
    {
        $question = 'What happened?';
        $errorMessage = 'Vector store connection failed';
        
        $this->agent->expects($this->once())
            ->method('ask')
            ->with($question)
            ->willThrowException(new \Exception($errorMessage));

        $exitCode = $this->commandTester->execute([
            'question' => $question,
            '--json' => true
        ]);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        
        $jsonOutput = json_decode(trim($output), true);
        $this->assertNotNull($jsonOutput);
        $this->assertTrue($jsonOutput['error']);
        $this->assertEquals($errorMessage, $jsonOutput['message']);
        $this->assertEquals($question, $jsonOutput['question']);
        $this->assertArrayHasKey('timestamp', $jsonOutput);
    }

    public function testExecuteWithVerboseErrorOutput(): void
    {
        $question = 'Debug question';
        $exception = new \Exception('Test error', 0, new \Exception('Root cause'));
        
        $this->agent->expects($this->once())
            ->method('ask')
            ->with($question)
            ->willThrowException($exception);

        $exitCode = $this->commandTester->execute([
            'question' => $question
        ], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Stack trace:', $output);
    }

    public function testExecuteWithComplexQuestion(): void
    {
        $question = 'What caused the database connection failures between 2 PM and 3 PM?';
        $response = 'Database connection pool exhaustion caused by increased traffic. Pool size reached maximum of 20 connections at 2:15 PM.';
        
        $mockResult = $this->createMock(ResultInterface::class);
        $mockResult->expects($this->once())
            ->method('getContent')
            ->willReturn($response);
        
        $this->agent->expects($this->once())
            ->method('ask')
            ->with($question)
            ->willReturn($mockResult);

        $exitCode = $this->commandTester->execute([
            'question' => $question
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString($response, $output);
    }

    public function testExecuteWithEmptyResponse(): void
    {
        $question = 'Show me nothing';
        $response = '';
        
        $mockResult = $this->createMock(ResultInterface::class);
        $mockResult->expects($this->once())
            ->method('getContent')
            ->willReturn($response);
        
        $this->agent->expects($this->once())
            ->method('ask')
            ->with($question)
            ->willReturn($mockResult);

        $exitCode = $this->commandTester->execute([
            'question' => $question
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Analysis completed successfully!', $output);
    }

    public function testHelpText(): void
    {
        $help = $this->command->getHelp();
        $this->assertStringContainsString('This command allows you to ask natural language questions', $help);
        $this->assertStringContainsString('php bin/console ai:log-inspector:ask "What errors occurred today?"', $help);
        $this->assertStringContainsString('php bin/console ai:log-inspector:ask "Show me payment failures"', $help);
        $this->assertStringContainsString('php bin/console ai:log-inspector:ask "Why did the database connection fail?"', $help);
    }

    public function testCommandSynopsis(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasArgument('question'));
        $this->assertTrue($definition->hasOption('json'));
        
        // Test that json option has correct short name
        $jsonOption = $definition->getOption('json');
        $this->assertEquals('j', $jsonOption->getShortcut());
    }

    public function testExecuteWithSpecialCharactersInQuestion(): void
    {
        $question = 'What errors contain "payment & billing" issues?';
        $response = 'Found payment gateway errors in billing module.';
        
        $mockResult = $this->createMock(ResultInterface::class);
        $mockResult->expects($this->once())
            ->method('getContent')
            ->willReturn($response);
        
        $this->agent->expects($this->once())
            ->method('ask')
            ->with($question)
            ->willReturn($mockResult);

        $exitCode = $this->commandTester->execute([
            'question' => $question
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString($response, $this->commandTester->getDisplay());
    }

    public function testJsonOutputStructure(): void
    {
        $question = 'Test question';
        $response = 'Test response';
        
        $mockResult = $this->createMock(ResultInterface::class);
        $mockResult->expects($this->once())
            ->method('getContent')
            ->willReturn($response);
        
        $this->agent->expects($this->once())
            ->method('ask')
            ->willReturn($mockResult);

        $this->commandTester->execute([
            'question' => $question,
            '--json' => true
        ]);

        $output = trim($this->commandTester->getDisplay());
        $jsonOutput = json_decode($output, true);
        
        $this->assertIsArray($jsonOutput);
        $this->assertArrayHasKey('question', $jsonOutput);
        $this->assertArrayHasKey('response', $jsonOutput);
        $this->assertArrayHasKey('timestamp', $jsonOutput);
        
        // Verify timestamp format
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $jsonOutput['timestamp']);
    }
}
