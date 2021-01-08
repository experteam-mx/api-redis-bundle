<?php

namespace Experteam\ApiRedisBundle\Service\RedisTransport;

use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Exception;
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
     * PostChange constructor.
     * @param ParameterBagInterface $parameterBag
     * @param ManagerRegistry $registry
     * @param RedisClientInterface $redisClient
     * @param SerializerInterface $serializer
     * @param MessageBusInterface $messageBus
     */
    public function __construct(ParameterBagInterface $parameterBag, ManagerRegistry $registry, RedisClientInterface $redisClient, SerializerInterface $serializer, MessageBusInterface $messageBus)
    {
        $this->parameterBag = $parameterBag;
        $this->registry = $registry;
        $this->redisClient = $redisClient;
        $this->serializer = $serializer;
        $this->messageBus = $messageBus;
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
        $cfg = $entities[$class] ?? null;

        if (!is_null($cfg)) {
            $appPrefix = $this->parameterBag->get('app.prefix');
            $data = null;

            if ($cfg['save']) {
                $method = $cfg['save_method'];
                if (method_exists($object, $method)) {
                    $data = $this->serializer->serialize($object, 'json', ['groups' => $cfg['serialize_groups']['save']]);
                    $this->redisClient->hset("{$appPrefix}.{$cfg['prefix']}", $object->$method(), $data, false);
                }
            }

            if ($cfg['message']) {
                $messageClass = "\App\Message\\{$class}Message";
                if (class_exists($messageClass)) {
                    if (is_null($data) || $cfg['serialize_groups']['message'] != $cfg['serialize_groups']['save'])
                        $data = $this->serializer->serialize($object, 'json', ['groups' => $cfg['serialize_groups']['message']]);
                    $this->messageBus->dispatch(new $messageClass($data));
                }
            }
        }
    }

    /**
     * @param array $entityClasses
     */
    public function processAllSaves(array $entityClasses = [])
    {
        $entities = $this->parameterBag->get('experteam_api_redis.entities');
        $manager = $this->registry->getManager();
        $appPrefix = $this->parameterBag->get('app.prefix');

        foreach ($entities as $class => $cfg) {
            if (!$cfg['save'] || (!empty($entityClasses) && !in_array($class, $entityClasses)))
                continue;

            $objects = $manager->getRepository($class)->findAll();
            foreach ($objects as $object) {
                $method = $cfg['save_method'];
                if (method_exists($object, $method)) {
                    $data = $this->serializer->serialize($object, 'json', ['groups' => $cfg['serialize_groups']['save']]);
                    $this->redisClient->hset("{$appPrefix}.{$cfg['prefix']}", $object->$method(), $data, false);
                }
            }
        }
    }

    /**
     * @param array $entityClasses
     * @param DateTime|null $updatedFrom
     * @param array $filters
     * @throws Exception
     */
    public function processAllMessages(array $entityClasses = [], DateTime $updatedFrom = null, array $filters = [])
    {
        $entities = $this->parameterBag->get('experteam_api_redis.entities');
        $manager = $this->registry->getManager();

        foreach ($entities as $class => $cfg) {
            if (!$cfg['message'] || (!empty($entityClasses) && !in_array($class, $entityClasses)))
                continue;

            $metadata = $this->getClassMetadata($class);
            $qb = $manager->getRepository($class)->createQueryBuilder('e');

            if (!is_null($updatedFrom) && $metadata->hasField('updatedAt')) {
                $qb->andWhere('e.updatedAt >= :updatedFrom')
                    ->setParameter('updatedFrom', $updatedFrom);
            }
            if (!empty($filters)) {
                foreach ($filters[$class] ?? [] as $field => $value) {
                    if (!$metadata->hasField($field))
                        throw new Exception(sprintf('RedisTransport: the field %s not exists on class %s', $field, $class));
                    $qb->andWhere(sprintf('e.%s = :%s', $field, $field))
                        ->setParameter($field, $value);
                }
            }

            $objects = $qb->getQuery()->getResult();
            foreach ($objects as $object) {
                $messageClass = "\App\Message\\{$class}Message";
                if (class_exists($messageClass)) {
                    $data = $this->serializer->serialize($object, 'json', ['groups' => $cfg['serialize_groups']['message']]);
                    $this->messageBus->dispatch(new $messageClass($data));
                }
            }
        }
    }

    /**
     * @param string $className
     * @return ClassMetadata
     */
    protected function getClassMetadata(string $className): ClassMetadata
    {
        return $this->registry->getManagerForClass($className)->getClassMetadata($className);
    }
}