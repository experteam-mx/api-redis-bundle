<?php

namespace Experteam\ApiRedisBundle\Service\RedisTransport;

use DateTime;
use Exception;

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

    /**
     * @param array $entityClasses
     */
    public function processAllSaves(array $entityClasses = []);

    /**
     * @param array $entityClasses
     * @param DateTime|null $updatedFrom
     * @param array $filters
     * @throws Exception
     */
    public function processAllMessages(array $entityClasses = [], DateTime $updatedFrom = null, array $filters = []);

}