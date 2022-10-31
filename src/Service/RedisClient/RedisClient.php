<?php

namespace Experteam\ApiRedisBundle\Service\RedisClient;

use Redis;
use ReflectionMethod;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

class RedisClient implements RedisClientInterface
{
    /**
     * @var Redis
     */
    private $redis;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param Redis $redis
     * @param SerializerInterface $serializer
     */
    public function __construct(Redis $redis, SerializerInterface $serializer)
    {
        $this->redis = $redis;
        $this->serializer = $serializer;
    }

    /**
     * @param string $value
     * @param null $objectType
     * @param false $assoc
     * @return array|mixed|object
     */
    private function deserialize(string $value, $objectType = null, $assoc = false)
    {
        if (is_null($objectType)) {
            $value = json_decode($value, $assoc);
        } else {
            $value = $this->serializer->deserialize($value, $objectType, 'json');
        }

        return $value;
    }

    /**
     * @param string $key
     * @param $data
     * @param bool $serialize
     * @param string[]|null $serializeGroups
     */
    public function set(string $key, $data, bool $serialize = true, array $serializeGroups = null)
    {
        $serializeGroups = !is_null($serializeGroups) ? ['groups' => $serializeGroups] : [];
        $data = $serialize ? $this->serializer->serialize($data, 'json', $serializeGroups) : $data;
        $this->redis->set($key, $data);
    }

    /**
     * @param string $key
     * @param null $objectType
     * @param false $assoc
     * @return array|mixed|object|null
     */
    public function get(string $key, $objectType = null, $assoc = false)
    {
        $value = $this->redis->get($key);
        return (($value === false) ? null : $this->deserialize($value, $objectType, $assoc));
    }

    /**
     * @param string $key
     * @param $field
     * @param $data
     * @param bool $serialize
     * @param string[]|null $serializeGroups
     */
    public function hset(string $key, $field, $data, bool $serialize = true, array $serializeGroups = null)
    {
        $serializeGroups = !is_null($serializeGroups) ? ['groups' => $serializeGroups] : [];
        $data = $serialize ? $this->serializer->serialize($data, 'json', $serializeGroups) : $data;
        $this->redis->hSet($key, $field, $data);
    }

    /**
     * @param string $key
     * @param $field
     * @param null $objectType
     * @param false $assoc
     * @return array|mixed|object|null
     */
    public function hget(string $key, $field, $objectType = null, $assoc = false)
    {
        $value = $this->redis->hGet($key, $field);
        return (($value === false) ? null : $this->deserialize($value, $objectType, $assoc));
    }

    /**
     * @param string $key
     * @param array $data
     */
    public function hmset(string $key, array $data)
    {
        $this->redis->hMSet($key, $data);
    }

    /**
     * @param string $key
     * @return array
     */
    public function hgetall(string $key)
    {
        return $this->redis->hGetAll($key);
    }

    /**
     * @param string $key
     * @param int $seconds
     */
    public function expire(string $key, int $seconds)
    {
        $this->redis->expire($key, $seconds);
    }

    /**
     * @param string $key
     * @param int $seconds
     * @param $data
     * @param bool $serialize
     * @param string[]|null $serializeGroups
     */
    public function setex(string $key, int $seconds, $data, bool $serialize = true, array $serializeGroups = null)
    {
        $serializeGroups = !is_null($serializeGroups) ? ['groups' => $serializeGroups] : [];
        $data = $serialize ? $this->serializer->serialize($data, 'json', $serializeGroups) : $data;
        $this->redis->setex($key, $seconds, $data);
    }

    /**
     * @param array|string $keys
     */
    public function del($keys)
    {
        $this->redis->del($keys);
    }

    /**
     * @param string|null $pattern
     * @return array
     */
    public function keys(string $pattern = null)
    {
        return $this->redis->keys($pattern);
    }

    /**
     * @param string $key
     * @param array $fields
     */
    public function hdel(string $key, array $fields)
    {
        foreach ($fields as $field) {
            $this->redis->hDel($key, $field);
        }
    }

    /**
     * @param string $key
     * @return int
     */
    public function incr(string $key)
    {
        return $this->redis->incr($key);
    }

    /**
     * @param string $commandID
     * @param array $arguments
     * @return array [error, message]
     */
    public function command(string $commandID, array $arguments = []): array
    {
        try {
            $reflectionMethod = new ReflectionMethod(get_class($this->redis), 'rawCommand');
            array_unshift($arguments, $commandID);
            $result = $reflectionMethod->invokeArgs($this->redis, $arguments);
            $lastError = $this->redis->getLastError();
            $error = !is_null($lastError);
            return [$error, ($error ? $lastError : $result)];
        } catch (Throwable $t) {
            return [true, $t->getMessage()];
        }
    }

    /**
     * @param string $key
     * @return int
     */
    public function exists(string $key): int
    {
        return $this->redis->exists($key);
    }
}
