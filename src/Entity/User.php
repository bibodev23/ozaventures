<?php

namespace App\Entity;

use App\Enum\UserRole;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
#[UniqueEntity(fields: ['username'], message: 'Cet identifiant est déjà utilisé.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    #[Assert\Regex(pattern: '/^[a-z0-9._-]+$/i', message: 'Utilise seulement des lettres, chiffres, points, tirets ou underscores.')]
    private string $username = '';

    #[ORM\Column(length: 255)]
    private string $passwordHash = '';

    #[ORM\Column(length: 20)]
    private string $role = 'animator';

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private string $firstName = '';

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private string $lastName = '';

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private bool $mustChangePassword = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: Animator::class)]
    private ?Animator $animator = null;

    /**
     * @var Collection<int, ApiToken>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ApiToken::class)]
    private Collection $apiTokens;

    /**
     * @var Collection<int, MobileDeviceToken>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: MobileDeviceToken::class)]
    private Collection $mobileDeviceTokens;

    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(mappedBy: 'sender', targetEntity: Message::class)]
    private Collection $sentMessages;

    /**
     * @var Collection<int, MessageRecipient>
     */
    #[ORM\OneToMany(mappedBy: 'recipient', targetEntity: MessageRecipient::class)]
    private Collection $messageRecipients;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->apiTokens = new ArrayCollection();
        $this->mobileDeviceTokens = new ArrayCollection();
        $this->sentMessages = new ArrayCollection();
        $this->messageRecipients = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = strtolower(trim($username));

        return $this->touch();
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        if (!$this->active) {
            return [];
        }

        return ['ROLE_USER', $this->getRole()->securityRole()];
    }

    public function getRole(): UserRole
    {
        return UserRole::tryFrom($this->role) ?? UserRole::Animator;
    }

    public function setRole(UserRole|string $role): self
    {
        if (is_string($role)) {
            $role = UserRole::from($role);
        }

        $this->role = $role->value;

        return $this->touch();
    }

    public function isDirector(): bool
    {
        return $this->getRole() === UserRole::Director;
    }

    public function isAnimator(): bool
    {
        return $this->getRole() === UserRole::Animator;
    }

    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;

        return $this->touch();
    }

    public function eraseCredentials(): void
    {
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = trim($firstName);

        return $this->touch();
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = trim($lastName);

        return $this->touch();
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getDisplayName(): string
    {
        return $this->getFullName() !== '' ? $this->getFullName() : $this->username;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this->touch();
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

        return $this->touch();
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

    public function getAnimator(): ?Animator
    {
        return $this->animator;
    }

    public function setAnimator(?Animator $animator): self
    {
        $this->animator = $animator;

        return $this;
    }

    /**
     * @return Collection<int, MessageRecipient>
     */
    public function getMessageRecipients(): Collection
    {
        return $this->messageRecipients;
    }
}
