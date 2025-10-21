<?php

namespace App\Entity;

use App\Repository\ScheduleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ScheduleRepository::class)]
class Schedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'schedules')]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $am_start = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $am_end = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $pm_start = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $pm_end = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

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

    public function getAmStart(): ?\DateTime
    {
        return $this->am_start;
    }

    public function setAmStart(?\DateTime $am_start): static
    {
        $this->am_start = $am_start;

        return $this;
    }

    public function getAmEnd(): ?\DateTime
    {
        return $this->am_end;
    }

    public function setAmEnd(?\DateTime $am_end): static
    {
        $this->am_end = $am_end;

        return $this;
    }

    public function getPmStart(): ?\DateTime
    {
        return $this->pm_start;
    }

    public function setPmStart(?\DateTime $pm_start): static
    {
        $this->pm_start = $pm_start;

        return $this;
    }

    public function getPmEnd(): ?\DateTime
    {
        return $this->pm_end;
    }

    public function setPmEnd(?\DateTime $pm_end): static
    {
        $this->pm_end = $pm_end;

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
