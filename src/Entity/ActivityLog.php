<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use DateTime;

#[ApiResource]
#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Table(name: 'activity_log')]
#[ORM\Index(columns: ['user_id', 'action', 'created_at'])]
final class ActivityLog
{
    public const ACTION_CREATE = 'Create';
    public const ACTION_UPDATE = 'Update';
    public const ACTION_DELETE = 'Delete';
    public const ACTION_LOGIN = 'Login';
    public const ACTION_LOGOUT = 'Logout';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    private string $action = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(nullable: true)]
    private ?int $entityId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $affectedData = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $changes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTime $createdAt = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(?string $entityType): static
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(?int $entityId): static
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getAffectedData(): ?string
    {
        return $this->affectedData;
    }

    public function setAffectedData(?string $affectedData): static
    {
        $this->affectedData = $affectedData;
        return $this;
    }

    public function getChanges(): ?string
    {
        return $this->changes;
    }

    public function setChanges(?string $changes): static
    {
        $this->changes = $changes;
        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }
}
