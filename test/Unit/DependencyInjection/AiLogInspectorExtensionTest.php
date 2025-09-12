<?php

namespace Hakam\AiLogInspectorBundle\Tests\Unit\DependencyInjection;

use Hakam\AiLogInspector\Platform\LogDocumentPlatformFactory;
use Hakam\AiLogInspector\Store\VectorLogDocumentStore;
use Hakam\AiLogInspector\Tool\LogInspectorToolInterface;
use Hakam\AiLogInspector\Vectorizer\VectorizerFactory;
use Hakam\AiLogInspector\Indexer\VectorLogDocumentIndexer;
use Hakam\AiLogInspector\Service\LogProcessorService;
use Hakam\AiLogInspectorBundle\DependencyInjection\AiLogInspectorExtension;
use Hakam\AiLogInspectorBundle\Factory\LogInspectorAgentFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Store\Bridge\Local\InMemoryStore;
use Symfony\AI\Store\Bridge\Chroma\ChromaStore;

class AiLogInspectorExtensionTest extends TestCase
{
    private AiLogInspectorExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new AiLogInspectorExtension();
        $this->container = new ContainerBuilder();
    }

    public function testLoadWithEmptyConfiguration(): void
    {
        $this->extension->load([], $this->container);

        // Test that parameters are set with defaults
        $this->assertTrue($this->container->hasParameter('hakam_ai_log_inspector.config'));
        $this->assertTrue($this->container->hasParameter('hakam_ai_log_inspector.ai_platform'));
        $this->assertTrue($this->container->hasParameter('hakam_ai_log_inspector.vector_store'));
        $this->assertTrue($this->container->hasParameter('hakam_ai_log_inspector.log_sources'));
        $this->assertTrue($this->container->hasParameter('hakam_ai_log_inspector.indexing'));
        $this->assertTrue($this->container->hasParameter('hakam_ai_log_inspector.system_prompt'));

        // Test default parameter values
        $aiPlatform = $this->container->getParameter('hakam_ai_log_inspector.ai_platform');
        $this->assertEquals('ollama', $aiPlatform['provider']);
        $this->assertEquals('llama3.2:1b', $aiPlatform['model']['name']);
        
        $vectorStore = $this->container->getParameter('hakam_ai_log_inspector.vector_store');
        $this->assertEquals('memory', $vectorStore['provider']);
        
        $logSources = $this->container->getParameter('hakam_ai_log_inspector.log_sources');
        $this->assertCount(1, $logSources);
        $this->assertEquals('%kernel.logs_dir%', $logSources[0]['path']);
    }

    public function testLoadWithCustomConfiguration(): void
    {
        $config = [
            'ai_platform' => [
                'provider' => 'openai',
                'api_key' => 'sk-test-key',
                'model' => [
                    'name' => 'gpt-4',
                    'options' => ['temperature' => 0.7]
                ]
            ],
            'vector_store' => [
                'provider' => 'chroma',
                'connection_string' => 'http://localhost:8000'
            ],
            'log_sources' => [
                ['path' => '/var/log/app', 'pattern' => '*.log']
            ],
            'system_prompt' => 'Custom prompt'
        ];

        $this->extension->load([$config], $this->container);

        // Verify custom parameters
        $aiPlatform = $this->container->getParameter('hakam_ai_log_inspector.ai_platform');
        $this->assertEquals('openai', $aiPlatform['provider']);
        $this->assertEquals('sk-test-key', $aiPlatform['api_key']);
        $this->assertEquals('gpt-4', $aiPlatform['model']['name']);
        
        $vectorStore = $this->container->getParameter('hakam_ai_log_inspector.vector_store');
        $this->assertEquals('chroma', $vectorStore['provider']);
        $this->assertEquals('http://localhost:8000', $vectorStore['connection_string']);
        
        $systemPrompt = $this->container->getParameter('hakam_ai_log_inspector.system_prompt');
        $this->assertEquals('Custom prompt', $systemPrompt);
    }

    public function testServicesAreRegistered(): void
    {
        $this->extension->load([], $this->container);

        // Test that core services are registered
        $this->assertTrue($this->container->hasDefinition('hakam_ai_log_inspector.platform'));
        $this->assertTrue($this->container->hasDefinition('hakam_ai_log_inspector.ai_store'));
        $this->assertTrue($this->container->hasDefinition('hakam_ai_log_inspector.vector_store'));
        $this->assertTrue($this->container->hasDefinition('hakam_ai_log_inspector.vectorizer'));
        $this->assertTrue($this->container->hasDefinition('hakam_ai_log_inspector.indexer'));
        $this->assertTrue($this->container->hasDefinition('hakam_ai_log_inspector.processor'));
        $this->assertTrue($this->container->hasDefinition('hakam_ai_log_inspector.agent_factory'));
        $this->assertTrue($this->container->hasDefinition('hakam_ai_log_inspector.agent'));
    }

    public function testPlatformServiceConfiguration(): void
    {
        $this->extension->load([], $this->container);

        $platformDefinition = $this->container->getDefinition('hakam_ai_log_inspector.platform');
        $this->assertEquals([LogDocumentPlatformFactory::class, 'create'], $platformDefinition->getFactory());
        $this->assertFalse($platformDefinition->isPublic());
    }

    public function testMemoryStoreConfiguration(): void
    {
        $config = [
            'vector_store' => [
                'provider' => 'memory'
            ]
        ];

        $this->extension->load([$config], $this->container);

        $storeDefinition = $this->container->getDefinition('hakam_ai_log_inspector.ai_store');
        $this->assertEquals(InMemoryStore::class, $storeDefinition->getClass());
        $this->assertEmpty($storeDefinition->getArguments());
    }

    public function testChromaStoreConfiguration(): void
    {
        $config = [
            'vector_store' => [
                'provider' => 'chroma',
                'connection_string' => 'http://localhost:8000',
                'options' => ['collection_name' => 'test_logs']
            ]
        ];

        $this->extension->load([$config], $this->container);

        $storeDefinition = $this->container->getDefinition('hakam_ai_log_inspector.ai_store');
        $this->assertEquals(ChromaStore::class, $storeDefinition->getClass());
        $arguments = $storeDefinition->getArguments();
        $this->assertEquals('http://localhost:8000', $arguments[0]);
        $this->assertIsArray($arguments[1]);
        $this->assertEquals('test_logs', $arguments[1]['collection_name']);
        // With variablePrototype, custom options don't get merged with defaults automatically
        // So we only check what was explicitly provided
    }

    public function testVectorStoreWrapperConfiguration(): void
    {
        $this->extension->load([], $this->container);

        $vectorStoreDefinition = $this->container->getDefinition('hakam_ai_log_inspector.vector_store');
        $this->assertEquals(VectorLogDocumentStore::class, $vectorStoreDefinition->getClass());
        
        $arguments = $vectorStoreDefinition->getArguments();
        $this->assertCount(1, $arguments);
    }

    public function testVectorizerConfiguration(): void
    {
        $this->extension->load([], $this->container);

        $vectorizerDefinition = $this->container->getDefinition('hakam_ai_log_inspector.vectorizer');
        $this->assertEquals([VectorizerFactory::class, 'create'], $vectorizerDefinition->getFactory());
    }

    public function testIndexerConfiguration(): void
    {
        $this->extension->load([], $this->container);

        $indexerDefinition = $this->container->getDefinition('hakam_ai_log_inspector.indexer');
        $this->assertEquals(VectorLogDocumentIndexer::class, $indexerDefinition->getClass());
        
        $arguments = $indexerDefinition->getArguments();
        $this->assertCount(2, $arguments);
    }

    public function testProcessorConfiguration(): void
    {
        $this->extension->load([], $this->container);

        $processorDefinition = $this->container->getDefinition('hakam_ai_log_inspector.processor');
        $this->assertEquals(LogProcessorService::class, $processorDefinition->getClass());
    }

    public function testAgentFactoryConfiguration(): void
    {
        $this->extension->load([], $this->container);

        $factoryDefinition = $this->container->getDefinition('hakam_ai_log_inspector.agent_factory');
        $this->assertEquals(LogInspectorAgentFactory::class, $factoryDefinition->getClass());
        $this->assertTrue($factoryDefinition->isPublic());
        
        $arguments = $factoryDefinition->getArguments();
        $this->assertCount(4, $arguments);
    }

    public function testAgentConfiguration(): void
    {
        $this->extension->load([], $this->container);

        $agentDefinition = $this->container->getDefinition('hakam_ai_log_inspector.agent');
        $this->assertTrue($agentDefinition->isPublic());
        
        $factory = $agentDefinition->getFactory();
        $this->assertNotNull($factory);
        $this->assertEquals('create', $factory[1]);
    }

    public function testToolAutoconfiguration(): void
    {
        $this->extension->load([], $this->container);

        // Test that LogInspectorToolInterface is registered for autoconfiguration
        $autoconfiguredInstanceof = $this->container->getAutoconfiguredInstanceof();
        $this->assertArrayHasKey(LogInspectorToolInterface::class, $autoconfiguredInstanceof);
        
        $toolInterface = $autoconfiguredInstanceof[LogInspectorToolInterface::class];
        $tags = $toolInterface->getTags();
        
        // Debug: log actual tag structure to understand what's happening
        // The expected structure should be: [['name' => 'tag_name']] or ['tag_name' => []]
        $foundExpectedTag = false;
        foreach ($tags as $tagName => $tagData) {
            if ($tagName === 'hakam_ai_log_inspector.tool' || 
                (is_array($tagData) && isset($tagData['name']) && $tagData['name'] === 'hakam_ai_log_inspector.tool')) {
                $foundExpectedTag = true;
                break;
            }
        }
        
        // If we didn't find it in the expected format, check for simple array format
        if (!$foundExpectedTag) {
            foreach ($tags as $tag) {
                if (is_array($tag) && in_array('hakam_ai_log_inspector.tool', $tag, true)) {
                    $foundExpectedTag = true;
                    break;
                }
            }
        }
        
        $this->assertTrue($foundExpectedTag, 'Tool interface should be tagged with hakam_ai_log_inspector.tool. Found tags: ' . print_r($tags, true));
        
        // Test that attributes are registered for autoconfiguration by checking if the extension was loaded correctly
        // The AsTool attribute registration happens in the extension load method
        $this->assertTrue($this->container->hasDefinition('hakam_ai_log_inspector.platform'));
    }

    public function testExternalStoreWithoutConnectionString(): void
    {
        $config = [
            'vector_store' => [
                'provider' => 'chroma'
                // No connection_string provided
            ]
        ];

        $this->extension->load([$config], $this->container);

        $storeDefinition = $this->container->getDefinition('hakam_ai_log_inspector.ai_store');
        $this->assertEquals(ChromaStore::class, $storeDefinition->getClass());
        // Should not have arguments when no connection string
        $this->assertEmpty($storeDefinition->getArguments());
    }

    public function testPineconeStoreConfiguration(): void
    {
        $config = [
            'vector_store' => [
                'provider' => 'pinecone',
                'connection_string' => 'pinecone-api-key',
                'options' => ['environment' => 'us-east1-gcp']
            ]
        ];

        $this->extension->load([$config], $this->container);

        $storeDefinition = $this->container->getDefinition('hakam_ai_log_inspector.ai_store');
        $this->assertEquals('Symfony\AI\Store\Bridge\Pinecone\PineconeStore', $storeDefinition->getClass());
        $this->assertEquals([
            'pinecone-api-key',
            ['environment' => 'us-east1-gcp']
        ], $storeDefinition->getArguments());
    }

    public function testWeaviateStoreConfiguration(): void
    {
        $config = [
            'vector_store' => [
                'provider' => 'weaviate',
                'connection_string' => 'http://weaviate:8080'
            ]
        ];

        $this->extension->load([$config], $this->container);

        $storeDefinition = $this->container->getDefinition('hakam_ai_log_inspector.ai_store');
        $this->assertEquals('Symfony\AI\Store\Bridge\Weaviate\WeaviateStore', $storeDefinition->getClass());
    }

    public function testUnknownStoreProviderDefaultsToMemory(): void
    {
        $config = [
            'vector_store' => [
                'provider' => 'unknown_provider'
            ]
        ];

        $this->extension->load([$config], $this->container);

        $storeDefinition = $this->container->getDefinition('hakam_ai_log_inspector.ai_store');
        $this->assertEquals(InMemoryStore::class, $storeDefinition->getClass());
    }

    public function testMultipleConfigurations(): void
    {
        $config1 = [
            'ai_platform' => [
                'provider' => 'ollama'
            ]
        ];

        $config2 = [
            'ai_platform' => [
                'provider' => 'openai',
                'api_key' => 'sk-test-key'
            ]
        ];

        $this->extension->load([$config1, $config2], $this->container);

        // Second config should override first
        $aiPlatform = $this->container->getParameter('hakam_ai_log_inspector.ai_platform');
        $this->assertEquals('openai', $aiPlatform['provider']);
        $this->assertEquals('sk-test-key', $aiPlatform['api_key']);
    }

    public function testServiceDefinitionsAreNotPublic(): void
    {
        $this->extension->load([], $this->container);

        // Most services should not be public except agent and factory
        $this->assertFalse($this->container->getDefinition('hakam_ai_log_inspector.platform')->isPublic());
        $this->assertFalse($this->container->getDefinition('hakam_ai_log_inspector.ai_store')->isPublic());
        $this->assertFalse($this->container->getDefinition('hakam_ai_log_inspector.vector_store')->isPublic());
        $this->assertFalse($this->container->getDefinition('hakam_ai_log_inspector.vectorizer')->isPublic());
        $this->assertFalse($this->container->getDefinition('hakam_ai_log_inspector.indexer')->isPublic());
        $this->assertFalse($this->container->getDefinition('hakam_ai_log_inspector.processor')->isPublic());
        
        // These should be public
        $this->assertTrue($this->container->getDefinition('hakam_ai_log_inspector.agent_factory')->isPublic());
        $this->assertTrue($this->container->getDefinition('hakam_ai_log_inspector.agent')->isPublic());
    }

    public function testParameterValues(): void
    {
        $config = [
            'ai_platform' => [
                'provider' => 'anthropic',
                'api_key' => 'sk-ant-test',
                'model' => [
                    'name' => 'claude-3-sonnet',
                    'capabilities' => ['text_generation'],
                    'options' => ['temperature' => 0.5, 'max_tokens' => 4000]
                ]
            ],
            'vector_store' => [
                'provider' => 'pinecone',
                'connection_string' => 'test-key',
                'options' => ['environment' => 'test']
            ],
            'log_sources' => [
                ['path' => '/app/logs', 'pattern' => '*.log', 'recursive' => true],
                ['path' => '/var/log', 'pattern' => 'error*.log', 'recursive' => false]
            ],
            'indexing' => [
                'auto_index' => false,
                'batch_size' => 500,
                'excluded_patterns' => ['/health', '/status']
            ],
            'system_prompt' => 'Test system prompt'
        ];

        $this->extension->load([$config], $this->container);

        // Verify all parameters are correctly set
        $fullConfig = $this->container->getParameter('hakam_ai_log_inspector.config');
        $this->assertEquals($config['ai_platform']['provider'], $fullConfig['ai_platform']['provider']);
        $this->assertEquals($config['ai_platform']['api_key'], $fullConfig['ai_platform']['api_key']);
        $this->assertEquals($config['vector_store']['provider'], $fullConfig['vector_store']['provider']);
        $this->assertCount(2, $fullConfig['log_sources']);
        $this->assertFalse($fullConfig['indexing']['auto_index']);
        $this->assertEquals('Test system prompt', $fullConfig['system_prompt']);

        // Verify individual parameters - note that the config will include defaults
        $aiPlatformParam = $this->container->getParameter('hakam_ai_log_inspector.ai_platform');
        $this->assertEquals($config['ai_platform']['provider'], $aiPlatformParam['provider']);
        $this->assertEquals($config['ai_platform']['api_key'], $aiPlatformParam['api_key']);
        $this->assertEquals($config['ai_platform']['model']['name'], $aiPlatformParam['model']['name']);
        $this->assertEquals($config['vector_store'], $this->container->getParameter('hakam_ai_log_inspector.vector_store'));
        $this->assertEquals($config['log_sources'], $this->container->getParameter('hakam_ai_log_inspector.log_sources'));
        $this->assertEquals($config['indexing'], $this->container->getParameter('hakam_ai_log_inspector.indexing'));
        $this->assertEquals($config['system_prompt'], $this->container->getParameter('hakam_ai_log_inspector.system_prompt'));
    }
}
