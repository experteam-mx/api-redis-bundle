<?php

namespace Experteam\ApiRedisBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Experteam\ApiRedisBundle\ExperteamApiRedisBundle;
use Snc\RedisBundle\SncRedisBundle;
use Stof\DoctrineExtensionsBundle\StofDoctrineExtensionsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles()
    {
        $bundles = [
            FrameworkBundle::class,
            DoctrineBundle::class,
            StofDoctrineExtensionsBundle::class,
            SncRedisBundle::class,
            ExperteamApiRedisBundle::class
        ];

        foreach ($bundles as $class) {
            yield new $class();
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes)
    {
        //$routes->import(__DIR__.'/../../src/Resources/config/routes.xml')->prefix('/api');
    }

    protected function configureContainer(ContainerConfigurator $c)
    {
        $c->import('../config/{packages}/*.yaml');
        $c->import("{$this->getProjectDir()}/src/Resources/config/services.xml");
        $c->import("{$this->getProjectDir()}/tests/config/experteam_api_redis.yaml");
        $c->parameters()->set('app.prefix', 'inventories');
    }

}