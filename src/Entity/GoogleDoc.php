<?php

namespace App\Entity;

use App\Enum\SourceType;
use App\Repository\GoogleDocRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GoogleDocRepository::class)]
class GoogleDoc extends Source
{
    #[ORM\Column(length: 255)]
    private ?string $url = null;

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getType(): ?SourceType
    {
        return SourceType::GOOGLE_DOC;
    }

    public function __toString(): string
    {
        return 'Google Doc';
    }
}
