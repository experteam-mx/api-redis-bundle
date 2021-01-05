<?php

namespace Experteam\ApiRedisBundle\Service\RedisTransport;

interface RedisTransportInterface
{
    /**
     * @param $object
     */
    public function postChangeEntity($object);

    /**
     * @param array $entitiesWithPostChange
     */
    public function loadEntitiesWithPostChange(array $entitiesWithPostChange);

}