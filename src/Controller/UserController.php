<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/users', name: 'app_user_')]
class UserController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $entityManager->getRepository(User::class)->findAll(),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = new User();

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'User created');

            return $this->redirectToRoute('app_user_edit', ['id' => $user->getId()]);
        }

        return $this->render('user/edit.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'edit')]
    public function edit(Request $request, EntityManagerInterface $entityManager, User $user): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'User updated');

            return $this->redirectToRoute('app_user_edit', ['id' => $user->getId()]);
        }

        return $this->render('user/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/delete/{id<\d+>}', name: 'delete')]
    public function delete(EntityManagerInterface $entityManager, User $user): Response
    {
        $entityManager->remove($user);
        $entityManager->flush();

        $this->addFlash('success', 'User deleted');

        return $this->redirectToRoute('app_user_index');
    }
}
