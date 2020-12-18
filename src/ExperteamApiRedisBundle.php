<?php

namespace Experteam\ApiRedisBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;

class ExperteamApiRedisBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(DoctrineOrmMappingsPass::createAnnotationMappingDriver(
            ['Experteam\ApiRedisBundle\Entity'],
            [realpath(__DIR__.'/Entity')]
        ));
    }
}