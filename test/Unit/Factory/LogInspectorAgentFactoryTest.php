<?php

namespace Hakam\AiLogInspectorBundle\Tests\Unit\Factory;

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Tool\LogInspectorToolInterface;
use Hakam\AiLogInspectorBundle\Factory\LogInspectorAgentFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class LogInspectorAgentFactoryTest extends TestCase
{
    public function testCreateReturnsLogInspectorAgent(): void
    {
        $tools = [];
        $config = [
            'provider' => 'openai',
            'api_key' => 'sk-test-api-key',
            'model' => [
                'name' => 'gpt-4',
                'capabilities' => ['text_generation'],
                'options' => ['temperature' => 0.7]
            ]
        ];

        $factory = $this->createFactoryInstance($tools, $config);
        $agent = $factory->create();

        $this->assertInstanceOf(LogInspectorAgent::class, $agent);
    }

    public function testCreateWithTools(): void
    {
        $mockTool = $this->createMock(LogInspectorToolInterface::class);
        $tools = [$mockTool];
        $config = [
            'provider' => 'anthropic',
            'api_key' => 'sk-test-key',
            'model' => [
                'name' => 'claude-3-sonnet',
                'capabilities' => ['text_generation'],
                'options' => ['temperature' => 0.5]
            ]
        ];

        $factory = $this->createFactoryInstance($tools, $config);
        $agent = $factory->create();

        $this->assertInstanceOf(LogInspectorAgent::class, $agent);
    }

    public function testCreateWithDifferentProviders(): void
    {
        $testCases = [
            ['provider' => 'openai', 'model' => ['name' => 'gpt-4']],
            ['provider' => 'anthropic', 'model' => ['name' => 'claude-3-sonnet']],
        ];

        foreach ($testCases as $config) {
            $config['api_key'] = 'sk-test-key';
            $config['model']['capabilities'] = ['text_generation'];
            $config['model']['options'] = ['temperature' => 0.7];

            $factory = $this->createFactoryInstance([], $config);
            $agent = $factory->create();

            $this->assertInstanceOf(LogInspectorAgent::class, $agent);
        }
    }

    private function createFactoryInstance(iterable $tools, array $config): LogInspectorAgentFactory
    {
        $reflection = new ReflectionClass(LogInspectorAgentFactory::class);
        $constructor = $reflection->getConstructor();
        $constructor->setAccessible(true);
        
        $factory = $reflection->newInstanceWithoutConstructor();
        $constructor->invoke($factory, $tools, $config);
        
        return $factory;
    }
}