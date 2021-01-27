<?php

namespace Experteam\ApiRedisBundle\Service\RedisTransport;

use Doctrine\Persistence\ManagerRegistry;
use Experteam\ApiBaseBundle\Service\ELKLogger\ELKLoggerInterface;
use Experteam\ApiRedisBundle\Service\RedisClient\RedisClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

class RedisTransport implements RedisTransportInterface
{
    /**
     * @var ParameterBagInterface
     */
    private $parameterBag;

    /**
     * @var ManagerRegistry
     */
    private $registry;

    /**
     * @var RedisClientInterface
     */
    private $redisClient;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var MessageBusInterface
     */
    private $messageBus;

    /**
     * @var ELKLoggerInterface
     */
    private $elkLogger;

    /**
     * PostChange constructor.
     * @param ParameterBagInterface $parameterBag
     * @param ManagerRegistry $registry
     * @param RedisClientInterface $redisClient
     * @param SerializerInterface $serializer
     * @param MessageBusInterface $messageBus
     * @param ELKLoggerInterface $elkLogger
     */
    public function __construct(ParameterBagInterface $parameterBag, ManagerRegistry $registry, RedisClientInterface $redisClient, SerializerInterface $serializer, MessageBusInterface $messageBus, ELKLoggerInterface $elkLogger)
    {
        $this->parameterBag = $parameterBag;
        $this->registry = $registry;
        $this->redisClient = $redisClient;
        $this->serializer = $serializer;
        $this->messageBus = $messageBus;
        $this->elkLogger = $elkLogger;
    }

    /**
     * @return array
     */
    public function getEntitiesConfig()
    {
        return $this->parameterBag->get('experteam_api_redis.entities');
    }

    /**
     * @param $object
     */
    public function processEntity($object)
    {
        $entities = $this->parameterBag->get('experteam_api_redis.entities');
        $class = get_class($object);

        if (isset($entities[$class])) {
            $cfg = $entities[$class];
            $appPrefix = $this->parameterBag->get('app.prefix');
            $data = null;

            if ($cfg['save']) {
                $method = $cfg['save_method'];
                if (method_exists($object, $method)) {
                    $data = $this->serializeWithCircularRefHandler($object, [$cfg['serialize_groups']['save']]);
                    $this->redisClient->hset("{$appPrefix}.{$cfg['prefix']}", $object->$method(), $data, false);

                    if ($cfg['elk_logger']['save']) {
                        $this->elkLogger->infoLog("{$cfg['prefix']}_save_redis", ['data' => $data]);
                    }
                }
            }

            if ($cfg['message']) {
                $messageClass = $cfg['message_class'];
                if (class_exists($messageClass)) {
                    if (is_null($data) || $cfg['serialize_groups']['message'] != $cfg['serialize_groups']['save'])
                        $data = $this->serializeWithCircularRefHandler($object, [$cfg['serialize_groups']['message']]);
                    $this->messageBus->dispatch(new $messageClass($data));

                    if ($cfg['elk_logger']['message']) {
                        $this->elkLogger->infoLog("{$cfg['prefix']}_message", ['data' => $data]);
                    }
                }
            }
        }
    }

    /**
     * @param $object
     * @param array|null $groups
     * @return string
     */
    protected function serializeWithCircularRefHandler($object, array $groups = null)
    {
        $context = [
            'circular_reference_handler' => function ($object) {
                return method_exists($object, 'getId') ? $object->getId() : null;
            }
        ];

        if (!is_null($groups))
            $context['groups'] = $groups;

        return $this->serializer->serialize($object, 'json', $context);
    }
}