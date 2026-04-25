<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\User;
use App\Entity\Event as EventEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'transactions')]
class Transaction
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    public const PAYMENT_GCASH = 'GCash';
    public const PAYMENT_PAYPAL = 'PayPal';
    public const PAYMENT_MANUAL = 'Manual';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Event $event = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createdBy = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $totalAmount = '0.00';

    #[ORM\Column(type: 'string', length: 20)]
    private string $paymentStatus = self::STATUS_PENDING;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\Column(type: 'string', length: 128, nullable: true)]
    private ?string $paymentReference = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $paidAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $refundedAt = null;

    #[ORM\Column(type: 'json')]
    private array $tickets = [];

    #[ORM\OneToMany(mappedBy: 'transaction', targetEntity: TicketPurchase::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $ticketPurchases;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->paymentStatus = self::STATUS_PENDING;
        $this->ticketPurchases = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(Event $event): self
    {
        $this->event = $event;
        return $this;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $amount): self
    {
        $this->totalAmount = number_format((float)$amount, 2, '.', '');
        return $this;
    }

    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(string $status): self
    {
        $allowed = [self::STATUS_PENDING, self::STATUS_PAID, self::STATUS_FAILED, self::STATUS_REFUNDED];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid payment status');
        }
        $this->paymentStatus = $status;
        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $method): self
    {
        $this->paymentMethod = $method;
        return $this;
    }

    public function getPaymentReference(): ?string
    {
        return $this->paymentReference;
    }

    public function setPaymentReference(?string $ref): self
    {
        $this->paymentReference = $ref;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $user): self
    {
        $this->createdBy = $user;
        return $this;
    }

    public function getPaidAt(): ?\DateTime
    {
        return $this->paidAt;
    }

    public function getRefundedAt(): ?\DateTime
    {
        return $this->refundedAt;
    }

    public function getTickets(): array
    {
        return $this->tickets;
    }

    public function setTickets(array $tickets): self
    {
        $this->tickets = $tickets;
        $this->calculateTotalAmount();
        return $this;
    }

    public function addTicket(string $type, int $quantity, float $price, ?string $code = null): self
    {
        $this->tickets[] = [
            'ticketType' => $type,
            'quantity' => $quantity,
            'price' => number_format($price, 2, '.', ''),
            'code' => $code,
        ];

        $this->calculateTotalAmount();
        return $this;
    }

    /**
     * @return Collection<int, TicketPurchase>
     */
    public function getTicketPurchases(): Collection
    {
        return $this->ticketPurchases;
    }

    public function addTicketPurchase(TicketPurchase $purchase): self
    {
        if (!$this->ticketPurchases->contains($purchase)) {
            $this->ticketPurchases->add($purchase);
            $purchase->setTransaction($this);
        }
        return $this;
    }

    public function removeTicketPurchase(TicketPurchase $purchase): self
    {
        if ($this->ticketPurchases->removeElement($purchase)) {
            if ($purchase->getTransaction() === $this) {
                $purchase->setTransaction(null);
            }
        }
        return $this;
    }

    public function calculateTotalAmount(): string
    {
        $total = 0.00;
        foreach ($this->tickets as $t) {
            $total += (float)$t['price'] * (int)$t['quantity'];
        }

        $this->totalAmount = number_format($total, 2, '.', '');
        return $this->totalAmount;
    }

    public function markPaid(?string $reference = null, ?\DateTime $paidAt = null): self
    {
        $this->setPaymentStatus(self::STATUS_PAID);
        $this->paymentReference = $reference ?? $this->paymentReference;
        $this->paidAt = $paidAt ?? new \DateTime();
        return $this;
    }

    public function markRefunded(?string $reason = null): self
    {
        $this->setPaymentStatus(self::STATUS_REFUNDED);
        $this->refundedAt = new \DateTime();
        return $this;
    }

}
