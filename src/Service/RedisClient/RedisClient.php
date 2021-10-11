<?php

namespace Experteam\ApiRedisBundle\Service\RedisClient;

use Predis\Client;
use Symfony\Component\Serializer\SerializerInterface;

class RedisClient implements RedisClientInterface
{
    /**
     * @var Client
     */
    private $predisClient;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param Client $predisClient
     * @param SerializerInterface $serializer
     */
    public function __construct(Client $predisClient, SerializerInterface $serializer)
    {
        $this->predisClient = $predisClient;
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
        $this->predisClient->set($key, $data);
    }

    /**
     * @param string $key
     * @param null $objectType
     * @param false $assoc
     * @return array|mixed|object|null
     */
    public function get(string $key, $objectType = null, $assoc = false)
    {
        $value = $this->predisClient->get($key);
        return (is_null($value) ? null : $this->deserialize($value, $objectType, $assoc));
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
        $this->predisClient->hset($key, $field, $data);
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
        $value = $this->predisClient->hget($key, $field);
        return (is_null($value) ? null : $this->deserialize($value, $objectType, $assoc));
    }

    /**
     * @param string $key
     * @param array $data
     */
    public function hmset(string $key, array $data)
    {
        $this->predisClient->hmset($key, $data);
    }

    /**
     * @param string $key
     * @return array
     */
    public function hgetall(string $key)
    {
        return $this->predisClient->hgetall($key);
    }

    /**
     * @param string $key
     * @param int $seconds
     */
    public function expire(string $key, int $seconds)
    {
        $this->predisClient->expire($key, $seconds);
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
        $this->predisClient->setex($key, $seconds, $data);
    }

    /**
     * @param array|string $keys
     */
    public function del($keys)
    {
        $this->predisClient->del($keys);
    }

    /**
     * @param string|null $pattern
     */
    public function keys(string $pattern = null)
    {
        return $this->predisClient->keys($pattern);
    }

    /**
     * @param string $key
     * @param array $fields
     */
    public function hdel(string $key, array $fields)
    {
        $this->predisClient->hdel($key, $fields);
    }

    /**
     * @param string $key
     * @return int
     */
    public function incr(string $key)
    {
        return $this->predisClient->incr($key);
    }
}