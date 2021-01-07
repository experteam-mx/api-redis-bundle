<?php

namespace Experteam\ApiRedisBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ServiceTest extends KernelTestCase
{
    public function redisTransport()
    {
        self::bootKernel();
        $redisTransport = self::$kernel->getContainer()->get('api_redis.transport');
        dump($redisTransport);
    }
}