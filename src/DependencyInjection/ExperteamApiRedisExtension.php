<?php

namespace Experteam\ApiRedisBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class ExperteamApiRedisExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $config = (new Processor())->processConfiguration(new Configuration(), $configs);
        $serializeGroups = $config['serialize_groups'];
        $logger = $config['logger'];
        $configEntities = array_map(function($cfg) use($serializeGroups, $logger) {
            $cfg['serialize_groups'] = $cfg['serialize_groups'] ?? $serializeGroups;
            $cfg['logger'] = $cfg['logger'] ?? $logger;
            return $cfg;
        }, $config['entities'] ?? []);

        $container->setParameter('experteam_api_redis.entities', $configEntities);
    }

}