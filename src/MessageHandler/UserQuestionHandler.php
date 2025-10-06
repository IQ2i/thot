<?php

namespace App\MessageHandler;

use App\AI\AiManager;
use App\Entity\Message;
use App\Message\UserQuestionMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Twig\Environment;

#[AsMessageHandler]
final readonly class UserQuestionHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private HubInterface $hub,
        private Environment $twig,
        private AiManager $aiManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(UserQuestionMessage $userQuestion): void
    {
        $message = $this->entityManager->find(Message::class, $userQuestion->messageId);

        if ($userQuestion->firstMessage) {
            try {
                $this->logger->info('Generate title for conversation', [
                    'conversation_id' => $message->getConversation()->getId(),
                ]);

                $result = $this->aiManager->generateConversationName($userQuestion->question);
                $name = $result->getContent();
                $message->getConversation()->setName($name);

                $this->hub->publish(new Update(
                    'conversation#'.$message->getConversation()->getId(),
                    $this->twig->render('conversation/name.stream.html.twig', ['conversation' => $message->getConversation()])
                ));
            } catch (\Throwable $e) {
                $this->logger->error('An error occurred during title generation', ['exception' => $e->getMessage()]);
            }
        }

        $response = '';
        try {
            $this->logger->info('Generate message for conversation', [
                'conversation_id' => $message->getConversation()->getId(),
            ]);

            $result = $this->aiManager->ask($message->getConversation());
            foreach ($result->getContent() as $word) {
                $response .= $word;
                $message->setContent($response);

                if ('' !== $message->getContent()) {
                    $this->logger->debug('New chunk of content received from LLM', [
                        'conversation_id' => $message->getConversation()->getId(),
                        'chunk' => $word,
                    ]);

                    $this->hub->publish(new Update(
                        'conversation#'.$message->getConversation()->getId(),
                        $this->twig->render('conversation/message.stream.html.twig', ['message' => $message])
                    ));
                }
            }
        } catch (\Throwable $e) {
            $message->setContent('An error occurred');
            $this->logger->error('An error occurred during message generation', ['exception' => $e->getMessage()]);

            $this->hub->publish(new Update(
                'conversation#'.$message->getConversation()->getId(),
                $this->twig->render('conversation/message.stream.html.twig', ['message' => $message])
            ));
        }

        $this->entityManager->flush();
    }
}
