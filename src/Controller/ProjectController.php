<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Source;
use App\Form\ProjectType;
use App\Service\SourceUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects', name: 'app_project_')]
class ProjectController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        return $this->render('project/index.html.twig', [
            'projects' => $entityManager->getRepository(Project::class)->findAll(),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $project = new Project();

        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($project);
            $entityManager->flush();

            $this->addFlash('success', 'Project created');

            return $this->redirectToRoute('app_project_edit', ['id' => $project->getId()]);
        }

        return $this->render('project/edit.html.twig', [
            'form' => $form,
            'project' => $project,
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'edit')]
    public function edit(Request $request, EntityManagerInterface $entityManager, Project $project): Response
    {
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($project);
            $entityManager->flush();

            $this->addFlash('success', 'Project updated');

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
        $this->addFlash('success', 'Source updated');

        return $this->redirectToRoute('app_project_logs', ['id' => $project->getId()]);
    }

    #[Route('/delete/{id<\d+>}', name: 'delete')]
    public function delete(EntityManagerInterface $entityManager, Project $project): Response
    {
        $entityManager->remove($project);
        $entityManager->flush();

        $this->addFlash('success', 'Project deleted');

        return $this->redirectToRoute('app_project_index');
    }
}
