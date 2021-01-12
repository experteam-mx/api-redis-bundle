<?php

namespace Experteam\ApiRedisBundle\Service\RedisTransport;

interface RedisTransportInterface
{
    /**
     * @return array
     */
    public function getEntitiesConfig();

    /**
     * @param $object
     */
    public function processEntity($object);

}