<?php

namespace Experteam\ApiRedisBundle\MessageHandler;

use Experteam\ApiRedisBundle\Message\SendEntityToRedisMessage;
use Doctrine\ORM\EntityManagerInterface;
use Experteam\ApiRedisBundle\Service\RedisTransportV2\RedisTransportV2Interface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendEntityToRedisMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface    $entityManager,
        private readonly RedisTransportV2Interface $redisTransportV2
    )
    {
    }

    public function __invoke(SendEntityToRedisMessage $message): void
    {
        $object = $this->entityManager->getRepository($message->getClass())
            ->find($message->getId());

        if (!is_null($object)) {
            $this->redisTransportV2->processEntity($object);
        }
    }
}
