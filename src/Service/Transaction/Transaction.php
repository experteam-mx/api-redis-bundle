<?php

namespace Experteam\ApiRedisBundle\Service\Transaction;

use Experteam\ApiRedisBundle\Service\RedisClient\RedisClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

class Transaction implements TransactionInterface
{
    /**
     * @var RedisClientInterface
     */
    private $redisClient;

    /**
     * @var ParameterBagInterface
     */
    private $parameterBag;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var string
     */
    private $key;

    /**
     * @param RedisClientInterface $redisClient
     * @param ParameterBagInterface $parameterBag
     * @param RequestStack $requestStack
     */
    public function __construct(RedisClientInterface $redisClient, ParameterBagInterface $parameterBag, RequestStack $requestStack)
    {
        $this->redisClient = $redisClient;
        $this->parameterBag = $parameterBag;
        $this->request = $requestStack->getCurrentRequest();
    }

    /**
     * @return string
     */
    private function getRedisKey(): string
    {
        return sprintf('%s.transaction:%s', $this->parameterBag->get('app.prefix'), $this->getId());
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        if (is_null($this->key)) {
            $this->key = ((is_null($this->request) || !$this->request->headers->has('Transaction-Id')) ? Uuid::v1()->toRfc4122() : $this->request->headers->get('Transaction-Id'));
        }

        return $this->key;
    }

    /**
     * @param string $field
     * @param $value
     */
    public function saveToRedis(string $field, $value)
    {
        $redisKey = $this->getRedisKey();
        $now = date_create()->format('YmdHisv');
        $this->redisClient->hset($redisKey, "{$now}_$field", $value);
        $this->redisClient->expire($redisKey, $this->parameterBag->get('app.transaction.ttl.sec'));
    }

    /**
     * @param string $transactionId
     * @return array
     */
    public function getFromRedis(string $transactionId): array
    {
        $this->key = $transactionId;
        $redisKey = $this->getRedisKey();

        return array_map(function ($v) {
            return json_decode($v);
        }, ($this->redisClient->hgetall($redisKey) ?? []));
    }
}
