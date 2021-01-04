<?php

namespace Experteam\ApiRedisBundle\Service\PostChange;

interface PostChangeInterface
{
    /**
     * @param $object
     */
    public function onPersist($object);

    /**
     * @param array $entitiesWithPostChange
     */
    public function loadEntitiesWithPostChange(array $entitiesWithPostChange);

}