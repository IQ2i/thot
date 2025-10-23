<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Source;
use App\Event\CreateProjectEvent;
use App\Event\DeleteProjectEvent;
use App\Event\EditProjectEvent;
use App\Events;
use App\Form\ProjectType;
use App\Service\SourceUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route('/projects', name: 'app_project_')]
class ProjectController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(): Response
    {
        return $this->render('project/index.html.twig', [
            'projects' => $this->entityManager->getRepository(Project::class)->findAll(),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $project = new Project();

        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($project);
            $this->entityManager->flush();

            $this->eventDispatcher->dispatch(new CreateProjectEvent($project), Events::PROJECT_CREATE);
            $this->addFlash('success', new TranslatableMessage('flash.project_created'));

            return $this->redirectToRoute('app_project_edit', ['id' => $project->getId()]);
        }

        return $this->render('project/edit.html.twig', [
            'form' => $form,
            'project' => $project,
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'edit')]
    public function edit(Request $request, Project $project): Response
    {
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->eventDispatcher->dispatch(new EditProjectEvent($project), Events::PROJECT_EDIT);
            $this->addFlash('success', new TranslatableMessage('flash.project_updated'));

            return $this->redirectToRoute('app_project_edit', ['id' => $project->getId()]);
        }

        return $this->render('project/edit.html.twig', [
            'form' => $form,
            'project' => $project,
        ]);
    }

    #[Route('/{id<\d+>}/sources', name: 'sources')]
    public function sources(Project $project): Response
    {
        return $this->render('project/sources.html.twig', [
            'project' => $project,
        ]);
    }

    #[Route('/{project}/sources/{source}/update', name: 'source_update')]
    public function sourceUpdate(Project $project, Source $source, SourceUpdater $sourceUpdater): Response
    {
        $sourceUpdater->update($source);
        $this->addFlash('success', new TranslatableMessage('flash.source_updated'));

        return $this->redirectToRoute('app_project_sources', ['id' => $project->getId()]);
    }

    #[Route('/delete/{id<\d+>}', name: 'delete')]
    public function delete(Project $project): Response
    {
        $this->eventDispatcher->dispatch(new DeleteProjectEvent($project), Events::PROJECT_DELETE);

        $this->entityManager->remove($project);
        $this->entityManager->flush();

        $this->addFlash('success', new TranslatableMessage('flash.project_deleted'));

        return $this->redirectToRoute('app_project_index');
    }
}
