<?php

namespace App\Entity;

use App\Enum\AgeGroup;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'child')]
class Child
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private string $firstName = '';

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private string $lastName = '';

    #[ORM\Column(length: 12)]
    #[Assert\Choice(choices: [AgeGroup::Little->value, AgeGroup::Big->value])]
    private string $ageGroup = AgeGroup::Little->value;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 3, max: 12, notInRangeMessage: "L'âge doit être compris entre {{ min }} et {{ max }} ans.")]
    private ?int $age = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $legalGuardians = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $legalGuardianPhones = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $allergies = null;

    #[ORM\Column]
    private bool $photoPermission = false;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1500)]
    private ?string $importantNotes = null;

    #[ORM\ManyToOne(targetEntity: Season::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Season $season = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, Outing>
     */
    #[ORM\ManyToMany(targetEntity: Outing::class, mappedBy: 'children')]
    private Collection $outings;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->outings = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = trim($firstName);

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = trim($lastName);

        return $this;
    }

    public function getAgeGroup(): string
    {
        return $this->ageGroup;
    }

    public function setAgeGroup(string $ageGroup): self
    {
        $this->ageGroup = $ageGroup;

        return $this;
    }

    public function getAgeGroupLabel(): string
    {
        return AgeGroup::from($this->ageGroup)->label();
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(?int $age): self
    {
        $this->age = $age;

        return $this;
    }

    public function getLegalGuardians(): ?string
    {
        return $this->legalGuardians;
    }

    public function setLegalGuardians(?string $legalGuardians): self
    {
        $legalGuardians = $legalGuardians !== null ? trim($legalGuardians) : null;
        $this->legalGuardians = $legalGuardians !== '' ? $legalGuardians : null;

        return $this;
    }

    public function getLegalGuardianPhones(): ?string
    {
        return $this->legalGuardianPhones;
    }

    public function setLegalGuardianPhones(?string $legalGuardianPhones): self
    {
        $legalGuardianPhones = $legalGuardianPhones !== null ? trim($legalGuardianPhones) : null;
        $this->legalGuardianPhones = $legalGuardianPhones !== '' ? $legalGuardianPhones : null;

        return $this;
    }

    public function getAllergies(): ?string
    {
        return $this->allergies;
    }

    public function setAllergies(?string $allergies): self
    {
        $allergies = $allergies !== null ? trim($allergies) : null;
        $this->allergies = $allergies !== '' ? $allergies : null;

        return $this;
    }

    public function hasAllergies(): bool
    {
        return $this->allergies !== null;
    }

    public function hasPhotoPermission(): bool
    {
        return $this->photoPermission;
    }

    public function setPhotoPermission(bool $photoPermission): self
    {
        $this->photoPermission = $photoPermission;

        return $this;
    }

    public function getImportantNotes(): ?string
    {
        return $this->importantNotes;
    }

    public function setImportantNotes(?string $importantNotes): self
    {
        $importantNotes = $importantNotes !== null ? trim($importantNotes) : null;
        $this->importantNotes = $importantNotes !== '' ? $importantNotes : null;

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

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getDirectoryLabel(): string
    {
        $ageLabel = $this->age !== null ? sprintf('%d ans', $this->age) : $this->getAgeGroupLabel();

        return sprintf('%s - %s', $this->getFullName(), $ageLabel);
    }

    /**
     * @return Collection<int, Outing>
     */
    public function getOutings(): Collection
    {
        return $this->outings;
    }
}
