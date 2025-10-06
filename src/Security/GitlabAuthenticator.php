<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Omines\OAuth2\Client\Provider\GitlabResourceOwner;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class GitlabAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly RouterInterface $router,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return 'app_security_gitlab_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('gitlab');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var GitlabResourceOwner $gitlabUser */
                $gitlabUser = $client->fetchUserFromToken($accessToken);
                $username = $gitlabUser->getUsername();
                $email = $gitlabUser->getEmail();

                $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['gitlabId' => $gitlabUser->getId()]);
                if (null !== $existingUser) {
                    return $existingUser;
                }

                $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                if (null === $user) {
                    $password = bin2hex(random_bytes(16));

                    $user = new User();
                    $user->setEmail($email);
                    $user->setUsername($username);
                    $user->setPassword($this->passwordHasher->hashPassword($user, $password));
                    $this->entityManager->persist($user);
                }

                $user->setGitlabId((string) $gitlabUser->getId());
                $this->entityManager->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $targetUrl = $this->router->generate('app_homepage_detail');

        return new RedirectResponse($targetUrl);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new Response($message, Response::HTTP_FORBIDDEN);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse(
            $this->router->generate('app_security_login'),
            Response::HTTP_TEMPORARY_REDIRECT
        );
    }
}
