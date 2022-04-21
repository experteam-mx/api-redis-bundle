<?php

namespace Experteam\ApiRedisBundle\Service\Transaction;

interface TransactionInterface
{
    /**
     * @return string
     */
    public function getId(): string;

    /**
     * @param string $field
     * @param $value
     */
    public function saveToRedis(string $field, $value);

    /**
     * @param string $transactionId
     * @return array
     */
    public function getFromRedis(string $transactionId): array;
}
