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

    /**
     * @param array $entities
     */
    public function restoreData(array $entities = []);

    /**
     * @param string $dateTime
     * @param array $entities
     */
    public function restoreMessages(string $dateTime, array $entities = []);
}