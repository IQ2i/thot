<?php

namespace App\Event;

use App\Entity\Conversation;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AgentEvent extends Event
{
    public function __construct(
        private readonly string $message,
        private readonly Conversation $conversation,
        public readonly bool $error = false,
    ) {
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getConversation(): Conversation
    {
        return $this->conversation;
    }

    public function isError(): bool
    {
        return $this->error;
    }

    abstract public function getContent(): string;
}
