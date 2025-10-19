<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[UniqueEntity(fields: ['username'], message: 'There is already an account with this username')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $username = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    /**
     * @var Collection<int, Outing>
     */
    #[ORM\ManyToMany(targetEntity: Outing::class, mappedBy: 'animators')]
    private Collection $outings;

    /**
     * @var Collection<int, Task>
     */
    #[ORM\ManyToMany(targetEntity: Task::class, mappedBy: 'users', cascade: ['persist', 'remove'])]
    private Collection $tasks;

    /**
     * @var Collection<int, Outing>
     */
    #[ORM\OneToMany(targetEntity: Outing::class, mappedBy: 'created_by')]
    private Collection $outings_created;

    public function __construct()
    {
        $this->outings = new ArrayCollection();
        $this->tasks = new ArrayCollection();
        $this->outings_created = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
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
            $outing->addAnimator($this);
        }

        return $this;
    }

    public function removeOuting(Outing $outing): static
    {
        if ($this->outings->removeElement($outing)) {
            $outing->removeAnimator($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(Task $task): static
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->addUser($this);
        }

        return $this;
    }

    public function removeTask(Task $task): static
    {
        if ($this->tasks->removeElement($task)) {
            // set the owning side to null (unless already changed)
            if ($task->getUsers() === $this) {
                $task->removeUser($this);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Outing>
     */
    public function getOutingsCreated(): Collection
    {
        return $this->outings_created;
    }

    public function addOutingsCreated(Outing $outingsCreated): static
    {
        if (!$this->outings_created->contains($outingsCreated)) {
            $this->outings_created->add($outingsCreated);
            $outingsCreated->setCreatedBy($this);
        }

        return $this;
    }

    public function removeOutingsCreated(Outing $outingsCreated): static
    {
        if ($this->outings_created->removeElement($outingsCreated)) {
            // set the owning side to null (unless already changed)
            if ($outingsCreated->getCreatedBy() === $this) {
                $outingsCreated->setCreatedBy(null);
            }
        }

        return $this;
    }
}
