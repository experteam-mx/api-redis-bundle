<?php


namespace Experteam\ApiRedisBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('experteam_api_redis');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('serialize_groups')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('save')->defaultValue('read')->end()
                        ->scalarNode('message')->defaultValue('read')->end()
                    ->end()
                ->end()
                ->arrayNode('elk_logger')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('save')->defaultValue(false)->end()
                        ->booleanNode('message')->defaultValue(false)->end()
                    ->end()
                ->end()
                ->arrayNode('entities')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('prefix')->isRequired()->end()
                            ->booleanNode('save')->isRequired()->end()
                            ->scalarNode('save_method')->defaultValue('getId')->end()
                            ->booleanNode('message')->isRequired()->end()
                            ->scalarNode('message_class')->defaultValue('')->end()
                            ->arrayNode('serialize_groups')
                                ->children()
                                    ->scalarNode('save')->defaultValue('read')->end()
                                    ->scalarNode('message')->defaultValue('read')->end()
                                ->end()
                            ->end()
                            ->arrayNode('elk_logger')
                                ->children()
                                    ->booleanNode('save')->defaultValue(true)->end()
                                    ->booleanNode('message')->defaultValue(true)->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}