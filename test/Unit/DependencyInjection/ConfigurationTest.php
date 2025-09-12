<?php

namespace Hakam\AiLogInspectorBundle\Tests\Unit\DependencyInjection;

use Hakam\AiLogInspectorBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    private Configuration $configuration;
    private Processor $processor;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
        $this->processor = new Processor();
    }

    public function testEmptyConfiguration(): void
    {
        $config = $this->processor->processConfiguration(
            $this->configuration,
            []
        );

        // Test default values
        $this->assertEquals('ollama', $config['ai_platform']['provider']);
        $this->assertEquals('http://localhost:11434', $config['ai_platform']['host']);
        $this->assertNull($config['ai_platform']['api_key']);
        $this->assertEquals('llama3.2:1b', $config['ai_platform']['model']['name']);
        $this->assertEquals(['text_generation'], $config['ai_platform']['model']['capabilities']);
        $this->assertEquals(['temperature' => 0.3], $config['ai_platform']['model']['options']);
        
        $this->assertEquals('memory', $config['vector_store']['provider']);
        $this->assertNull($config['vector_store']['connection_string']);
        $this->assertEquals(['collection_name' => 'ai_logs', 'dimension' => 384], $config['vector_store']['options']);
        
        $this->assertEquals([['path' => '%kernel.logs_dir%', 'pattern' => '*.log']], $config['log_sources']);
        
        $this->assertTrue($config['indexing']['auto_index']);
        $this->assertEquals(100, $config['indexing']['batch_size']);
        $this->assertEquals(['/health', '/metrics', '/ping'], $config['indexing']['excluded_patterns']);
        
        $this->assertNull($config['system_prompt']);
    }

    public function testOpenAiConfiguration(): void
    {
        $inputConfig = [
            'hakam_ai_log_inspector' => [
                'ai_platform' => [
                    'provider' => 'openai',
                    'api_key' => 'sk-test-key',
                    'model' => [
                        'name' => 'gpt-4',
                        'options' => [
                            'temperature' => 0.7,
                            'max_tokens' => 4000
                        ]
                    ]
                ],
                'vector_store' => [
                    'provider' => 'chroma',
                    'connection_string' => 'http://localhost:8000'
                ]
            ]
        ];

        $config = $this->processor->processConfiguration(
            $this->configuration,
            [$inputConfig['hakam_ai_log_inspector']]
        );

        $this->assertEquals('openai', $config['ai_platform']['provider']);
        $this->assertEquals('sk-test-key', $config['ai_platform']['api_key']);
        $this->assertEquals('gpt-4', $config['ai_platform']['model']['name']);
        $this->assertEquals(0.7, $config['ai_platform']['model']['options']['temperature']);
        $this->assertEquals(4000, $config['ai_platform']['model']['options']['max_tokens']);
        
        $this->assertEquals('chroma', $config['vector_store']['provider']);
        $this->assertEquals('http://localhost:8000', $config['vector_store']['connection_string']);
    }

    public function testAnthropicConfiguration(): void
    {
        $inputConfig = [
            'ai_platform' => [
                'provider' => 'anthropic',
                'api_key' => 'sk-ant-test-key',
                'model' => [
                    'name' => 'claude-3-sonnet',
                    'options' => [
                        'temperature' => 0.5
                    ]
                ]
            ]
        ];

        $config = $this->processor->processConfiguration(
            $this->configuration,
            [$inputConfig]
        );

        $this->assertEquals('anthropic', $config['ai_platform']['provider']);
        $this->assertEquals('sk-ant-test-key', $config['ai_platform']['api_key']);
        $this->assertEquals('claude-3-sonnet', $config['ai_platform']['model']['name']);
        $this->assertEquals(0.5, $config['ai_platform']['model']['options']['temperature']);
    }

    public function testVertexAiConfiguration(): void
    {
        $inputConfig = [
            'ai_platform' => [
                'provider' => 'vertex_ai',
                'location' => 'us-central1',
                'project_id' => 'my-project',
                'model' => [
                    'name' => 'gemini-pro'
                ]
            ]
        ];

        $config = $this->processor->processConfiguration(
            $this->configuration,
            [$inputConfig]
        );

        $this->assertEquals('vertex_ai', $config['ai_platform']['provider']);
        $this->assertEquals('us-central1', $config['ai_platform']['location']);
        $this->assertEquals('my-project', $config['ai_platform']['project_id']);
        $this->assertEquals('gemini-pro', $config['ai_platform']['model']['name']);
    }

    public function testCustomLogSources(): void
    {
        $inputConfig = [
            'log_sources' => [
                [
                    'path' => '/var/log/nginx',
                    'pattern' => 'access*.log',
                    'recursive' => false
                ],
                [
                    'path' => '/var/log/app',
                    'pattern' => 'error*.log'
                ]
            ]
        ];

        $config = $this->processor->processConfiguration(
            $this->configuration,
            [$inputConfig]
        );

        $this->assertCount(2, $config['log_sources']);
        $this->assertEquals('/var/log/nginx', $config['log_sources'][0]['path']);
        $this->assertEquals('access*.log', $config['log_sources'][0]['pattern']);
        $this->assertFalse($config['log_sources'][0]['recursive']);
        
        $this->assertEquals('/var/log/app', $config['log_sources'][1]['path']);
        $this->assertEquals('error*.log', $config['log_sources'][1]['pattern']);
        $this->assertTrue($config['log_sources'][1]['recursive']); // default
    }

    public function testCustomIndexingConfiguration(): void
    {
        $inputConfig = [
            'indexing' => [
                'auto_index' => false,
                'batch_size' => 500,
                'excluded_patterns' => ['/status', '/ready']
            ]
        ];

        $config = $this->processor->processConfiguration(
            $this->configuration,
            [$inputConfig]
        );

        $this->assertFalse($config['indexing']['auto_index']);
        $this->assertEquals(500, $config['indexing']['batch_size']);
        $this->assertEquals(['/status', '/ready'], $config['indexing']['excluded_patterns']);
    }

    public function testPineconeVectorStore(): void
    {
        $inputConfig = [
            'vector_store' => [
                'provider' => 'pinecone',
                'connection_string' => 'pinecone-api-key',
                'options' => [
                    'collection_name' => 'my_logs',
                    'dimension' => 1536
                ]
            ]
        ];

        $config = $this->processor->processConfiguration(
            $this->configuration,
            [$inputConfig]
        );

        $this->assertEquals('pinecone', $config['vector_store']['provider']);
        $this->assertEquals('pinecone-api-key', $config['vector_store']['connection_string']);
        $this->assertEquals('my_logs', $config['vector_store']['options']['collection_name']);
        $this->assertEquals(1536, $config['vector_store']['options']['dimension']);
    }

    public function testWeaviateVectorStore(): void
    {
        $inputConfig = [
            'vector_store' => [
                'provider' => 'weaviate',
                'connection_string' => 'http://weaviate:8080',
                'options' => [
                    'collection_name' => 'ApplicationLogs'
                ]
            ]
        ];

        $config = $this->processor->processConfiguration(
            $this->configuration,
            [$inputConfig]
        );

        $this->assertEquals('weaviate', $config['vector_store']['provider']);
        $this->assertEquals('http://weaviate:8080', $config['vector_store']['connection_string']);
        $this->assertEquals('ApplicationLogs', $config['vector_store']['options']['collection_name']);
    }

    public function testSystemPromptConfiguration(): void
    {
        $inputConfig = [
            'system_prompt' => 'You are a specialized log analyzer for e-commerce applications.'
        ];

        $config = $this->processor->processConfiguration(
            $this->configuration,
            [$inputConfig]
        );

        $this->assertEquals('You are a specialized log analyzer for e-commerce applications.', $config['system_prompt']);
    }

    public function testClientOptionsConfiguration(): void
    {
        $inputConfig = [
            'ai_platform' => [
                'client_options' => [
                    'timeout' => 30,
                    'headers' => [
                        'User-Agent' => 'MyApp/1.0'
                    ]
                ]
            ]
        ];

        $config = $this->processor->processConfiguration(
            $this->configuration,
            [$inputConfig]
        );

        $this->assertEquals(30, $config['ai_platform']['client_options']['timeout']);
        $this->assertEquals('MyApp/1.0', $config['ai_platform']['client_options']['headers']['User-Agent']);
    }

    public function testFullCustomConfiguration(): void
    {
        $inputConfig = [
            'ai_platform' => [
                'provider' => 'openai',
                'api_key' => 'sk-test-key',
                'client_options' => [
                    'timeout' => 60
                ],
                'model' => [
                    'name' => 'gpt-4-turbo',
                    'capabilities' => ['text_generation', 'embeddings'],
                    'options' => [
                        'temperature' => 0.2,
                        'max_tokens' => 8000,
                        'top_p' => 0.9
                    ]
                ]
            ],
            'vector_store' => [
                'provider' => 'chroma',
                'connection_string' => 'http://chroma:8000',
                'options' => [
                    'collection_name' => 'production_logs',
                    'dimension' => 1536
                ]
            ],
            'log_sources' => [
                [
                    'path' => '/var/log/app',
                    'pattern' => '*.log',
                    'recursive' => true
                ],
                [
                    'path' => '/var/log/nginx',
                    'pattern' => 'access*.log',
                    'recursive' => false
                ]
            ],
            'indexing' => [
                'auto_index' => true,
                'batch_size' => 200,
                'excluded_patterns' => ['/health', '/ping', '/metrics', '/status']
            ],
            'system_prompt' => 'You are an expert log analyst for production systems.'
        ];

        $config = $this->processor->processConfiguration(
            $this->configuration,
            [$inputConfig]
        );

        // Verify all configuration is preserved
        $this->assertEquals('openai', $config['ai_platform']['provider']);
        $this->assertEquals('sk-test-key', $config['ai_platform']['api_key']);
        $this->assertEquals(60, $config['ai_platform']['client_options']['timeout']);
        $this->assertEquals('gpt-4-turbo', $config['ai_platform']['model']['name']);
        $this->assertEquals(['text_generation', 'embeddings'], $config['ai_platform']['model']['capabilities']);
        $this->assertEquals(0.2, $config['ai_platform']['model']['options']['temperature']);
        $this->assertEquals(8000, $config['ai_platform']['model']['options']['max_tokens']);
        $this->assertEquals(0.9, $config['ai_platform']['model']['options']['top_p']);
        
        $this->assertEquals('chroma', $config['vector_store']['provider']);
        $this->assertEquals('http://chroma:8000', $config['vector_store']['connection_string']);
        $this->assertEquals('production_logs', $config['vector_store']['options']['collection_name']);
        $this->assertEquals(1536, $config['vector_store']['options']['dimension']);
        
        $this->assertCount(2, $config['log_sources']);
        $this->assertEquals('/var/log/app', $config['log_sources'][0]['path']);
        $this->assertTrue($config['log_sources'][0]['recursive']);
        $this->assertEquals('/var/log/nginx', $config['log_sources'][1]['path']);
        $this->assertFalse($config['log_sources'][1]['recursive']);
        
        $this->assertTrue($config['indexing']['auto_index']);
        $this->assertEquals(200, $config['indexing']['batch_size']);
        $this->assertEquals(['/health', '/ping', '/metrics', '/status'], $config['indexing']['excluded_patterns']);
        
        $this->assertEquals('You are an expert log analyst for production systems.', $config['system_prompt']);
    }
}
