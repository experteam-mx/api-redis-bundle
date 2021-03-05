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

    /**
     * @param string $dateTime
     */
    public function restoreMessages(string $dateTime);
}