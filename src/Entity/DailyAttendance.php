<?php

namespace App\Entity;

use App\Repository\DailyAttendanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DailyAttendanceRepository::class)]
class DailyAttendance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'dailyAttendances')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Kid $kid = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column]
    private ?bool $morning = null;

    #[ORM\Column]
    private ?bool $canteen = null;

    #[ORM\Column]
    private ?bool $afternoon = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKid(): ?Kid
    {
        return $this->kid;
    }

    public function setKid(?Kid $kid): static
    {
        $this->kid = $kid;

        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function isMorning(): ?bool
    {
        return $this->morning;
    }

    public function setMorning(bool $morning): static
    {
        $this->morning = $morning;

        return $this;
    }

    public function isCanteen(): ?bool
    {
        return $this->canteen;
    }

    public function setCanteen(bool $canteen): static
    {
        $this->canteen = $canteen;

        return $this;
    }

    public function isAfternoon(): ?bool
    {
        return $this->afternoon;
    }

    public function setAfternoon(bool $afternoon): static
    {
        $this->afternoon = $afternoon;

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
