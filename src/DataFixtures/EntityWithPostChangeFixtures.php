<?php

namespace Experteam\ApiRedisBundle\DataFixtures;

use Experteam\ApiRedisBundle\Entity\EntityWithPostChange;
use Doctrine\Persistence\ObjectManager;

class EntityWithPostChangeFixtures
{
    /**
     * @param ObjectManager $manager
     * @param array $entitiesWithPostChange
     */
    public function load(ObjectManager $manager, array $entitiesWithPostChange)
    {
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