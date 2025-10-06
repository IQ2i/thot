<?php

namespace App\Twig\Components\Conversation;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Enum\MessageType;
use App\Message\UserQuestionMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class Feed
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public Conversation $conversation;

    #[LiveProp(writable: true)]
    public ?string $question = null;

    #[LiveAction]
    public function ask(EntityManagerInterface $entityManager, MessageBusInterface $bus): void
    {
        $firstMessage = $this->conversation->getMessages()->isEmpty();

        $userMessage = new Message()
            ->setType(MessageType::USER)
            ->setContent($this->question);
        $this->conversation->addMessage($userMessage);

        $assistantMessage = new Message()
            ->setType(MessageType::ASSISTANT);
        $this->conversation->addMessage($assistantMessage);

        $entityManager->flush();

        $bus->dispatch(new UserQuestionMessage(
            question: $this->question,
            messageId: $assistantMessage->getId(),
            firstMessage: $firstMessage,
        ));

        $this->question = null;
    }
}
