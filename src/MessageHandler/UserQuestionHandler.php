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
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
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
        private TranslatorInterface $translator,
        private LocaleSwitcher $localeSwitcher,
    ) {
    }

    public function __invoke(UserQuestionMessage $userQuestion): void
    {
        $this->localeSwitcher->setLocale($userQuestion->locale);

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
            $response = '';
            $result = $this->aiManager->ask($message->getConversation());
            foreach ($result->getContent() as $word) {
                switch ($word) {
                    case '[REASONING]':
                        $this->hub->publish(new Update(
                            'conversation#'.$message->getConversation()->getId(),
                            $this->twig->render('conversation/waiting_agent.stream.html.twig', [
                                'id' => $message->getId(),
                                'content' => new TranslatableMessage('agent.reasoning'),
                            ])
                        ));
                        break;

                    case '[TOOL_CALLS]':
                        $this->hub->publish(new Update(
                            'conversation#'.$message->getConversation()->getId(),
                            $this->twig->render('conversation/waiting_agent.stream.html.twig', [
                                'id' => $message->getId(),
                                'content' => new TranslatableMessage('agent.searching_document'),
                            ])
                        ));
                        break;

                    default:
                        $response .= $word;
                        $this->hub->publish(new Update(
                            'conversation#'.$message->getConversation()->getId(),
                            $this->twig->render('conversation/agent_response.stream.html.twig', [
                                'id' => $message->getId(),
                                'content' => $response,
                            ])
                        ));
                        break;
                }
            }
        } catch (\Throwable $e) {
            $error = true;
            $response = $this->translator->trans('agent.error_occurred', ['%message%' => $e->getMessage()]);
            $this->hub->publish(new Update(
                'conversation#'.$message->getConversation()->getId(),
                $this->twig->render('conversation/agent_response.stream.html.twig', [
                    'id' => $message->getId(),
                    'content' => $response,
                ])
            ));
        }

        $message->setCreatedAt(new \DateTime());
        $message->setContent($response);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new AgentResponseEvent($message->getContent(), $message->getConversation(), $error), Events::AGENT_RESPONSE);
    }
}
