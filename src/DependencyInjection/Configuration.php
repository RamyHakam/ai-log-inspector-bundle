<?php

namespace Hakam\AiLogInspectorBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('hakam_ai_log_inspector');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('ai_platform')
                    ->children()
                        ->scalarNode('provider')->defaultValue('openai')->end()
                        ->scalarNode('api_key')->isRequired()->end()
                        ->arrayNode('model')
                            ->children()
                                ->scalarNode('name')->defaultValue('gpt-4')->end()
                                ->arrayNode('capabilities')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(['text_generation', 'embeddings'])
                                ->end()
                                ->arrayNode('options')
                                    ->children()
                                        ->floatNode('temperature')->defaultValue(0.7)->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('vector_store')
                    ->children()
                        ->scalarNode('provider')->defaultValue('chroma')->end()
                        ->scalarNode('connection_string')->isRequired()->end()
                    ->end()
                ->end()
                ->arrayNode('log_sources')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('path')->isRequired()->end()
                            ->scalarNode('pattern')->defaultValue('*.log')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
