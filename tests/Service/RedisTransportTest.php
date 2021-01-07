<?php

namespace Experteam\ApiRedisBundle\Tests\Service;

use Experteam\ApiRedisBundle\Service\RedisTransport\RedisTransport;
use Experteam\ApiRedisBundle\Tests\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RedisTransportTest extends KernelTestCase
{

    /**
     * @var RedisTransport
     */
    private $redisTransport;

    public function setUp()
    {
        $kernel = self::bootKernel();
        $this->redisTransport = $kernel->getContainer()->get('api_redis.transport');
    }

    protected static function getKernelClass()
    {
        return TestKernel::class;
    }

    /** @test */
    public function redisTransport()
    {
        dump($this->redisTransport);
        $this->assertEquals(1, 1);
    }
}