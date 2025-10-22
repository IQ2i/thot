<?php

namespace App\Event;

use App\Entity\User;

final class EditUserEvent extends ActionEvent
{
    public function getContent(): string
    {
        /** @var User $user */
        $user = $this->getEntity();

        return \sprintf('%s edits user "%s" (#%d)', '%s', $user->getUsername(), $user->getId());
    }
}
