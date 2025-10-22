<?php

namespace App\MessageHandler;

use App\AI\AiManager;
use App\Entity\Message;
use App\Event\AgentResponseEvent;
use App\Events;
use App\Message\UserQuestionMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

#[AsMessageHandler]
final readonly class UserQuestionHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
        private HubInterface $hub,
        private Environment $twig,
        private AiManager $aiManager,
    ) {
    }

    public function __invoke(UserQuestionMessage $userQuestion): void
    {
        $message = $this->entityManager->find(Message::class, $userQuestion->messageId);

        if ($userQuestion->firstMessage) {
            try {
                $result = $this->aiManager->generateConversationName($userQuestion->question);
                $name = $result->getContent();
                $message->getConversation()->setName($name);

                $this->hub->publish(new Update(
                    'conversation#'.$message->getConversation()->getId(),
                    $this->twig->render('conversation/name.stream.html.twig', ['conversation' => $message->getConversation()])
                ));
            } catch (\Throwable) {
            }
        }

        $error = false;
        try {
            $result = $this->aiManager->ask($message->getConversation());
            $message->setContent($result->getContent());
        } catch (\Throwable $e) {
            $error = true;
            $message->setContent('An error occurred: '.$e->getMessage());
        }

        $message->setCreatedAt(new \DateTime());
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new AgentResponseEvent($message->getContent(), $message->getConversation(), $error), Events::AGENT_RESPONSE);
        $this->hub->publish(new Update(
            'conversation#'.$message->getConversation()->getId(),
            $this->twig->render('conversation/message.stream.html.twig', ['message' => $message])
        ));
    }
}
