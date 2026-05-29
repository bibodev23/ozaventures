<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'outing_location_ping')]
#[ORM\UniqueConstraint(name: 'UNIQ_OUTING_LOCATION_OUTING_ANIMATOR', columns: ['outing_id', 'animator_id'])]
#[ORM\Index(name: 'IDX_OUTING_LOCATION_OUTING', columns: ['outing_id'])]
#[ORM\Index(name: 'IDX_OUTING_LOCATION_ANIMATOR', columns: ['animator_id'])]
class OutingLocationPing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Outing::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Outing $outing = null;

    #[ORM\ManyToOne(targetEntity: Animator::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Animator $animator = null;

    #[ORM\Column]
    #[Assert\Range(min: -90, max: 90)]
    private float $latitude = 0.0;

    #[ORM\Column]
    #[Assert\Range(min: -180, max: 180)]
    private float $longitude = 0.0;

    #[ORM\Column(nullable: true)]
    private ?float $accuracy = null;

    #[ORM\Column(nullable: true)]
    private ?float $heading = null;

    #[ORM\Column(nullable: true)]
    private ?float $speed = null;

    #[ORM\Column]
    private \DateTimeImmutable $recordedAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->recordedAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOuting(): ?Outing
    {
        return $this->outing;
    }

    public function setOuting(?Outing $outing): self
    {
        $this->outing = $outing;

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

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): self
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getAccuracy(): ?float
    {
        return $this->accuracy;
    }

    public function setAccuracy(?float $accuracy): self
    {
        $this->accuracy = $accuracy;

        return $this;
    }

    public function getHeading(): ?float
    {
        return $this->heading;
    }

    public function setHeading(?float $heading): self
    {
        $this->heading = $heading;

        return $this;
    }

    public function getSpeed(): ?float
    {
        return $this->speed;
    }

    public function setSpeed(?float $speed): self
    {
        $this->speed = $speed;

        return $this;
    }

    public function getRecordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function setRecordedAt(\DateTimeInterface $recordedAt): self
    {
        $this->recordedAt = \DateTimeImmutable::createFromInterface($recordedAt);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
