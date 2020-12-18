<?php

namespace Experteam\ApiRedisBundle\Repository;

use Experteam\ApiRedisBundle\Entity\EntityWithPostChange;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method EntityWithPostChange|null find($id, $lockMode = null, $lockVersion = null)
 * @method EntityWithPostChange|null findOneBy(array $criteria, array $orderBy = null)
 * @method EntityWithPostChange[]    findAll()
 * @method EntityWithPostChange[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EntityWithPostChangeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EntityWithPostChange::class);
    }
}