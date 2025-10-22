<?php

namespace App\Event;

use App\Entity\Project;

final class DeleteProjectEvent extends ActionEvent
{
    public function getContent(): string
    {
        /** @var Project $project */
        $project = $this->getEntity();

        return \sprintf('%s deletes project "%s" (#%d)', '%s', $project->getName(), $project->getId());
    }
}
