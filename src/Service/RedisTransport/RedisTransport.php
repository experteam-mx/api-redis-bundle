<?php

namespace Experteam\ApiRedisBundle\Service\RedisTransport;

use Doctrine\Persistence\ManagerRegistry;
use Experteam\ApiRedisBundle\Entity\EntityWithPostChange;
use Experteam\ApiRedisBundle\Service\RedisClient\RedisClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
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
                    dd($data);
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
     * @param array $entitiesWithPostChange
     */
    public function loadEntitiesWithPostChange(array $entitiesWithPostChange)
    {
        $manager = $this->registry->getManager();
        $entityWithPostChangeRepository = $manager->getRepository(EntityWithPostChange::class);

        foreach ($entitiesWithPostChange as $value) {
            $class = $value['class'];
            $entityWithPostChange = $entityWithPostChangeRepository->findOneBy(['class' => $class]);

            if (is_null($entityWithPostChange)) {
                $entityWithPostChange = new EntityWithPostChange();
                $entityWithPostChange->setClass($class);
            }

            $entityWithPostChange->setPrefix($value['prefix']);
            $entityWithPostChange->setToRedis($value['toRedis']);
            $entityWithPostChange->setDispatchMessage($value['dispatchMessage']);

            if (isset($value['method'])) {
                $entityWithPostChange->setMethod($value['method']);
            }

            $manager->persist($entityWithPostChange);
        }

        $manager->flush();
    }
}