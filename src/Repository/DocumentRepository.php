<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\Project;
use App\Entity\Source;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /**
     * @return Document[]
     */
    public function findToUpdate(Source $source): array
    {
        $qb = $this->createQueryBuilder('document');

        $qb->andWhere($qb->expr()->andX(
            $qb->expr()->eq('document.source', ':source'),
            $qb->expr()->eq('document.closed', ':closed'),
        ))->setParameters(new ArrayCollection([
            new Parameter('source', $source),
            new Parameter('closed', false),
        ]));

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Document[]
     */
    public function findToIndex(Project $project, bool $all = false): array
    {
        $qb = $this->createQueryBuilder('document');

        $qb->join('document.source', 'source');

        $qb->andWhere($qb->expr()->andX(
            $qb->expr()->eq('source.project', ':project'),
        ))->setParameters(new ArrayCollection([
            new Parameter('project', $project),
        ]));

        if (!$all) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->isNull('document.indexedAt'),
                $qb->expr()->gt('document.syncedAt', 'document.indexedAt'),
            ));
        }

        return $qb->getQuery()->getResult();
    }
}
