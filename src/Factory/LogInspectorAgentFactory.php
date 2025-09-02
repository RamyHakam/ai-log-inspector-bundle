<?php

namespace Hakam\AiLogInspectorBundle\Factory;

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformFactory;
use Hakam\AiLogInspector\Tool\LogInspectorToolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final  readonly class LogInspectorAgentFactory
{
    private function __construct(
        #[AutowireIterator(LogInspectorToolInterface::class)]
        private iterable $tools,
        #[Autowire('%hakam_ai_log_inspector.ai_platform%')]
        private array $config,
    )
    {
    }

    public  function create(): object
    {
        $platform = LogDocumentPlatformFactory::create($this->config);
        return new LogInspectorAgent($platform, $this->tools);
    }
}
