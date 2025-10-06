<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Project;
use App\Entity\User;
use App\Form\ConversationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/conversations', name: 'app_conversation_')]
class ConversationController extends AbstractController
{
    #[Route('/new', name: 'new')]
    public function new(EntityManagerInterface $entityManager, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $project = $entityManager->getRepository(Project::class)->find($request->query->getInt('project'));
        if (null === $project) {
            throw $this->createNotFoundException();
        }

        $conversation = new Conversation()
            ->setName('New conversation #'.$user->getConversations()->count() + 1)
            ->setUser($user)
            ->setProject($project);

        $entityManager->persist($conversation);
        $entityManager->flush();

        return $this->redirectToRoute('app_conversation_detail', ['id' => $conversation->getId()]);
    }

    #[Route('/{id<\d+>}', name: 'detail')]
    public function detail(Conversation $conversation): Response
    {
        return $this->render('conversation/detail.html.twig', [
            'conversation' => $conversation,
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'edit')]
    public function edit(Request $request, EntityManagerInterface $entityManager, Conversation $conversation): Response
    {
        $form = $this->createForm(ConversationType::class, $conversation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Conversation updated');

            return $this->redirectToRoute('app_conversation_detail', ['id' => $conversation->getId()]);
        }

        return $this->render('conversation/edit.html.twig', [
            'form' => $form,
            'conversation' => $conversation,
        ]);
    }

    #[Route('/{id<\d+>}/delete', name: 'delete')]
    public function delete(EntityManagerInterface $entityManager, Conversation $conversation): Response
    {
        $entityManager->remove($conversation);
        $entityManager->flush();

        $this->addFlash('success', 'Conversation deleted');

        return $this->redirectToRoute('app_homepage_detail');
    }
}
