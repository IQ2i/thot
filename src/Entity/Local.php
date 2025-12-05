<?php

namespace App\Entity;

use App\Enum\SourceType;
use App\Repository\LocalRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LocalRepository::class)]
class Local extends Source implements \Stringable
{
    public function getType(): ?SourceType
    {
        return SourceType::LOCAL;
    }

    public function __toString(): string
    {
        return 'Documents locaux';
    }
}
