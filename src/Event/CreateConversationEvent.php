<?php

namespace App\Event;

use App\Entity\Conversation;

final class CreateConversationEvent extends ActionEvent
{
    public function getContent(): string
    {
        /** @var Conversation $conversation */
        $conversation = $this->getEntity();

        return \sprintf('%s creates conversation "%s" (#%d)', '%s', $conversation->getName(), $conversation->getId());
    }
}
