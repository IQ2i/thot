<?php

namespace App\Repository;

use App\Entity\Log;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Log>
 */
class LogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Log::class);
    }

    /**
     * @return Log[]
     */
    public function findToDelete(\DateTime $from): array
    {
        $qb = $this->createQueryBuilder('log');

        $qb->andWhere($qb->expr()->andX(
            $qb->expr()->lt('log.createdAt', ':from'),
        ))->setParameters(new ArrayCollection([
            new Parameter('from', $from),
        ]));

        return $qb->getQuery()->getResult();
    }
}
