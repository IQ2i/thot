<?php

namespace App\Entity;

use App\Enum\LogLevel;
use App\Repository\LogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LogRepository::class)]
class Log
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: LogLevel::class)]
    private ?LogLevel $level = null;

    #[ORM\Column(length: 255)]
    private ?string $message = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $additionalContent = null;

    #[ORM\Column]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLevel(): ?LogLevel
    {
        return $this->level;
    }

    public function setLevel(LogLevel $level): static
    {
        $this->level = $level;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getAdditionalContent(): ?string
    {
        return $this->additionalContent;
    }

    public function setAdditionalContent(?string $additionalContent): static
    {
        $this->additionalContent = $additionalContent;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
