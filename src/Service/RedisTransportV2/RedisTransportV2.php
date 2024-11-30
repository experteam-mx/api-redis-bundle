<?php

namespace Experteam\ApiRedisBundle\Service\RedisTransportV2;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Experteam\ApiBaseBundle\Service\ELKLogger\ELKLoggerInterface;
use Experteam\ApiRedisBundle\Service\RedisClient\RedisClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

class RedisTransportV2 implements RedisTransportV2Interface
{
    public function __construct(
        private readonly ParameterBagInterface  $parameterBag,
        private readonly EntityManagerInterface $entityManager,
        private readonly RedisClientInterface   $redisClient,
        private readonly SerializerInterface    $serializer,
        private readonly MessageBusInterface    $messageBus,
        private readonly ELKLoggerInterface     $elkLogger
    )
    {
    }

    public function processEntity(object $object): void
    {
        $class = ClassUtils::getClass($object);
        $entitiesConfig = $this->getEntitiesConfig();

        $entityConfigs = array_filter($entitiesConfig, function ($cfg) use ($class) {
            return ($cfg['class'] === $class);
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

    public function getEntitiesConfig(): array
    {
        return $this->parameterBag->get('experteam_api_redis.entities.v2');
    }

    protected function save(array $entityConfig, object $object)
    {
        $method = $entityConfig['save_method'];

        if (method_exists($object, $method)) {
            $suffixMethod = ($entityConfig['save_suffix_method'] ?? null);
            $suffix = ((!empty($suffixMethod) && method_exists($object, $suffixMethod)) ? $object->$suffixMethod() : '');
            $appPrefix = $this->parameterBag->get('app.prefix');
            $data = $this->serializeWithCircularRefHandler($object, [$entityConfig['serialize_groups']['save']], ($entityConfig['with_translations']['save'] ?? false));
            $this->redisClient->hset("$appPrefix.{$entityConfig['prefix']}$suffix", $object->$method(), $data, false);

            if ($entityConfig['elk_logger']['save']) {
                $this->elkLogger->infoLog("{$entityConfig['prefix']}_save_redis", ['data' => $data]);
            }
        }
    }

    protected function serializeWithCircularRefHandler($object, ?array $groups = null, bool $withTranslations = false): string
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

    protected function message(array $entityConfig, object $object, $data = null): void
    {
        $messageClass = $entityConfig['message_class'];

        if (class_exists($messageClass)) {
            if (is_null($data) || $entityConfig['serialize_groups']['message'] != $entityConfig['serialize_groups']['save']) {
                $data = $this->serializeWithCircularRefHandler($object, [$entityConfig['serialize_groups']['message']], ($entityConfig['with_translations']['message'] ?? false));
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

    protected function streamCompute(array $entityConfig, object $object): void
    {
        $appPrefix = $this->parameterBag->get('app.prefix');
        $data = $this->serializeWithCircularRefHandler($object, [$entityConfig['serialize_groups']['stream_compute']], ($entityConfig['with_translations']['stream_compute'] ?? false));
        $arguments = ["streamCompute.$appPrefix.{$entityConfig['prefix']}", '*', 'message', $data];
        [$error, $message] = $this->redisClient->command('XADD', $arguments);

        if ($error) {
            $this->elkLogger->errorLog("Error send message to stream compute", [
                'message' => $message,
                'arguments' => $arguments
            ]);
        }

        if ($entityConfig['elk_logger']['stream_compute']) {
            $this->elkLogger->infoLog("{$entityConfig['prefix']}_stream_compute", ['data' => $data]);
        }
    }

    public function restoreData(array $entities = []): ?array
    {
        $appPrefix = $this->parameterBag->get('app.prefix');
        $keys = $this->redisClient->keys("$appPrefix.*");

        if (!empty($keys)) {
            $this->redisClient->del($keys);
        }

        $entitiesConfig = $this->getEntitiesConfig();

        if (empty($entitiesConfig)) {
            return null;
        }

        $keysNotGenerated = [];

        foreach ($entitiesConfig as $entityConfig) {
            $class = $entityConfig['class'];

            if (count($entities) > 0 && !in_array($class, $entities)) {
                continue;
            }

            if ($entityConfig['save']) {
                $objects = $this->entityManager->getRepository($class)->findAll();

                if (count($objects) > 0) {
                    foreach ($objects as $object) {
                        $this->save($entityConfig, $object);
                    }
                }

                $key = "$appPrefix.{$entityConfig['prefix']}";

                if (is_null(($entityConfig['save_suffix_method'] ?? null)) && $this->redisClient->exists($key) === 0) {
                    $keysNotGenerated[] = $key;
                }
            }
        }

        return (empty($keysNotGenerated) ? null : $keysNotGenerated);
    }

    public function restoreMessages(string $dateFrom, ?string $dateTo = null, array $entities = [], array $ids = []): void
    {
        $entitiesConfig = $this->getEntitiesConfig();
        $createdAtFrom = DateTime::createFromFormat('Y-m-d H:i:s', $dateFrom);
        $createdAtTo = DateTime::createFromFormat('Y-m-d H:i:s', $dateTo);

        if (empty($entitiesConfig)) {
            return;
        }

        foreach ($entitiesConfig as $entityConfig) {
            $class = $entityConfig['class'];

            if (count($entities) > 0 && !in_array($class, $entities)) {
                continue;
            }

            if ($entityConfig['message']) {
                $qb = $this->getQueryBuilderToRestore($class, $createdAtFrom, $createdAtTo, $ids);

                foreach ($qb->getQuery()->toIterable() as $object) {
                    $this->message($entityConfig, $object);
                }
            }
        }
    }

    protected function getQueryBuilderToRestore(string $class, $createdAtFrom, $createdAtTo, array $ids = []): QueryBuilder
    {
        /** @var ServiceEntityRepository $repository */
        $repository = $this->entityManager->getRepository($class);
        $qb = $repository->createQueryBuilder('qb');

        if ($createdAtFrom instanceof DateTime) {
            $qb->where('qb.createdAt >= :createdAtFrom')
                ->setParameter('createdAtFrom', $createdAtFrom);
        }

        if ($createdAtTo instanceof DateTime) {
            $qb->andWhere('qb.createdAt <= :createdAtTo')
                ->setParameter('createdAtTo', $createdAtTo);
        }

        if (count($ids) > 0 && method_exists($class, 'getId')) {
            $qb->andWhere('qb.id in (:ids)')
                ->setParameter('ids', $ids);
        }

        return $qb;
    }

    public function restoreStreamCompute(?string $dateFrom = null, ?string $dateTo = null, array $entities = [], array $ids = []): void
    {
        $entitiesConfig = $this->getEntitiesConfig();
        $createdAtFrom = DateTime::createFromFormat('Y-m-d H:i:s', $dateFrom);
        $createdAtTo = DateTime::createFromFormat('Y-m-d H:i:s', $dateTo);

        if (empty($entitiesConfig)) {
            return;
        }

        foreach ($entitiesConfig as $entityConfig) {
            $class = $entityConfig['class'];

            if (count($entities) > 0 && !in_array($class, $entities)) {
                continue;
            }

            if ($entityConfig['stream_compute']) {
                $qb = $this->getQueryBuilderToRestore($class, $createdAtFrom, $createdAtTo, $ids);

                foreach ($qb->getQuery()->toIterable() as $object) {
                    $this->streamCompute($entityConfig, $object);
                }
            }
        }
    }
}
