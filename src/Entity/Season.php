<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'season')]
class Season
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $endsAt;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, Child>
     */
    #[ORM\OneToMany(mappedBy: 'season', targetEntity: Child::class)]
    private Collection $children;

    /**
     * @var Collection<int, Outing>
     */
    #[ORM\OneToMany(mappedBy: 'season', targetEntity: Outing::class)]
    private Collection $outings;

    /**
     * @var Collection<int, DailyTaskAssignment>
     */
    #[ORM\OneToMany(mappedBy: 'season', targetEntity: DailyTaskAssignment::class)]
    private Collection $dailyTaskAssignments;

    /**
     * @var Collection<int, AnimatorWorkShift>
     */
    #[ORM\OneToMany(mappedBy: 'season', targetEntity: AnimatorWorkShift::class)]
    private Collection $workShifts;

    public function __construct()
    {
        $this->startsAt = new \DateTimeImmutable('first day of july this year');
        $this->endsAt = new \DateTimeImmutable('last day of july this year');
        $this->createdAt = new \DateTimeImmutable();
        $this->children = new ArrayCollection();
        $this->outings = new ArrayCollection();
        $this->dailyTaskAssignments = new ArrayCollection();
        $this->workShifts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): self
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(\DateTimeImmutable $endsAt): self
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @return Collection<int, DailyTaskAssignment>
     */
    public function getDailyTaskAssignments(): Collection
    {
        return $this->dailyTaskAssignments;
    }

    /**
     * @return Collection<int, AnimatorWorkShift>
     */
    public function getWorkShifts(): Collection
    {
        return $this->workShifts;
    }
}
