<?php

namespace Experteam\ApiRedisBundle\Service\RedisTransportV2;

interface RedisTransportV2Interface
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
     * @return array|null
     */
    public function restoreData(array $entities = []): ?array;

    /**
     * @param string $dateFrom
     * @param string|null $dateTo
     * @param array $entities
     * @param array $ids
     */
    public function restoreMessages(string $dateFrom, string $dateTo = null, array $entities = [], array $ids = []);

    /**
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @param array $entities
     * @param array $ids
     * @return void
     */
    public function restoreStreamCompute(string $dateFrom = null, string $dateTo = null, array $entities = [], array $ids = []);
}
