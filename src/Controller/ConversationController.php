<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Project;
use App\Entity\User;
use App\Event\CreateConversationEvent;
use App\Event\DeleteConversationEvent;
use App\Event\EditConversationEvent;
use App\Events;
use App\Form\ConversationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
#[Route('/conversations', name: 'app_conversation_')]
class ConversationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $project = $this->entityManager->getRepository(Project::class)->find($request->query->getInt('project'));
        if (null === $project) {
            throw $this->createNotFoundException();
        }

        $conversation = new Conversation()
            ->setName($this->translator->trans('entity.new_conversation'))
            ->setUser($user)
            ->setProject($project);

        $this->entityManager->persist($conversation);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new CreateConversationEvent($conversation), Events::CONVERSATION_CREATE);

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
    public function edit(Request $request, Conversation $conversation): Response
    {
        $form = $this->createForm(ConversationType::class, $conversation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->eventDispatcher->dispatch(new EditConversationEvent($conversation), Events::CONVERSATION_EDIT);
            $this->addFlash('success', new TranslatableMessage('flash.conversation_updated'));

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
        $this->eventDispatcher->dispatch(new DeleteConversationEvent($conversation), Events::CONVERSATION_DELETE);

        $entityManager->remove($conversation);
        $entityManager->flush();

        $this->addFlash('success', new TranslatableMessage('flash.conversation_deleted'));

        return $this->redirectToRoute('app_homepage_detail');
    }
}
