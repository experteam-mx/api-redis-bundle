<?php

namespace Experteam\ApiRedisBundle\Service\RedisTransportV2;

interface RedisTransportV2Interface
{
    public function processEntity(object $object): void;

    public function getEntitiesConfig(): array;

    public function restoreData(array $entities = []): ?array;

    public function restoreMessages(string $dateFrom, ?string $dateTo = null, array $entities = [], array $ids = []): void;

    public function restoreStreamCompute(?string $dateFrom = null, ?string $dateTo = null, array $entities = [], array $ids = []): void;

    public function streamCompute(array $entityConfig, object $object): void;
}
