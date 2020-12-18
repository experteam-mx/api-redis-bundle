<?php

namespace Experteam\ApiRedisBundle\EventSubscriber;

use Experteam\ApiRedisBundle\Entity\EntityWithPostChange;
use Experteam\ApiRedisBundle\Service\RedisClient\RedisClientInterface;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

class PostChangeSubscriber implements EventSubscriber
{
    /**
     * @var ContainerInterface
     */
    private $container;

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

    public function __construct(ContainerInterface $container, RedisClientInterface $redisClient, SerializerInterface $serializer, MessageBusInterface $messageBus)
    {
        $this->container = $container;
        $this->redisClient = $redisClient;
        $this->serializer = $serializer;
        $this->messageBus = $messageBus;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::postRemove
        ];
    }

    private function persist($object)
    {
        $entitiesWithPostChange = $this->container->get('doctrine')->getRepository(EntityWithPostChange::class)->findBy(['isActive' => true]);

        if (count($entitiesWithPostChange) > 0) {
            $break = false;
            $appPrefix = $this->container->getParameter('app.prefix');

            /** @var EntityWithPostChange $entityWithPostChange */
            foreach ($entitiesWithPostChange as $entityWithPostChange) {
                $class = $entityWithPostChange->getClass();

                if ($class === str_replace('App\\Entity\\', '', get_class($object))) {
                    $break = true;
                    $encoder = new JsonEncoder();
                    $data = $this->serializer->serialize($object, 'json', ['groups' => 'read']);

                    $defaultContext = [AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                        $method = 'getId';
                        return (method_exists($object, $method) ? $object->$method() : null);
                    }];

                    $normalizer = new ObjectNormalizer(null, null, null, null, null, null, $defaultContext);
                    $serializer = new Serializer([$normalizer], [$encoder]);
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

                if ($break) {
                    break;
                }
            }
        }
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $this->persist($args->getObject());
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $this->persist($args->getObject());
    }

    public function postRemove(LifecycleEventArgs $args)
    {
        $this->persist($args->getObject());
    }
}