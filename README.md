# AI Log Inspector Bundle

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-blue.svg)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-%5E6.4%20%7C%7C%20%5E7.0-green.svg)](https://symfony.com/)
[![Status](https://img.shields.io/badge/Status-Experimental-red.svg)](#)


AI-powered Symfony bundle to inspect, analyze, and interact with logs using Large Language Models (LLMs) and Model Context Protocol (MCP) agents.

## Features

- 🤖 **AI-Powered Log Analysis**: Leverage LLMs to understand and analyze your application logs
- 🔍 **Intelligent Log Search**: Vector-based semantic search through log entries
- 📊 **Log Summarization**: Automatically summarize and extract insights from logs
- 🛠️ **Extensible Tool System**: Add custom analysis tools using Symfony's `#[AsTool]` attribute
- ⚙️ **Multiple AI Providers**: Support for OpenAI, Anthropic, Ollama, and more
- 💾 **Vector Storage**: Multiple vector store backends (Chroma, Pinecone, Weaviate)
- 📱 **Console Commands**: CLI interface for log analysis and indexing
- 🔄 **Auto-Indexing**: Automatic log indexing via event listeners

## Installation

Install the bundle using Composer:

```bash
composer require hakam/ai-log-inspector-bundle
```

## Configuration

Create a configuration file at `config/packages/hakam_ai_log_inspector.yaml`:

```yaml
hakam_ai_log_inspector:
    ai_platform:
        provider: 'openai'  # or 'anthropic', 'ollama', etc.
        api_key: '%env(AI_API_KEY)%'
        model:
            name: 'gpt-4'
            capabilities: ['text_generation', 'embeddings']
            options:
                temperature: 0.7

    vector_store:
        provider: 'chroma'  # or 'pinecone', 'weaviate', etc.
        connection_string: '%env(VECTOR_STORE_URL)%'

    log_sources:
        - path: '%kernel.logs_dir%'
          pattern: '*.log'
        - path: '/var/log/application'
          pattern: '*.log'
```

### Environment Variables

Set the required environment variables in your `.env` file:

```env
AI_API_KEY=your_ai_provider_api_key
VECTOR_STORE_URL=your_vector_store_connection_string
```

## Usage

### Console Commands

#### Ask Questions About Your Logs

```bash
php bin/console ai:log-inspector:ask "What errors occurred in the last hour?"
php bin/console ai:log-inspector:ask "Show me all database connection errors"
php bin/console ai:log-inspector:ask "Summarize today's critical issues"
```

#### Index Logs for Analysis

```bash
php bin/console ai:log-inspector:index
```

### Service Integration

#### Using the Agent Factory (Recommended)

```php
use Hakam\AiLogInspectorBundle\Factory\LogInspectorAgentFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class LogAnalysisService
{
    public function __construct(
        #[Autowire(service: 'hakam_ai_log_inspector.agent_factory')]
        private LogInspectorAgentFactory $agentFactory
    ) {}

    public function analyzeError(string $errorMessage): string
    {
        $agent = $this->agentFactory->create();
        return $agent->interact("Analyze this error: {$errorMessage}");
    }

    public function getLogSummary(): string
    {
        $agent = $this->agentFactory->create();
        return $agent->interact("Provide a summary of recent log activity");
    }
}
```

#### Service Configuration

```yaml
# config/services.yaml
services:
    App\Service\LogAnalysisService:
        arguments:
            $agentFactory: '@hakam_ai_log_inspector.agent_factory'
```

### Creating Custom Tools

Create custom analysis tools by implementing `LogInspectorToolInterface` and using the `#[AsTool]` attribute:

```php
<?php

namespace App\Tool;

use Hakam\AiLogInspector\Tool\LogInspectorToolInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'error_pattern_detector',
    description: 'Detects specific error patterns in logs'
)]
class ErrorPatternDetectorTool implements LogInspectorToolInterface
{
    public function __invoke(string $pattern, int $hours = 24): array
    {
        // Search for error patterns in the last N hours
        return [
            'pattern' => $pattern,
            'matches' => $this->searchPattern($pattern, $hours),
            'severity' => $this->calculateSeverity($matches)
        ];
    }

    private function searchPattern(string $pattern, int $hours): array
    {
        // Your pattern detection logic
        return [];
    }

    private function calculateSeverity(array $matches): string
    {
        // Calculate severity based on matches
        return 'medium';
    }
}
```

Tools are automatically discovered and injected into the agent when they implement `LogInspectorToolInterface` and are tagged with `#[AsTool]`.

### Event System

The bundle includes automatic log indexing via event listeners:

- **kernel.terminate**: Indexes logs after request completion
- **console.terminate**: Indexes logs after console command execution

## Configuration Examples

### OpenAI with Chroma

```yaml
hakam_ai_log_inspector:
    ai_platform:
        provider: 'openai'
        api_key: '%env(OPENAI_API_KEY)%'
        model:
            name: 'gpt-4'
            options:
                temperature: 0.3
    vector_store:
        provider: 'chroma'
        connection_string: 'http://localhost:8000'
```

### Anthropic with Pinecone

```yaml
hakam_ai_log_inspector:
    ai_platform:
        provider: 'anthropic'
        api_key: '%env(ANTHROPIC_API_KEY)%'
        model:
            name: 'claude-3-sonnet'
            options:
                temperature: 0.5
    vector_store:
        provider: 'pinecone'
        connection_string: '%env(PINECONE_API_KEY)%'
```

### Local Ollama Setup

```yaml
hakam_ai_log_inspector:
    ai_platform:
        provider: 'ollama'
        api_key: ''  # Not required for local Ollama
        model:
            name: 'llama2'
            options:
                temperature: 0.1
    vector_store:
        provider: 'chroma'
        connection_string: 'http://localhost:8000'
```

## Security & Privacy

- **Data Sanitization**: All logs are automatically sanitized to remove sensitive information
- **Local Processing**: Can run entirely offline with local models (Ollama)
- **Configurable Privacy**: Choose your AI provider and data handling preferences
- **No Data Persistence**: Logs are processed in memory by default

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## License

This bundle is released under the MIT License. See the [LICENSE](LICENSE) file for details.

## Author

**Ramy Hakam** - [pencilsoft1@gmail.com](mailto:pencilsoft1@gmail.com)

## Support

For issues and feature requests, please use the [GitHub issue tracker](https://github.com/hakam/ai-log-inspector-bundle/issues).