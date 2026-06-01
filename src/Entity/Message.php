<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'message')]
class Message
{
    public const AUDIENCE_PRIVATE = 'private';
    public const AUDIENCE_ALL_ANIMATORS = 'all_animators';
    public const AUDIENCE_ALL_DIRECTORS = 'all_directors';
    public const AUDIENCE_EVERYONE = 'everyone';
    public const AUDIENCE_GROUP_LITTLE = 'group_little';
    public const AUDIENCE_GROUP_BIG = 'group_big';
    public const AUDIENCE_OUTING = 'outing';

    public const AUDIENCES = [
        self::AUDIENCE_PRIVATE,
        self::AUDIENCE_ALL_ANIMATORS,
        self::AUDIENCE_ALL_DIRECTORS,
        self::AUDIENCE_EVERYONE,
        self::AUDIENCE_GROUP_LITTLE,
        self::AUDIENCE_GROUP_BIG,
        self::AUDIENCE_OUTING,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'sentMessages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $sender = null;

    #[ORM\Column(length: 30)]
    #[Assert\Choice(choices: self::AUDIENCES)]
    private string $audience = self::AUDIENCE_PRIVATE;

    #[ORM\ManyToOne(targetEntity: Outing::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Outing $outing = null;

    #[ORM\Column(length: 160, nullable: true)]
    #[Assert\Length(max: 160)]
    private ?string $subject = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $body = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, MessageRecipient>
     */
    #[ORM\OneToMany(mappedBy: 'message', targetEntity: MessageRecipient::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $recipients;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->recipients = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(?User $sender): self
    {
        $this->sender = $sender;

        return $this;
    }

    public function getAudience(): string
    {
        return $this->audience;
    }

    public function setAudience(string $audience): self
    {
        $this->audience = $audience;

        return $this;
    }

    public function getAudienceLabel(): string
    {
        return match ($this->audience) {
            self::AUDIENCE_ALL_ANIMATORS => 'Tous les animateurs',
            self::AUDIENCE_ALL_DIRECTORS => 'Direction',
            self::AUDIENCE_EVERYONE => 'Toute l’équipe',
            self::AUDIENCE_GROUP_LITTLE => 'Animateurs 3-5 ans',
            self::AUDIENCE_GROUP_BIG => 'Animateurs 6-12 ans',
            self::AUDIENCE_OUTING => 'Équipe sortie',
            default => 'Message privé',
        };
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

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): self
    {
        $subject = $subject !== null ? trim($subject) : null;
        $this->subject = $subject !== '' ? $subject : null;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = trim($body);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, MessageRecipient>
     */
    public function getRecipients(): Collection
    {
        return $this->recipients;
    }

    public function addRecipient(User $user): self
    {
        foreach ($this->recipients as $recipient) {
            if ($recipient->getRecipient() === $user) {
                return $this;
            }
        }

        $this->recipients->add((new MessageRecipient())
            ->setMessage($this)
            ->setRecipient($user));

        return $this;
    }

    public function removeRecipient(MessageRecipient $recipient): self
    {
        if ($this->recipients->removeElement($recipient) && $recipient->getMessage() === $this) {
            $recipient->setMessage(null);
        }

        return $this;
    }
}
