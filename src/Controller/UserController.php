<?php

namespace App\Controller;

use App\Entity\User;
use App\Event\CreateUserEvent;
use App\Event\DeleteUserEvent;
use App\Event\EditUserEvent;
use App\Events;
use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route('/users', name: 'app_user_')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $this->entityManager->getRepository(User::class)->findAll(),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $user = new User();

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->eventDispatcher->dispatch(new CreateUserEvent($user), Events::USER_CREATE);
            $this->addFlash('success', 'User created');

            return $this->redirectToRoute('app_user_edit', ['id' => $user->getId()]);
        }

        return $this->render('user/edit.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'edit')]
    public function edit(Request $request, User $user): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->eventDispatcher->dispatch(new EditUserEvent($user), Events::USER_EDIT);
            $this->addFlash('success', 'User updated');

            return $this->redirectToRoute('app_user_edit', ['id' => $user->getId()]);
        }

        return $this->render('user/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/delete/{id<\d+>}', name: 'delete')]
    public function delete(User $user): Response
    {
        $this->eventDispatcher->dispatch(new DeleteUserEvent($user), Events::USER_DELETE);

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->addFlash('success', 'User deleted');

        return $this->redirectToRoute('app_user_index');
    }
}
