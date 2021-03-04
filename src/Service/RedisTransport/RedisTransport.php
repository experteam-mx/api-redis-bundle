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
    private $doctrine;

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
     * @param ManagerRegistry $doctrine
     * @param RedisClientInterface $redisClient
     * @param SerializerInterface $serializer
     * @param MessageBusInterface $messageBus
     * @param ELKLoggerInterface $elkLogger
     */
    public function __construct(ParameterBagInterface $parameterBag, ManagerRegistry $doctrine, RedisClientInterface $redisClient, SerializerInterface $serializer, MessageBusInterface $messageBus, ELKLoggerInterface $elkLogger)
    {
        $this->parameterBag = $parameterBag;
        $this->doctrine = $doctrine;
        $this->redisClient = $redisClient;
        $this->serializer = $serializer;
        $this->messageBus = $messageBus;
        $this->elkLogger = $elkLogger;
    }

    /**
     * @param $object
     * @param array|null $groups
     * @return string
     */
    protected function serializeWithCircularRefHandler($object, array $groups = null): string
    {
        $context = [
            'circular_reference_handler' => function ($object) {
                return (method_exists($object, 'getId') ? $object->getId() : null);
            }
        ];

        if (!is_null($groups)) {
            $context['groups'] = $groups;
        }

        return $this->serializer->serialize($object, 'json', $context);
    }

    /**
     * @param array $entityConfig
     * @param object $object
     */
    protected function save(array $entityConfig, object $object)
    {
        $method = $entityConfig['save_method'];

        if (method_exists($object, $method)) {
            $appPrefix = $this->parameterBag->get('app.prefix');
            $data = $this->serializeWithCircularRefHandler($object, [$entityConfig['serialize_groups']['save']]);
            $this->redisClient->hset("{$appPrefix}.{$entityConfig['prefix']}", $object->$method(), $data, false);

            if ($entityConfig['elk_logger']['save']) {
                $this->elkLogger->infoLog("{$entityConfig['prefix']}_save_redis", ['data' => $data]);
            }
        }
    }

    /**
     * @return array
     */
    public function getEntitiesConfig(): array
    {
        return $this->parameterBag->get('experteam_api_redis.entities');
    }

    /**
     * @param object $object
     */
    public function processEntity(object $object)
    {
        $class = get_class($object);
        $entitiesConfig = $this->getEntitiesConfig();

        if (isset($entitiesConfig[$class])) {
            $data = null;
            $entityConfig = $entitiesConfig[$class];

            if ($entityConfig['save']) {
                $this->save($entityConfig, $object);
            }

            if ($entityConfig['message']) {
                $messageClass = $entityConfig['message_class'];

                if (class_exists($messageClass)) {
                    if (is_null($data) || $entityConfig['serialize_groups']['message'] != $entityConfig['serialize_groups']['save']) {
                        $data = $this->serializeWithCircularRefHandler($object, [$entityConfig['serialize_groups']['message']]);
                    }

                    $this->messageBus->dispatch(new $messageClass($data));

                    if ($entityConfig['elk_logger']['message']) {
                        $this->elkLogger->infoLog("{$entityConfig['prefix']}_message", ['data' => $data]);
                    }
                }
            }
        }
    }

    public function restoreData()
    {
        $entitiesConfig = $this->getEntitiesConfig();

        if (count($entitiesConfig) > 0) {
            foreach ($entitiesConfig as $class => $entityConfig) {
                if ($entityConfig['save']) {
                    $objects = $this->doctrine->getRepository($class)->findAll();

                    if (count($objects) > 0) {
                        foreach ($objects as $object) {
                            $this->save($entityConfig, $object);
                        }
                    }
                }
            }
        }
    }
}