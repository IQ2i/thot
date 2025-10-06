<?php

namespace App\Twig\Components\Layout\Menu;

use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsTwigComponent]
readonly class SelectProjectModal
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return Project[]
     */
    #[ExposeInTemplate]
    public function getProjects(): array
    {
        return $this->entityManager->getRepository(Project::class)->findAll();
    }
}
