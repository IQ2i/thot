<?php

namespace App\Entity;

use App\Enum\SourceType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'type', enumType: SourceType::class)]
#[ORM\DiscriminatorMap([
    SourceType::GITLAB->value => Gitlab::class,
    SourceType::GOOGLE_DOC->value => GoogleDoc::class,
    SourceType::REDMINE->value => Redmine::class,
])]
abstract class Source implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $lastUpdatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'sources')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

    /**
     * @var Collection<int, Document>
     */
    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'source', orphanRemoval: true)]
    private Collection $documents;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLastUpdatedAt(): ?\DateTime
    {
        return $this->lastUpdatedAt;
    }

    public function setLastUpdatedAt(?\DateTime $lastUpdatedAt): static
    {
        $this->lastUpdatedAt = $lastUpdatedAt;

        return $this;
    }

    abstract public function getType(): ?SourceType;

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setSource($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            // set the owning side to null (unless already changed)
            if ($document->getSource() === $this) {
                $document->setSource(null);
            }
        }

        return $this;
    }

    abstract public function __toString(): string;
}
