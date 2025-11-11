<?php

namespace App\Entity;

use App\Repository\TicketRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Ticket
{
    // Status constants (match Event and application usage)
    public const STATUS_ACTIVE = 'Active';
    public const STATUS_UPCOMING = 'Upcoming';
    public const STATUS_ONGOING = 'Ongoing';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_CANCELLED = 'Cancelled';
    public const STATUS_SOLD_OUT = 'Sold Out';
    public const STATUS_USED = 'Used';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $ticketType = null;

    #[Assert\NotBlank]
    #[Assert\Positive]
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private $price;

    #[Assert\NotBlank]
    #[Assert\GreaterThanOrEqual(0)]
    #[ORM\Column]
    private ?int $quantity = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Event $event = null;

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_UPCOMING;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->created_at = new \DateTimeImmutable();
    }

    /**
     * Check if the ticket is available for purchase
     */
    public function isAvailable(): bool
    {
        // Check both status and quantity
        return $this->status === self::STATUS_ACTIVE 
            && $this->quantity > 0 
            && $this->event 
            && $this->event->getStatus() !== Event::STATUS_CANCELLED
            && $this->event->getStatus() !== Event::STATUS_COMPLETED;
    }

    /**
     * Update status based on event status
     */
    #[ORM\PreUpdate]
    #[ORM\PrePersist]
    public function updateStatus(): void
    {
        if ($this->event) {
            // Always sync ticket status with event status
            $this->status = $this->event->getStatus();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): static
    {
        $this->event = $event;
        // Update status when event is set or changed
        if ($event) {
            $this->updateStatus();
        }
        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        
        // Update status if quantity becomes 0
        if ($quantity <= 0) {
            $this->status = self::STATUS_SOLD_OUT;
        } elseif ($this->status === self::STATUS_SOLD_OUT && $quantity > 0) {
            $this->status = self::STATUS_ACTIVE;
        }

        return $this;
    }

    public function getTicketType(): ?string
    {
        return $this->ticketType;
    }

    public function setTicketType(string $ticketType): self
    {
        $this->ticketType = $ticketType;
        return $this;
    }
    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, [
            self::STATUS_ACTIVE,
            self::STATUS_UPCOMING,
            self::STATUS_ONGOING,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_SOLD_OUT,
            self::STATUS_USED
        ])) {
            throw new \InvalidArgumentException('Invalid status');
        }
        
        $this->status = $status;
        return $this;
    }
}
