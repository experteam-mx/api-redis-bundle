<?php

namespace Experteam\ApiRedisBundle\Service\RedisTransportV2;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Experteam\ApiBaseBundle\Service\ELKLogger\ELKLoggerInterface;
use Experteam\ApiRedisBundle\Service\RedisClient\RedisClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

class RedisTransportV2 implements RedisTransportV2Interface
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
     * @param bool $withTranslations
     * @return string
     */
    protected function serializeWithCircularRefHandler($object, array $groups = null, bool $withTranslations = false): string
    {
        $context = [
            'circular_reference_handler' => function ($object) {
                return (method_exists($object, 'getId') ? $object->getId() : null);
            },
            'with_translations' => $withTranslations
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
            $suffixMethod = $entityConfig['save_suffix_method'] ?? null;
            $suffix = !empty($suffixMethod) && method_exists($object, $suffixMethod) ? $object->$suffixMethod() : '';

            $appPrefix = $this->parameterBag->get('app.prefix');
            $data = $this->serializeWithCircularRefHandler(
                $object,
                [$entityConfig['serialize_groups']['save']],
                $entityConfig['with_translations']['save'] ?? false
            );
            $this->redisClient->hset("$appPrefix.{$entityConfig['prefix']}$suffix", $object->$method(), $data, false);

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
                $data = $this->serializeWithCircularRefHandler(
                    $object,
                    [$entityConfig['serialize_groups']['message']],
                    $entityConfig['with_translations']['message'] ?? false
                );
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
     * @param array $entityConfig
     * @param object $object
     */
    protected function streamCompute(array $entityConfig, object $object)
    {
        $appPrefix = $this->parameterBag->get('app.prefix');
        $data = $this->serializeWithCircularRefHandler(
            $object,
            [$entityConfig['serialize_groups']['stream_compute']],
            $entityConfig['with_translations']['stream_compute'] ?? false
        );

        $arguments = ["streamCompute.$appPrefix.{$entityConfig['prefix']}", '*', 'message', $data];
        [$error, $message] = $this->redisClient->command('XADD', $arguments);

        if ($error)
            $this->elkLogger->errorLog("Error send message to stream compute", [
                'message' => $message,
                'arguments' => $arguments
            ]);

        if ($entityConfig['elk_logger']['stream_compute'])
            $this->elkLogger->infoLog("{$entityConfig['prefix']}_stream_compute", ['data' => $data]);
    }

    /**
     * @return array
     */
    public function getEntitiesConfig(): array
    {
        return $this->parameterBag->get('experteam_api_redis.entities.v2');
    }

    /**
     * @param object $object
     */
    public function processEntity(object $object)
    {
        $class = get_class($object);
        $entitiesConfig = $this->getEntitiesConfig();

        $entityConfigs = array_filter($entitiesConfig, function ($cfg) use ($class) {
            return $cfg['class'] == $class;
        });

        foreach ($entityConfigs as $entityConfig) {
            if ($entityConfig['save']) {
                $this->save($entityConfig, $object);
            }

            if ($entityConfig['message']) {
                $this->message($entityConfig, $object);
            }

            if ($entityConfig['stream_compute']) {
                $this->streamCompute($entityConfig, $object);
            }
        }
    }

    /**
     * @param array $entities
     * @return array|null
     */
    public function restoreData(array $entities = []): ?array
    {
        $entitiesConfig = $this->getEntitiesConfig();

        if (empty($entitiesConfig)) {
            return null;
        }

        $keysNotGenerated = [];
        $appPrefix = $this->parameterBag->get('app.prefix');

        foreach ($entitiesConfig as $entityConfig) {
            $class = $entityConfig['class'];

            if (count($entities) > 0 && !in_array($class, $entities)) {
                continue;
            }

            if ($entityConfig['save']) {
                $key = "$appPrefix.{$entityConfig['prefix']}";
                $isNullSaveSuffixMethod = is_null(($entityConfig['save_suffix_method'] ?? null));
                $keys = $this->redisClient->keys($key . (!$isNullSaveSuffixMethod ? '*' : ''));

                if (!empty($keys)) {
                    $this->redisClient->del($keys);
                }

                $objects = $this->entityManager->getRepository($class)->findAll();

                if (count($objects) > 0) {
                    foreach ($objects as $object) {
                        $this->save($entityConfig, $object);
                    }
                }

                if ($isNullSaveSuffixMethod && $this->redisClient->exists($key) === 0) {
                    $keysNotGenerated[] = $key;
                }
            }
        }

        return (empty($keysNotGenerated) ? null : $keysNotGenerated);
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

        if (empty($entitiesConfig))
            return;

        foreach ($entitiesConfig as $entityConfig) {
            $class = $entityConfig['class'];

            if (count($entities) > 0 && !in_array($class, $entities))
                continue;

            if ($entityConfig['message']) {

                $qb = $this->getQueryBuilderToRestore($class, $createdAtFrom, $createdAtTo, $ids);

                foreach ($qb->getQuery()->toIterable() as $object)
                    $this->message($entityConfig, $object);
            }
        }
    }

    /**
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @param array $entities
     * @param array $ids
     * @return void
     */
    public function restoreStreamCompute(string $dateFrom = null, string $dateTo = null, array $entities = [], array $ids = [])
    {
        $entitiesConfig = $this->getEntitiesConfig();
        $createdAtFrom = DateTime::createFromFormat('Y-m-d H:i:s', $dateFrom);
        $createdAtTo = DateTime::createFromFormat('Y-m-d H:i:s', $dateTo);

        if (empty($entitiesConfig))
            return;

        foreach ($entitiesConfig as $entityConfig) {
            $class = $entityConfig['class'];

            if (count($entities) > 0 && !in_array($class, $entities))
                continue;

            if ($entityConfig['stream_compute']) {

                $qb = $this->getQueryBuilderToRestore($class, $createdAtFrom, $createdAtTo, $ids);

                foreach ($qb->getQuery()->toIterable() as $object)
                    $this->streamCompute($entityConfig, $object);
            }
        }
    }

    /**
     * @param string $class
     * @param $createdAtFrom
     * @param $createdAtTo
     * @param array $ids
     * @return QueryBuilder
     */
    protected function getQueryBuilderToRestore(string $class, $createdAtFrom, $createdAtTo, array $ids = []): QueryBuilder
    {
        /** @var ServiceEntityRepository $repository */
        $repository = $this->entityManager->getRepository($class);
        $qb = $repository->createQueryBuilder('qb');

        if ($createdAtFrom instanceof DateTime)
            $qb->where('qb.createdAt >= :createdAtFrom')
                ->setParameter('createdAtFrom', $createdAtFrom);

        if ($createdAtTo instanceof DateTime)
            $qb->andWhere('qb.createdAt <= :createdAtTo')
                ->setParameter('createdAtTo', $createdAtTo);

        if (count($ids) > 0 && method_exists($class, 'getId'))
            $qb->andWhere('qb.id in (:ids)')
                ->setParameter('ids', $ids);

        return $qb;
    }
}
