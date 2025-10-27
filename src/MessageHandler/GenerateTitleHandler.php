<?php

namespace App\MessageHandler;

use App\AI\AiManager;
use App\Entity\Conversation;
use App\Message\GenerateTitleMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;

#[AsMessageHandler]
final readonly class GenerateTitleHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private HubInterface $hub,
        private Environment $twig,
        private AiManager $aiManager,
        private LocaleSwitcher $localeSwitcher,
    ) {
    }

    public function __invoke(GenerateTitleMessage $generateTitle): void
    {
        $this->localeSwitcher->setLocale($generateTitle->locale);

        $conversation = $this->entityManager->find(Conversation::class, $generateTitle->conversationId);

        try {
            $result = $this->aiManager->generateConversationName($generateTitle->question);
            $name = $result->getContent();
            $conversation->setName($name);

            $this->hub->publish(new Update(
                'conversation#'.$conversation->getId(),
                $this->twig->render('conversation/name.stream.html.twig', ['conversation' => $conversation])
            ));
        } catch (\Throwable) {
        }
    }
}
