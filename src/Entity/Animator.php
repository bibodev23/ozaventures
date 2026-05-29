<?php

namespace App\Entity;

use App\Enum\AgeGroup;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'animator')]
#[UniqueEntity(fields: ['username'], message: 'Cet identifiant est déjà utilisé.')]
class Animator implements UserInterface, PasswordAuthenticatedUserInterface
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

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(max: 30)]
    private ?string $phone = null;

    #[ORM\Column(length: 12, nullable: true)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [AgeGroup::Little->value, AgeGroup::Big->value])]
    private ?string $ageGroup = null;

    #[ORM\Column(length: 80, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    #[Assert\Regex(pattern: '/^[a-z0-9._-]+$/i', message: 'Utilise seulement des lettres, chiffres, points, tirets ou underscores.')]
    private string $username = '';

    #[ORM\Column(length: 255)]
    private string $passwordHash = '';

    #[ORM\Column]
    private bool $mustChangePassword = true;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'animator')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    /**
     * @var Collection<int, Outing>
     */
    #[ORM\ManyToMany(targetEntity: Outing::class, mappedBy: 'animators')]
    private Collection $outings;

    /**
     * @var Collection<int, DailyTaskAssignment>
     */
    #[ORM\ManyToMany(targetEntity: DailyTaskAssignment::class, mappedBy: 'animators')]
    private Collection $dailyTaskAssignments;

    /**
     * @var Collection<int, AnimatorWorkShift>
     */
    #[ORM\OneToMany(mappedBy: 'animator', targetEntity: AnimatorWorkShift::class)]
    private Collection $workShifts;

    /**
     * @var Collection<int, ApiToken>
     */
    #[ORM\OneToMany(mappedBy: 'animator', targetEntity: ApiToken::class)]
    private Collection $apiTokens;

    /**
     * @var Collection<int, MobileDeviceToken>
     */
    #[ORM\OneToMany(mappedBy: 'animator', targetEntity: MobileDeviceToken::class)]
    private Collection $mobileDeviceTokens;

    /**
     * @var Collection<int, Outing>
     */
    #[ORM\OneToMany(mappedBy: 'createdBy', targetEntity: Outing::class)]
    private Collection $createdOutings;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->outings = new ArrayCollection();
        $this->dailyTaskAssignments = new ArrayCollection();
        $this->workShifts = new ArrayCollection();
        $this->apiTokens = new ArrayCollection();
        $this->mobileDeviceTokens = new ArrayCollection();
        $this->createdOutings = new ArrayCollection();
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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $phone = $phone !== null ? trim($phone) : null;
        $this->phone = $phone !== '' ? $phone : null;

        return $this;
    }

    public function getAgeGroup(): ?string
    {
        return $this->ageGroup;
    }

    public function setAgeGroup(?string $ageGroup): self
    {
        $ageGroup = $ageGroup !== null ? trim($ageGroup) : null;
        $this->ageGroup = $ageGroup !== '' ? $ageGroup : null;

        return $this;
    }

    public function getAgeGroupLabel(): string
    {
        if ($this->ageGroup === null) {
            return 'Groupe à préciser';
        }

        return AgeGroup::from($this->ageGroup)->label();
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = strtolower(trim($username));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        return $this->active ? ['ROLE_ANIMATOR'] : [];
    }

    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function mustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    public function getMustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    public function setMustChangePassword(bool $mustChangePassword): self
    {
        $this->mustChangePassword = $mustChangePassword;

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

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getPlanningLabel(): string
    {
        return sprintf('%s (%s)', $this->getFullName(), $this->getAgeGroupLabel());
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        if ($user instanceof User && $user->getAnimator() !== $this) {
            $user->setAnimator($this);
        }

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

    /**
     * @return Collection<int, ApiToken>
     */
    public function getApiTokens(): Collection
    {
        return $this->apiTokens;
    }

    /**
     * @return Collection<int, MobileDeviceToken>
     */
    public function getMobileDeviceTokens(): Collection
    {
        return $this->mobileDeviceTokens;
    }

    /**
     * @return Collection<int, Outing>
     */
    public function getCreatedOutings(): Collection
    {
        return $this->createdOutings;
    }
}
