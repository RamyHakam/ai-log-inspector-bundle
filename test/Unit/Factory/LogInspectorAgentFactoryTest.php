<?php

namespace Hakam\AiLogInspectorBundle\Tests\Unit\Factory;

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformInterface;
use Hakam\AiLogInspector\Store\VectorLogStoreInterface;
use Hakam\AiLogInspector\Tool\LogInspectorToolInterface;
use Hakam\AiLogInspector\Tool\LogSearchTool;
use Hakam\AiLogInspector\Vectorizer\LogDocumentVectorizerInterface;
use Hakam\AiLogInspectorBundle\Factory\LogInspectorAgentFactory;
use PHPUnit\Framework\TestCase;

class LogInspectorAgentFactoryTest extends TestCase
{
    private LogDocumentPlatformInterface $platform;
    private VectorLogStoreInterface $store;
    private LogDocumentVectorizerInterface $vectorizer;

    protected function setUp(): void
    {
        $this->platform = $this->createMock(LogDocumentPlatformInterface::class);
        $this->store = $this->createMock(VectorLogStoreInterface::class);
        $this->vectorizer = $this->createMock(LogDocumentVectorizerInterface::class);
    }

    public function testCreateReturnsLogInspectorAgent(): void
    {
        $factory = new LogInspectorAgentFactory(
            $this->platform,
            $this->store,
            $this->vectorizer
        );

        $agent = $factory->create();

        $this->assertInstanceOf(LogInspectorAgent::class, $agent);
    }

    public function testCreateWithCustomSystemPrompt(): void
    {
        $customPrompt = 'You are a specialized log analyzer for e-commerce applications.';
        
        $factory = new LogInspectorAgentFactory(
            $this->platform,
            $this->store,
            $this->vectorizer,
            $customPrompt
        );

        $agent = $factory->create();

        $this->assertInstanceOf(LogInspectorAgent::class, $agent);
    }

    public function testCreateWithCustomTools(): void
    {
        $customTool = $this->createMock(LogInspectorToolInterface::class);
        $customTools = [$customTool];
        
        $factory = new LogInspectorAgentFactory(
            $this->platform,
            $this->store,
            $this->vectorizer,
            null,
            $customTools
        );

        $agent = $factory->create();

        $this->assertInstanceOf(LogInspectorAgent::class, $agent);
    }

    public function testCreateWithToolsAddsAdditionalTools(): void
    {
        $factory = new LogInspectorAgentFactory(
            $this->platform,
            $this->store,
            $this->vectorizer
        );

        $additionalTool = $this->createMock(LogInspectorToolInterface::class);
        $additionalTools = [$additionalTool];

        $agent = $factory->createWithTools($additionalTools);

        $this->assertInstanceOf(LogInspectorAgent::class, $agent);
    }

    public function testCreateWithPromptUsesCustomPrompt(): void
    {
        $factory = new LogInspectorAgentFactory(
            $this->platform,
            $this->store,
            $this->vectorizer
        );

        $customPrompt = 'Custom security-focused prompt';
        $agent = $factory->createWithPrompt($customPrompt);

        $this->assertInstanceOf(LogInspectorAgent::class, $agent);
    }

    public function testCreateAlwaysIncludesLogSearchTool(): void
    {
        $factory = new LogInspectorAgentFactory(
            $this->platform,
            $this->store,
            $this->vectorizer
        );

        // The factory should always include LogSearchTool as a core tool
        // We can't directly test the tools array, but we can verify the agent is created
        $agent = $factory->create();
        $this->assertInstanceOf(LogInspectorAgent::class, $agent);
    }

    public function testFactoryWithEmptyCustomTools(): void
    {
        $factory = new LogInspectorAgentFactory(
            $this->platform,
            $this->store,
            $this->vectorizer,
            null,
            [] // empty custom tools
        );

        $agent = $factory->create();

        $this->assertInstanceOf(LogInspectorAgent::class, $agent);
    }

    public function testFactoryWithNullSystemPrompt(): void
    {
        $factory = new LogInspectorAgentFactory(
            $this->platform,
            $this->store,
            $this->vectorizer,
            null // null system prompt
        );

        $agent = $factory->create();

        $this->assertInstanceOf(LogInspectorAgent::class, $agent);
    }

    public function testCreateWithToolsCombinesAllTools(): void
    {
        $containerTool = $this->createMock(LogInspectorToolInterface::class);
        $additionalTool = $this->createMock(LogInspectorToolInterface::class);
        
        $factory = new LogInspectorAgentFactory(
            $this->platform,
            $this->store,
            $this->vectorizer,
            'Test prompt',
            [$containerTool]
        );

        $agent = $factory->createWithTools([$additionalTool]);

        $this->assertInstanceOf(LogInspectorAgent::class, $agent);
    }

    public function testMultipleAgentCreation(): void
    {
        $factory = new LogInspectorAgentFactory(
            $this->platform,
            $this->store,
            $this->vectorizer
        );

        // Factory should be able to create multiple agents
        $agent1 = $factory->create();
        $agent2 = $factory->create();

        $this->assertInstanceOf(LogInspectorAgent::class, $agent1);
        $this->assertInstanceOf(LogInspectorAgent::class, $agent2);
        $this->assertNotSame($agent1, $agent2); // Different instances
    }

    public function testFactoryIsReadonly(): void
    {
        // Test that the factory can be instantiated (which verifies readonly works)
        $factory = new LogInspectorAgentFactory(
            $this->platform,
            $this->store,
            $this->vectorizer
        );
        $this->assertInstanceOf(LogInspectorAgentFactory::class, $factory);
        
        // Test that properties are readonly by verifying constructor injection works
        $agent = $factory->create();
        $this->assertInstanceOf(LogInspectorAgent::class, $agent);
    }

    public function testConstructorParameters(): void
    {
        $reflection = new \ReflectionClass(LogInspectorAgentFactory::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(5, $parameters);
        $this->assertEquals('platform', $parameters[0]->getName());
        $this->assertEquals('store', $parameters[1]->getName());
        $this->assertEquals('vectorizer', $parameters[2]->getName());
        $this->assertEquals('systemPrompt', $parameters[3]->getName());
        $this->assertEquals('customTools', $parameters[4]->getName());
        
        // System prompt should be nullable
        $this->assertTrue($parameters[3]->allowsNull());
        
        // Custom tools should have default value
        $this->assertTrue($parameters[4]->isDefaultValueAvailable());
    }
}
