<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'animator_work_shift')]
#[ORM\UniqueConstraint(name: 'UNIQ_WORK_SHIFT_SEASON_ANIMATOR_DAY', columns: ['season_id', 'animator_id', 'work_date'])]
class AnimatorWorkShift
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    private \DateTimeImmutable $workDate;

    #[ORM\Column(type: 'time_immutable')]
    #[Assert\NotNull]
    private \DateTimeImmutable $startTime;

    #[ORM\Column(type: 'time_immutable')]
    #[Assert\NotNull]
    private \DateTimeImmutable $lunchStartTime;

    #[ORM\Column(type: 'time_immutable')]
    #[Assert\NotNull]
    private \DateTimeImmutable $lunchEndTime;

    #[ORM\Column(type: 'time_immutable')]
    #[Assert\NotNull]
    private \DateTimeImmutable $endTime;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(targetEntity: Season::class, inversedBy: 'workShifts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Season $season = null;

    #[ORM\ManyToOne(targetEntity: Animator::class, inversedBy: 'workShifts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Animator $animator = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->workDate = $now->setTime(0, 0);
        $this->startTime = $now->setTime(7, 0);
        $this->lunchStartTime = $now->setTime(12, 0);
        $this->lunchEndTime = $now->setTime(13, 30);
        $this->endTime = $now->setTime(18, 0);
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorkDate(): \DateTimeImmutable
    {
        return $this->workDate;
    }

    public function setWorkDate(\DateTimeInterface $workDate): self
    {
        $this->workDate = \DateTimeImmutable::createFromInterface($workDate)->setTime(0, 0);

        return $this;
    }

    public function getStartTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): self
    {
        $this->startTime = \DateTimeImmutable::createFromInterface($startTime);

        return $this;
    }

    public function getLunchStartTime(): \DateTimeImmutable
    {
        return $this->lunchStartTime;
    }

    public function setLunchStartTime(\DateTimeInterface $lunchStartTime): self
    {
        $this->lunchStartTime = \DateTimeImmutable::createFromInterface($lunchStartTime);

        return $this;
    }

    public function getLunchEndTime(): \DateTimeImmutable
    {
        return $this->lunchEndTime;
    }

    public function setLunchEndTime(\DateTimeInterface $lunchEndTime): self
    {
        $this->lunchEndTime = \DateTimeImmutable::createFromInterface($lunchEndTime);

        return $this;
    }

    public function getEndTime(): \DateTimeImmutable
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeInterface $endTime): self
    {
        $this->endTime = \DateTimeImmutable::createFromInterface($endTime);

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

    public function getAnimator(): ?Animator
    {
        return $this->animator;
    }

    public function setAnimator(?Animator $animator): self
    {
        $this->animator = $animator;

        return $this;
    }

    public function getWorkedMinutes(): int
    {
        return $this->minutesBetween($this->startTime, $this->lunchStartTime)
            + $this->minutesBetween($this->lunchEndTime, $this->endTime);
    }

    public function getWorkedHoursLabel(): string
    {
        $minutes = $this->getWorkedMinutes();

        return sprintf('%dh%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    private function minutesBetween(\DateTimeInterface $start, \DateTimeInterface $end): int
    {
        return ((int) $end->format('H') * 60 + (int) $end->format('i'))
            - ((int) $start->format('H') * 60 + (int) $start->format('i'));
    }
}
