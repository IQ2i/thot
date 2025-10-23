<?php

namespace App\Twig\Components\Conversation;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Enum\MessageType;
use App\Event\UserAskEvent;
use App\Events;
use App\Message\UserQuestionMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
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
    public function ask(EntityManagerInterface $entityManager, MessageBusInterface $bus, EventDispatcherInterface $eventDispatcher, LocaleSwitcher $localeSwitcher): void
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

        $eventDispatcher->dispatch(new UserAskEvent($this->question, $this->conversation), Events::AGENT_ASK);

        $bus->dispatch(new UserQuestionMessage(
            locale: $localeSwitcher->getLocale(),
            question: $this->question,
            messageId: $assistantMessage->getId(),
            firstMessage: $firstMessage,
        ));

        $this->question = null;
    }
}
