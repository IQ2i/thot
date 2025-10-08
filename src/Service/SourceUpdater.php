<?php

namespace App\Service;

use App\Bridge\BridgeInterface;
use App\Entity\Source;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class SourceUpdater
{
    public function __construct(
        /**
         * @var BridgeInterface[]
         */
        #[AutowireIterator('iq2i_thot.bridge')]
        private iterable $bridges,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function update(Source $source, bool $all = false): void
    {
        $bridge = array_find(iterator_to_array($this->bridges), fn (BridgeInterface $bridge): bool => $bridge->supports($source));
        if (null === $bridge) {
            return;
        }

        $bridge->importNewDocuments($source, $all);
        $bridge->updateDocuments($source, $all);

        $source->setLastUpdatedAt(new \DateTime());

        $this->entityManager->flush();
    }
}
