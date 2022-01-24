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
        $logger = $config['elk_logger'];
        $withTranslations = $config['with_translations'];

        $configEntities = array_map(function($cfg) use($serializeGroups, $logger) {
            $cfg['serialize_groups'] = $cfg['serialize_groups'] ?? $serializeGroups;
            $cfg['elk_logger'] = $cfg['elk_logger'] ?? $logger;
            return $cfg;
        }, $config['entities'] ?? []);
        $container->setParameter('experteam_api_redis.entities', $configEntities);

        $configEntitiesV2 = array_map(function($cfg) use($serializeGroups, $logger, $withTranslations) {
            $cfg['serialize_groups'] = $cfg['serialize_groups'] ?? $serializeGroups;
            $cfg['elk_logger'] = $cfg['elk_logger'] ?? $logger;
            $cfg['with_translations'] = $cfg['with_translations'] ?? $withTranslations;
            return $cfg;
        }, $config['entities_v2'] ?? []);
        $container->setParameter('experteam_api_redis.entities.v2', $configEntitiesV2);

        if ($container->hasParameter('experteam_api_base.timezone') && isset($config['timezone'])) {
            $container->setParameter('experteam_api_base.timezone', array_merge(
                $container->getParameter('experteam_api_base.timezone'),
                ['redis' => $config['timezone']]
            ));
        }
    }

}