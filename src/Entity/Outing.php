<?php

namespace App\Entity;

use App\Enum\OutingStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'outing')]
class Outing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    private string $number = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $destination = '';

    #[ORM\Column]
    #[Assert\NotNull]
    private \DateTimeImmutable $departureAt;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\GreaterThan(propertyPath: 'departureAt', message: "L'heure de retour doit être après le départ.")]
    private \DateTimeImmutable $returnAt;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private string $transportMode = '';

    #[ORM\Column]
    private bool $picnicRequired = false;

    #[ORM\Column(length: 20)]
    private string $status = OutingStatus::Pending->value;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $validationComment = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(targetEntity: Season::class, inversedBy: 'outings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Season $season = null;

    #[ORM\ManyToOne(targetEntity: Animator::class, inversedBy: 'createdOutings')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Animator $createdBy = null;

    /**
     * @var Collection<int, Child>
     */
    #[ORM\ManyToMany(targetEntity: Child::class, inversedBy: 'outings')]
    #[ORM\JoinTable(name: 'outing_child')]
    #[Assert\Count(min: 1, minMessage: 'Sélectionne au moins un enfant.')]
    private Collection $children;

    /**
     * @var Collection<int, Animator>
     */
    #[ORM\ManyToMany(targetEntity: Animator::class, inversedBy: 'outings')]
    #[ORM\JoinTable(name: 'outing_animator')]
    #[Assert\Count(min: 1, minMessage: 'Sélectionne au moins un animateur.')]
    private Collection $animators;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->departureAt = $now->setTime(9, 30);
        $this->returnAt = $now->setTime(16, 30);
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->children = new ArrayCollection();
        $this->animators = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): self
    {
        $this->number = trim($number);

        return $this;
    }

    public function getDestination(): string
    {
        return $this->destination;
    }

    public function setDestination(string $destination): self
    {
        $this->destination = trim($destination);

        return $this;
    }

    public function getDepartureAt(): \DateTimeImmutable
    {
        return $this->departureAt;
    }

    public function setDepartureAt(\DateTimeInterface $departureAt): self
    {
        $this->departureAt = \DateTimeImmutable::createFromInterface($departureAt);

        return $this;
    }

    public function getReturnAt(): \DateTimeImmutable
    {
        return $this->returnAt;
    }

    public function setReturnAt(\DateTimeInterface $returnAt): self
    {
        $this->returnAt = \DateTimeImmutable::createFromInterface($returnAt);

        return $this;
    }

    public function getTransportMode(): string
    {
        return $this->transportMode;
    }

    public function setTransportMode(string $transportMode): self
    {
        $this->transportMode = trim($transportMode);

        return $this;
    }

    public function isPicnicRequired(): bool
    {
        return $this->picnicRequired;
    }

    public function setPicnicRequired(bool $picnicRequired): self
    {
        $this->picnicRequired = $picnicRequired;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getStatusLabel(): string
    {
        return OutingStatus::from($this->status)->label();
    }

    public function getStatusBadgeClass(): string
    {
        return OutingStatus::from($this->status)->badgeClass();
    }

    public function getValidationComment(): ?string
    {
        return $this->validationComment;
    }

    public function setValidationComment(?string $validationComment): self
    {
        $validationComment = $validationComment !== null ? trim($validationComment) : null;
        $this->validationComment = $validationComment !== '' ? $validationComment : null;

        return $this;
    }

    public function getValidatedAt(): ?\DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?\DateTimeImmutable $validatedAt): self
    {
        $this->validatedAt = $validatedAt;

        return $this;
    }

    public function getSeason(): ?Season
    {
        return $this->season;
    }

    public function setSeason(?Season $season): self
    {
        $this->season = $season;

        return $this;
    }

    public function getCreatedBy(): ?Animator
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?Animator $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * @return Collection<int, Child>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(Child $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
        }

        return $this;
    }

    public function removeChild(Child $child): self
    {
        $this->children->removeElement($child);

        return $this;
    }

    /**
     * @return Collection<int, Animator>
     */
    public function getAnimators(): Collection
    {
        return $this->animators;
    }

    public function addAnimator(Animator $animator): self
    {
        if (!$this->animators->contains($animator)) {
            $this->animators->add($animator);
        }

        return $this;
    }

    public function removeAnimator(Animator $animator): self
    {
        $this->animators->removeElement($animator);

        return $this;
    }

    public function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
