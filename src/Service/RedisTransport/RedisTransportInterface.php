<?php

namespace Experteam\ApiRedisBundle\Service\RedisTransport;

interface RedisTransportInterface
{
    /**
     * @return array
     */
    public function getEntitiesConfig(): array;

    /**
     * @param object $object
     */
    public function processEntity(object $object);

    public function restoreData();
}