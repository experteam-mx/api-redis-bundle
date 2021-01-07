<?php

namespace Experteam\ApiRedisBundle\Tests;

use Experteam\ApiRedisBundle\ExperteamApiRedisBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new ExperteamApiRedisBundle()
        ];
    }

    protected function configureRoutes(RoutingConfigurator $routes)
    {
        //$routes->import(__DIR__.'/../../src/Resources/config/routes.xml')->prefix('/api');
    }

    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
    {
        $loader->load($this->getProjectDir().'/src/Resources/config/services.xml', 'glob');
        $loader->load($this->getProjectDir().'/config/packages/api_redis.yaml', 'glob');
    }

}