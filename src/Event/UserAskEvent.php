<?php

namespace App\Event;

final class UserAskEvent extends AgentEvent
{
    public function getContent(): string
    {
        return \sprintf('%s asks a question in conversation "%s" (#%d)', '%s', $this->getConversation()->getName(), $this->getConversation()->getId());
    }
}
