<?php

namespace App\Twig\Components\Layout\Menu;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsTwigComponent]
readonly class Conversations
{
    public function __construct(
        private Security $security,
        private RouterInterface $router,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return array<int, mixed>
     */
    #[ExposeInTemplate]
    public function getProjects(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $pathInfo = $request->getPathInfo();

        /** @var User $user */
        $user = $this->security->getUser();

        $projects = [];
        foreach ($user->getConversations() as $conversation) {
            if (!isset($projects[$conversation->getProject()->getId()])) {
                $projects[$conversation->getProject()->getId()] = [
                    'id' => $conversation->getProject()->getId(),
                    'name' => $conversation->getProject()->getName(),
                    'opened' => false,
                    'conversations' => [],
                ];
            }

            $url = $this->router->generate('app_conversation_detail', ['id' => $conversation->getId()]);
            $active = $pathInfo === $url;

            $projects[$conversation->getProject()->getId()]['conversations'][$conversation->getId()] = [
                'id' => $conversation->getId(),
                'name' => $conversation->getName(),
                'url' => $url,
                'active' => $active,
            ];
            $projects[$conversation->getProject()->getId()]['opened'] = $projects[$conversation->getProject()->getId()]['opened'] || $active;
        }

        return $projects;
    }
}
