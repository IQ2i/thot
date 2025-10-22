<?php

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

abstract class ActionEvent extends Event
{
    public function __construct(
        private readonly mixed $entity,
    ) {
    }

    public function getEntity(): mixed
    {
        return $this->entity;
    }

    abstract public function getContent(): string;
}
