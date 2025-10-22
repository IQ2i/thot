<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Log;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/logs', name: 'app_log_')]
class LogController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(): Response
    {
        return $this->render('log/index.html.twig', [
            'logs' => $this->entityManager->getRepository(Log::class)->findBy([], orderBy: ['createdAt' => 'DESC']),
        ]);
    }
}
