<?php

namespace Experteam\ApiRedisBundle\Util;

use Experteam\ApiRedisBundle\Service\RedisClient\RedisClientInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Common
{
    /**
     * @param RedisClientInterface $redisClient
     * @param string $key
     * @param $field
     * @param bool $throwException
     * @param array $checkFields
     * @return mixed
     */
    public static function getResourceFromRedis(RedisClientInterface $redisClient, string $key, $field, bool $throwException = false, array $checkFields = [])
    {
        $resource = $redisClient->hget($key, $field);

        if ($throwException) {
            if (!isset($resource)) {
                throw new BadRequestHttpException("Not found resource '$key' with id '$field' in redis.");
            }

            foreach ($checkFields as $checkField) {
                if (!isset($resource->$checkField)) {
                    throw new BadRequestHttpException("Resource '$key' with id '$field' retrieved from redis is incorrectly formatted.");
                }
            }
        }

        return $resource;
    }
}
