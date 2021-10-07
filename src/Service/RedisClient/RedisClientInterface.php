<?php

namespace Experteam\ApiRedisBundle\Service\RedisClient;

interface RedisClientInterface
{
    /**
     * @param string $key
     * @param $data
     * @param bool $serialize
     * @param string[]|null $serializeGroups
     * @return mixed
     */
    public function set(string $key, $data, bool $serialize = true, array $serializeGroups = null);

    /**
     * @param string $key
     * @param null $objectType
     * @param false $assoc
     * @return mixed
     */
    public function get(string $key, $objectType = null, $assoc = false);

    /**
     * @param string $key
     * @param $field
     * @param $data
     * @param bool $serialize
     * @param string[]|null $serializeGroups
     * @return mixed
     */
    public function hset(string $key, $field, $data, bool $serialize = true, array $serializeGroups = null);

    /**
     * @param string $key
     * @param $field
     * @param null $objectType
     * @param false $assoc
     * @return mixed
     */
    public function hget(string $key, $field, $objectType = null, $assoc = false);

    /**
     * @param string $key
     * @param array $data
     * @return mixed
     */
    public function hmset(string $key, array $data);

    /**
     * @param string $key
     * @return mixed
     */
    public function hgetall(string $key);

    /**
     * @param string $key
     * @param int $seconds
     * @return mixed
     */
    public function expire(string $key, int $seconds);

    /**
     * @param string $key
     * @param int $seconds
     * @param $data
     * @param bool $serialize
     * @param string[]|null $serializeGroups
     * @return mixed
     */
    public function setex(string $key, int $seconds, $data, bool $serialize = true, array $serializeGroups = null);

    /**
     * @param array|string $keys
     */
    public function del($keys);

    /**
     * @param string|null $pattern
     */
    public function keys(string $pattern = null);

    /**
     * @param string $key
     * @param array $fields
     */
    public function hdel(string $key, array $fields);
}