<?php

namespace Hakam\AiLogInspectorBundle\DependencyInjection;

use Hakam\AiLogInspector\Agent\LogInspectorAgent;
use Hakam\AiLogInspector\Platform\LogDocumentPlatformFactory;
use Hakam\AiLogInspectorBundle\Factory\LogInspectorAgentFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Config\FileLocator;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

class AiLogInspectorExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container) : void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $container->registerAttributeForAutoconfiguration(AsTool::class, static function (
            Definition $definition,
            AsTool $attribute
        ): void {
            $definition->addTag('hakam_ai_log_inspector.tool', [
                'name' => $attribute->name,
                'description' => $attribute->description,
                'method' => $attribute->method
            ]);
        });

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Set configuration parameters
        $container->setParameter('hakam_ai_log_inspector.ai_platform', $config['ai_platform']);
        $container->setParameter('hakam_ai_log_inspector.vector_store', $config['vector_store']);
        $container->setParameter('hakam_ai_log_inspector.log_sources', $config['log_sources']);

        $factoryDefinition = new Definition(LogInspectorAgentFactory::class);
        $factoryDefinition->setPublic(true);
        $container->setDefinition('hakam_ai_log_inspector.agent_factory', $factoryDefinition);
    }
}
