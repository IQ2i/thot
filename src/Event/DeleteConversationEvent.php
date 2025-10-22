<?php

namespace App\Event;

use App\Entity\Conversation;

final class DeleteConversationEvent extends ActionEvent
{
    public function getContent(): string
    {
        /** @var Conversation $conversation */
        $conversation = $this->getEntity();

        return \sprintf('%s deletes conversation "%s" (#%d)', '%s', $conversation->getName(), $conversation->getId());
    }
}
