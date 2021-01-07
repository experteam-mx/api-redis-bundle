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
     * @param $object
     */
    public function postChangeEntity($object)
    {
        $manager = $this->registry->getManager();
        $entitiesWithPostChange = $manager->getRepository(EntityWithPostChange::class)->findBy(['isActive' => true]);

        if (count($entitiesWithPostChange) > 0) {
            $appPrefix = $this->parameterBag->get('app.prefix');

            /** @var EntityWithPostChange $entityWithPostChange */
            foreach ($entitiesWithPostChange as $entityWithPostChange) {
                $class = $entityWithPostChange->getClass();

                if ($class !== basename(str_replace('\\', '/', get_class($object))))
                    break;

                $data = $this->serializer->serialize($object, 'json', ['groups' => 'read']);

                $defaultContext = [AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                    $method = 'getId';
                    return (method_exists($object, $method) ? $object->$method() : null);
                }];

                $normalizer = new ObjectNormalizer(null, null, null, null, null, null, $defaultContext);
                $serializer = new Serializer([$normalizer], [new JsonEncoder()]);
                $serializer->serialize($object, 'json');

                if ($entityWithPostChange->getToRedis()) {
                    $method = $entityWithPostChange->getMethod();

                    if (method_exists($object, $method)) {
                        $this->redisClient->hset("{$appPrefix}.{$entityWithPostChange->getPrefix()}", $object->$method(), $data, false);
                    }
                }

                if ($entityWithPostChange->getDispatchMessage()) {
                    $messageClass = "\App\Message\\{$class}Message";

                    if (class_exists($messageClass)) {
                        $this->messageBus->dispatch(new $messageClass($data));
                    }
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