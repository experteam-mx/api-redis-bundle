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
     * @param string $dateFrom
     * @param string|null $dateTo
     * @param array $entities
     * @param array $ids
     */
    public function restoreMessages(string $dateFrom, string $dateTo = null, array $entities = [], array $ids = []);
}