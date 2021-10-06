<?php

namespace Experteam\ApiRedisBundle\Service\RedisTransport;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
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
     * @var EntityManagerInterface
     */
    private $entityManager;

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
     * @param EntityManagerInterface $entityManager
     * @param RedisClientInterface $redisClient
     * @param SerializerInterface $serializer
     * @param MessageBusInterface $messageBus
     * @param ELKLoggerInterface $elkLogger
     */
    public function __construct(ParameterBagInterface $parameterBag, EntityManagerInterface $entityManager, RedisClientInterface $redisClient, SerializerInterface $serializer, MessageBusInterface $messageBus, ELKLoggerInterface $elkLogger)
    {
        $this->parameterBag = $parameterBag;
        $this->entityManager = $entityManager;
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
     * @param array $entityConfig
     * @param object $object
     * @param null $data
     */
    protected function message(array $entityConfig, object $object, $data = null)
    {
        $messageClass = $entityConfig['message_class'];

        if (class_exists($messageClass)) {
            if (is_null($data) || $entityConfig['serialize_groups']['message'] != $entityConfig['serialize_groups']['save']) {
                $data = $this->serializeWithCircularRefHandler($object, [$entityConfig['serialize_groups']['message']]);
            }

            $dispatchMessage = true;
            $message = new $messageClass($data);

            foreach ($message->getConditions() as $key => $value) {
                if (method_exists($object, $key) && $object->$key() !== $value) {
                    $dispatchMessage = false;
                    break;
                }
            }

            if ($dispatchMessage) {
                $this->messageBus->dispatch($message);
            }

            if ($entityConfig['elk_logger']['message']) {
                $this->elkLogger->infoLog("{$entityConfig['prefix']}_message", ['data' => $data]);
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
                $this->message($entityConfig, $object, $data);
            }
        }
    }

    public function restoreData(array $entities = [])
    {
        $entitiesConfig = $this->getEntitiesConfig();

        if (count($entitiesConfig) > 0) {
            $appPrefix = $this->parameterBag->get('app.prefix');

            foreach ($entitiesConfig as $class => $entityConfig) {
                if (count($entities) > 0 && !in_array($class, $entities)) {
                    continue;
                }

                if ($entityConfig['save']) {
                    $this->redisClient->del("{$appPrefix}.{$entityConfig['prefix']}");
                    $objects = $this->entityManager->getRepository($class)->findAll();

                    if (count($objects) > 0) {
                        foreach ($objects as $object) {
                            $this->save($entityConfig, $object);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param string $dateFrom
     * @param string|null $dateTo
     * @param array $entities
     * @param array $ids
     */
    public function restoreMessages(string $dateFrom, string $dateTo = null, array $entities = [], array $ids = [])
    {
        $entitiesConfig = $this->getEntitiesConfig();
        $createdAtFrom = DateTime::createFromFormat('Y-m-d H:i:s', $dateFrom);
        $createdAtTo = DateTime::createFromFormat('Y-m-d H:i:s', $dateTo);

        if (count($entitiesConfig) > 0) {
            foreach ($entitiesConfig as $class => $entityConfig) {
                if (count($entities) > 0 && !in_array($class, $entities))
                    continue;

                if ($entityConfig['message']) {
                    /** @var ServiceEntityRepository $repository */
                    $repository = $this->entityManager->getRepository($class);

                    $qb = $repository->createQueryBuilder('qb')
                        ->where('qb.createdAt >= :createdAtFrom')
                        ->setParameter('createdAtFrom', $createdAtFrom);

                    if ($createdAtTo)
                        $qb->andWhere('qb.createdAt <= :createdAtTo')
                            ->setParameter('createdAtTo', $createdAtTo);

                    if (count($ids) > 0 && method_exists($class, 'getId'))
                        $qb->andWhere('qb.id in (:ids)')
                            ->setParameter('ids', $ids);

                    $objects = $qb->getQuery()->getResult();

                    if (count($objects) > 0) {
                        foreach ($objects as $object) {
                            $this->message($entityConfig, $object);
                        }
                    }
                }
            }
        }
    }
}