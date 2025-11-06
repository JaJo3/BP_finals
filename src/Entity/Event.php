<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;  
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Event
{
    private ?string $previousStatus = null;

    // Update status constants
    public const STATUS_ACTIVE = 'Active';
    public const STATUS_UPCOMING = 'Upcoming';
    public const STATUS_ONGOING = 'Ongoing';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_CANCELLED = 'Cancelled';
    public const STATUS_POSTPONED = 'Postponed';
    public const STATUS_SOLD_OUT = 'Sold Out';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $event_name = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(length: 255)]
    private ?string $venue = null;

    #[ORM\Column(length: 255)]
    private ?string $category = null;

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_UPCOMING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $date_created = null;

    #[ORM\ManyToOne(inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Organizer $organizer = null;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Ticket::class, cascade: ['persist', 'remove'])]
    private Collection $tickets;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $poster = null;

    public function __construct()
    {
        $this->tickets = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->date_created = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function storePreviousStatus(): void
    {
        $this->previousStatus = $this->status;
    }

    #[ORM\PostUpdate]
    public function updateTicketStatuses(): void
    {
        // Only proceed if status has changed
        if ($this->previousStatus !== $this->status) {
            // Map event status to ticket status
            $ticketStatus = match($this->status) {
                self::STATUS_CANCELLED => Ticket::STATUS_CANCELLED,
                self::STATUS_COMPLETED => Ticket::STATUS_USED,
                self::STATUS_ONGOING => Ticket::STATUS_ACTIVE,
                self::STATUS_UPCOMING => Ticket::STATUS_ACTIVE,
                default => Ticket::STATUS_ACTIVE
            };

            // Update all related tickets
            foreach ($this->getTickets() as $ticket) {
                $ticket->setStatus($ticketStatus);
            }

            // Log status change
            error_log(sprintf(
                'Event ID %d status changed from %s to %s. Updated %d tickets.',
                $this->getId(),
                $this->previousStatus,
                $this->status,
                count($this->getTickets())
            ));
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventName(): ?string
    {
        return $this->event_name;
    }

    public function setEventName(string $event_name): static
    {
        $this->event_name = $event_name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getVenue(): ?string
    {
        return $this->venue;
    }

    public function setVenue(string $venue): static
    {
        $this->venue = $venue;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        // Update valid statuses array
        if (!in_array($status, [
            self::STATUS_ACTIVE,
            self::STATUS_UPCOMING,
            self::STATUS_ONGOING,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_POSTPONED,
            self::STATUS_SOLD_OUT
        ])) {
            throw new \InvalidArgumentException('Invalid event status');
        }

        $this->status = $status;
        return $this;
    }

    public function getDateCreated(): ?\DateTimeImmutable
    {
        return $this->date_created;
    }

    public function setDateCreated(\DateTimeImmutable $date_created): static
    {
        $this->date_created = $date_created;

        return $this;
    }

    public function getOrganizer(): ?Organizer
    {
        return $this->organizer;
    }

    public function setOrganizer(?Organizer $organizer): static
    {
        $this->organizer = $organizer;

        return $this;
    }

    public function getPoster(): ?string
    {
        return $this->poster;
    }

    public function setPoster(?string $poster): static
    {
        $this->poster = $poster;

        return $this;
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function getTickets(): Collection
    {
        return $this->tickets;
    }

    public function addTicket(Ticket $ticket): static
    {
        if (!$this->tickets->contains($ticket)) {
            $this->tickets->add($ticket);
            $ticket->setEvent($this);
        }

        return $this;
    }

    public function removeTicket(Ticket $ticket): static
    {
        if ($this->tickets->removeElement($ticket)) {
            // Set the owning side to null if necessary
            if ($ticket->getEvent() === $this) {
                $ticket->setEvent(null);
            }
        }

        return $this;
    }
}
