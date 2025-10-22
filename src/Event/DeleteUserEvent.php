<?php

namespace App\Event;

use App\Entity\User;

final class DeleteUserEvent extends ActionEvent
{
    public function getContent(): string
    {
        /** @var User $user */
        $user = $this->getEntity();

        return \sprintf('%s deletes user "%s" (#%d)', '%s', $user->getUsername(), $user->getId());
    }
}
