<?php

namespace App\Event;

final class AgentResponseEvent extends AgentEvent
{
    public function getContent(): string
    {
        return \sprintf('Agent respond in conversation "%s" (#%d)', $this->getConversation()->getName(), $this->getConversation()->getId());
    }
}
