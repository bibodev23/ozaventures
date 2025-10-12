<?php

namespace App\Entity;

use App\Repository\OutingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OutingRepository::class)]
class Outing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    /**
     * @var Collection<int, Kid>
     */
    #[ORM\ManyToMany(targetEntity: Kid::class, inversedBy: 'outings')]
    private Collection $kids;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'outings')]
    private Collection $animators;

    #[ORM\Column]
    private ?\DateTime $date = null;

    #[ORM\Column(length: 255)]
    private ?string $location = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTime $timeGo = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTime $timeBack = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->kids = new ArrayCollection();
        $this->animators = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return Collection<int, Kid>
     */
    public function getKids(): Collection
    {
        return $this->kids;
    }

    public function addKid(Kid $kid): static
    {
        if (!$this->kids->contains($kid)) {
            $this->kids->add($kid);
        }

        return $this;
    }

    public function removeKid(Kid $kid): static
    {
        $this->kids->removeElement($kid);

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getAnimators(): Collection
    {
        return $this->animators;
    }

    public function addAnimator(User $animator): static
    {
        if (!$this->animators->contains($animator)) {
            $this->animators->add($animator);
        }

        return $this;
    }

    public function removeAnimator(User $animator): static
    {
        $this->animators->removeElement($animator);

        return $this;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getTimeGo(): ?\DateTime
    {
        return $this->timeGo;
    }

    public function setTimeGo(\DateTime $timeGo): static
    {
        $this->timeGo = $timeGo;

        return $this;
    }

    public function getTimeBack(): ?\DateTime
    {
        return $this->timeBack;
    }

    public function setTimeBack(\DateTime $timeBack): static
    {
        $this->timeBack = $timeBack;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }
}
