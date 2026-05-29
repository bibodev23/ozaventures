<?php

namespace App\Entity;

use App\Enum\DailyTaskType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'daily_task_assignment')]
#[ORM\UniqueConstraint(name: 'UNIQ_DAILY_TASK_SEASON_DAY_TASK', columns: ['season_id', 'task_date', 'task_type'])]
class DailyTaskAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    private \DateTimeImmutable $taskDate;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    private string $taskType = DailyTaskType::MorningCare->value;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(targetEntity: Season::class, inversedBy: 'dailyTaskAssignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Season $season = null;

    /**
     * @var Collection<int, Animator>
     */
    #[ORM\ManyToMany(targetEntity: Animator::class, inversedBy: 'dailyTaskAssignments')]
    #[ORM\JoinTable(name: 'daily_task_assignment_animator')]
    private Collection $animators;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->taskDate = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->animators = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTaskDate(): \DateTimeImmutable
    {
        return $this->taskDate;
    }

    public function setTaskDate(\DateTimeInterface $taskDate): self
    {
        $this->taskDate = \DateTimeImmutable::createFromInterface($taskDate)->setTime(0, 0);

        return $this;
    }

    public function getTaskType(): string
    {
        return $this->taskType;
    }

    public function setTaskType(DailyTaskType|string $taskType): self
    {
        $this->taskType = $taskType instanceof DailyTaskType ? $taskType->value : $taskType;

        return $this;
    }

    public function getTask(): DailyTaskType
    {
        return DailyTaskType::from($this->taskType);
    }

    public function getTaskLabel(): string
    {
        return $this->getTask()->label();
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

    /**
     * @return Collection<int, Animator>
     */
    public function getAnimators(): Collection
    {
        return $this->animators;
    }

    public function addAnimator(Animator $animator): self
    {
        if (!$this->animators->contains($animator)) {
            $this->animators->add($animator);
        }

        return $this;
    }

    public function removeAnimator(Animator $animator): self
    {
        $this->animators->removeElement($animator);

        return $this;
    }

    public function clearAnimators(): self
    {
        $this->animators->clear();

        return $this;
    }

    public function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
