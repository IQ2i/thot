<?php

declare(strict_types=1);

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/security', name: 'app_security_')]
class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'logout')]
    public function logout(): never
    {
        throw new \Exception();
    }

    #[Route('/gitlab/redirect', name: 'gitlab_redirect')]
    public function connectRedirect(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry
            ->getClient('gitlab')
            ->redirect(['email', 'profile', 'read_user'], []);
    }

    #[Route('/gitlab/check', name: 'gitlab_check')]
    public function connectCheck(): never
    {
        throw new \Exception();
    }
}
