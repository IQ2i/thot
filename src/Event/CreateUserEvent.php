<?php

namespace App\Event;

use App\Entity\User;

final class CreateUserEvent extends ActionEvent
{
    public function getContent(): string
    {
        /** @var User $user */
        $user = $this->getEntity();

        return \sprintf('%s creates user "%s" (#%d)', '%s', $user->getUsername(), $user->getId());
    }
}
