<?php

namespace App\Entity;

use App\Enum\SourceType;
use App\Repository\GitlabRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GitlabRepository::class)]
class Gitlab extends Source
{
    #[ORM\Column(length: 255)]
    private ?string $projectUrl = null;

    #[ORM\Column(length: 255)]
    private ?string $accessToken = null;

    public function getProjectUrl(): ?string
    {
        return $this->projectUrl;
    }

    public function setProjectUrl(string $projectUrl): static
    {
        $this->projectUrl = $projectUrl;

        return $this;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $accessToken): static
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getType(): ?SourceType
    {
        return SourceType::GITLAB;
    }

    public function __toString(): string
    {
        return 'GitLab';
    }
}
