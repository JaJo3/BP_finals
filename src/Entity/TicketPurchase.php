<?php

namespace App\Entity;

use App\Repository\TicketPurchaseRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
#[ORM\Entity(repositoryClass: TicketPurchaseRepository::class)]
#[ORM\Table(name: 'ticket_purchase')]
class TicketPurchase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Transaction::class, inversedBy: 'ticketPurchases')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Transaction $transaction = null;

    #[ORM\ManyToOne(targetEntity: Ticket::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Ticket $ticket = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $qrCode = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $uniqueTicketCode = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function setTransaction(?Transaction $transaction): self
    {
        $this->transaction = $transaction;
        return $this;
    }

    public function getTicket(): ?Ticket
    {
        return $this->ticket;
    }

    public function setTicket(?Ticket $ticket): self
    {
        $this->ticket = $ticket;
        return $this;
    }

    public function getQrCode(): ?string
    {
        return $this->qrCode;
    }

    public function setQrCode(string $qrCode): self
    {
        $this->qrCode = $qrCode;
        return $this;
    }

    public function getUniqueTicketCode(): ?string
    {
        return $this->uniqueTicketCode;
    }

    public function setUniqueTicketCode(string $uniqueTicketCode): self
    {
        $this->uniqueTicketCode = $uniqueTicketCode;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

