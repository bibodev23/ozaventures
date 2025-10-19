<?php

namespace App\Entity;

use App\Enum\AgeGroup;
use App\Repository\KidRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KidRepository::class)]
class Kid
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $firstname = null;

    #[ORM\Column(length: 255)]
    private ?string $lastname = null;

    #[ORM\Column]
    private ?int $age = null;

    /**
     * @var Collection<int, Outing>
     */
    #[ORM\ManyToMany(targetEntity: Outing::class, mappedBy: 'kids')]
    private Collection $outings;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'string', enumType: AgeGroup::class)]
    private ?AgeGroup $ageGroup = null;

    public function __construct()
    {
        $this->outings = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(int $age): static
    {
        $this->age = $age;

        return $this;
    }

    /**
     * @return Collection<int, Outing>
     */
    public function getOutings(): Collection
    {
        return $this->outings;
    }

    public function addOuting(Outing $outing): static
    {
        if (!$this->outings->contains($outing)) {
            $this->outings->add($outing);
            $outing->addKid($this);
        }

        return $this;
    }

    public function removeOuting(Outing $outing): static
    {
        if ($this->outings->removeElement($outing)) {
            $outing->removeKid($this);
        }

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

    public function getAgeGroup(): AgeGroup
    {
        return $this->ageGroup;
    }

    public function setAgeGroup(AgeGroup $ageGroup): static
    {
        $this->ageGroup = $ageGroup;
        return $this;
    }
    public function getAgeGroupLabel(): string
    {
        return $this->ageGroup?->getLabel();
    }


    public function assignAgeGroup(): void
    {
        if ($this->age === null) {
            return;
        }

        $this->ageGroup = match (true) {
            $this->age >= 3 && $this->age <= 5 => AgeGroup::BABIES,
            $this->age >= 6 && $this->age <= 12 => AgeGroup::CHILDREN,
            default => throw new \InvalidArgumentException("L'âge doit être entre 3 et 12 ans"),
        };
    }

}