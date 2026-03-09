<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Repository\EventRepository;
use App\Entity\Transaction;
use App\Entity\TicketPurchase;
use App\Service\ActivityLogger;
use App\Service\QrCodeService;

final class TransactionController extends AbstractController
{
    public function __construct(private ActivityLogger $activityLogger)
    {
    }

    private function ensureAdminOrStaff(): void
    {
        /** @var \App\Entity\User|null $currentUser */
        $currentUser = $this->getUser();
        if ($currentUser && method_exists($currentUser, 'getIsActive') && !$currentUser->getIsActive()) {
            throw $this->createAccessDeniedException('Your account has been disabled.');
        }

        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException('Admin area is restricted to staff and admins only.');
        }
    }

    #[Route('/transaction', name: 'app_transaction')]
    public function index(Request $request, TransactionRepository $repo): Response
    {
        // support filters for public listing (status, date range) similar to admin
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = min(100, (int)$request->query->get('limit', 25));

        $filters = [
            'status' => $request->query->get('status'),
            'from' => $request->query->get('from'),
            'to' => $request->query->get('to'),
        ];

        /** @var \App\Entity\User|null $currentUser */
        $currentUser = $this->getUser();
        $currentUserId = $currentUser?->getId();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $result = $repo->findForAdminList($filters, $page, $limit);

        return $this->render('transaction/index.html.twig', [
            'transactions' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
            'currentUserId' => $currentUserId,
            'isAdmin' => $isAdmin,
        ]);
    }

    // --- Admin routes copied from Admin\TransactionController ---

    #[Route('/admin/transactions', name: 'admin_transaction_index', methods: ['GET'])]
    public function adminIndex(Request $request, TransactionRepository $repo): Response
    {
        $this->ensureAdminOrStaff();
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = min(100, (int)$request->query->get('limit', 25));

        $filters = [
            'status' => $request->query->get('status'),
            'event' => $request->query->get('event'),
            'user' => $request->query->get('user'),
            'from' => $request->query->get('from'),
            'to' => $request->query->get('to'),
        ];

        // Pass current user info for template conditional rendering
        /** @var \App\Entity\User|null $currentUser */
        $currentUser = $this->getUser();
        $currentUserId = $currentUser?->getId();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        // Staff can now view all transactions; only admins can filter by arbitrary user.
        // Prevent staff from overriding user filters via query params for privacy.
        if (!$isAdmin) {
            unset($filters['user']);
        }

        $result = $repo->findForAdminList($filters, $page, $limit);

        return $this->render('transaction/index.html.twig', [
            'transactions' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
            'currentUserId' => $currentUserId,
            'isAdmin' => $isAdmin,
        ]);
    }

    #[Route('/admin/transaction/{id}/details', name: 'admin_transaction_details', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function details(Transaction $transaction): JsonResponse
    {
        $this->ensureAdminOrStaff();
        $buyer = $transaction->getUser();
        $event = $transaction->getEvent();
        
        // Build ticket purchases with QR codes
        $ticketPurchases = [];
        foreach ($transaction->getTicketPurchases() as $purchase) {
            $ticketPurchases[] = [
                'id' => $purchase->getId(),
                'ticketType' => $purchase->getTicket()?->getTicketType() ?? 'Ticket',
                'uniqueCode' => $purchase->getUniqueTicketCode(),
                'qrCode' => $purchase->getQrCode(),
            ];
        }
        
        $data = [
            'id' => $transaction->getId(),
            'user' => [
                'id' => $buyer?->getId(),
                'email' => $buyer?->getEmail() ?? 'N/A',
                'name' => $buyer?->getFullName() ?? $buyer?->getEmail() ?? 'Unknown',
            ],
            'event' => [
                'id' => $event?->getId(),
                'name' => $event?->getEventName() ?? 'N/A',
                'date' => $event?->getDate()?->format('Y-m-d H:i') ?? 'N/A',
            ],
            'tickets' => $transaction->getTickets() ?? [],
            'ticketPurchases' => $ticketPurchases,
            'payment' => [
                'status' => $transaction->getPaymentStatus() ?? 'pending',
                'method' => $transaction->getPaymentMethod() ?? 'N/A',
                'reference' => $transaction->getPaymentReference() ?? 'N/A',
                'amount' => (float)($transaction->getTotalAmount() ?? 0),
                'createdAt' => $transaction->getCreatedAt()?->format(DATE_ATOM) ?? 'N/A',
                'paidAt' => $transaction->getPaidAt()?->format(DATE_ATOM) ?? null,
                'refundedAt' => $transaction->getRefundedAt()?->format(DATE_ATOM) ?? null,
            ],
        ];

        return $this->json($data);
    }

    #[Route('/admin/transaction/new', name: 'admin_transaction_new', methods: ['GET','POST'])]
    public function adminNew(Request $request, EntityManagerInterface $em, UserRepository $users, EventRepository $events, \App\Repository\TicketRepository $ticketRepository, QrCodeService $qrCodeService): Response
    {
        $this->ensureAdminOrStaff();
        // Allow staff to create transactions, but always scope ownership to themselves.
        /** @var \App\Entity\User|null $currentUser */
        $currentUser = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        if ($request->isMethod('POST')) {
            $errors = [];
            $accept = (string) $request->headers->get('Accept', '');
            $isTurbo = str_contains($accept, 'turbo-stream') || $request->headers->has('Turbo-Frame') || strtolower($request->headers->get('X-Requested-With', '')) === 'xmlhttprequest';
            if (!$this->isCsrfTokenValid('transaction_new', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Invalid CSRF token.');
                return $this->redirectToRoute('admin_transaction_new');
            }

            // Admins may pick any buyer; staff are forced to themselves (when available)
            $userIdFromForm = $request->request->get('user');
            $userId = $isAdmin ? $userIdFromForm : ($currentUser?->getId());
            $eventId = $request->request->get('event');
            $ticketsJson = $request->request->get('tickets');

            // LOG what was submitted and what we're using
            error_log('=== TRANSACTION BUYER DEBUG ===');
            error_log('isAdmin: ' . ($isAdmin ? 'YES' : 'NO'));
            error_log('User from form: ' . var_export($userIdFromForm, true));
            error_log('Final userId being used: ' . var_export($userId, true));
            error_log('Current user ID: ' . ($currentUser?->getId() ?? 'null'));
            error_log('Current user email: ' . ($currentUser?->getEmail() ?? 'null'));
            error_log('=== END DEBUG ===');

            $user = $users->find((int)$userId);
            $event = $events->find((int)$eventId);

            // Validate event
            if (!$event) {
                $errors[] = 'Please choose a valid event.';
            }

            // Validate user
            if (!$user) {
                $errors[] = 'Please choose a valid buyer (user).';
            }

            if (!$user || !$event) {
                $this->addFlash('danger', 'User or Event not found.');
                return $this->redirectToRoute('admin_transaction_new');
            }

            $transaction = new Transaction();
            $transaction->setUser($user)->setEvent($event);

            $tickets = is_string($ticketsJson) ? json_decode($ticketsJson, true) ?? [] : $ticketsJson ?? [];

            // Validate requested tickets and update ticket inventory
            if (!is_array($tickets) || count($tickets) === 0) {
                $errors[] = 'Please select at least one ticket.';
            }

            $payload = [];
            $ticketEntitiesForPurchases = [];
            foreach ($tickets as $item) {
                $tid = isset($item['ticketId']) ? (int)$item['ticketId'] : null;
                $qty = isset($item['quantity']) ? (int)$item['quantity'] : 0;
                if (!$tid || $qty <= 0) continue;

                $ticketEntity = $ticketRepository->find($tid);
                if (!$ticketEntity || $ticketEntity->getEvent()->getId() !== $event->getId()) {
                    $errors[] = sprintf('Invalid ticket requested: %s', $tid);
                    break;
                }

                if ($qty > $ticketEntity->getQuantity()) {
                    $errors[] = sprintf('Not enough tickets available for %s (requested %d, available %d).', $ticketEntity->getTicketType(), $qty, $ticketEntity->getQuantity());
                    break;
                }

                // reserve / deduct quantity
                $ticketEntity->setQuantity($ticketEntity->getQuantity() - $qty);
                $em->persist($ticketEntity);

                    $payload[] = [
                    'ticketId' => $ticketEntity->getId(),
                    'ticketType' => $ticketEntity->getTicketType(),
                    'quantity' => $qty,
                    'price' => (float)$ticketEntity->getPrice(),
                ];
                $ticketEntitiesForPurchases[] = ['entity' => $ticketEntity, 'quantity' => $qty];
            }

            $transaction->setTickets($payload);
            $transaction->calculateTotalAmount();

            // Get and validate payment info (required for creation)
            $paymentMethod = $request->request->get('payment_method');
            $paymentReference = $request->request->get('payment_reference');

            // Validate that payment method and reference are provided
            if (!$paymentMethod) {
                $errors[] = 'Payment method is required.';
            }
            if (!$paymentReference) {
                $errors[] = 'Payment reference is required.';
            }

            // Validate payment method value
            if ($paymentMethod) {
                $allowedMethods = [Transaction::PAYMENT_GCASH, Transaction::PAYMENT_PAYPAL, Transaction::PAYMENT_MANUAL, 'Card'];
                if (!in_array($paymentMethod, $allowedMethods, true)) {
                    $errors[] = 'Invalid payment method.';
                }
                $transaction->setPaymentMethod($paymentMethod);
            }

            if ($paymentReference) {
                $transaction->setPaymentReference($paymentReference);
            }

            // Auto-mark as paid if both payment method and reference are provided
            if ($paymentMethod && $paymentReference) {
                $transaction->markPaid($paymentReference);
            }

            // if validation errors were collected, re-render with errors and old input
            if (count($errors) > 0) {
                $html = $this->renderView('transaction/new.html.twig', [
                    'users' => $isAdmin ? $users->findBuyers() : array_filter([$currentUser]),
                    'events' => $events->findAll(),
                    'errors' => $errors,
                    'old' => $request->request->all(),
                    'isAdmin' => $isAdmin,
                ]);
                if ($isTurbo) {
                    return new Response($html, Response::HTTP_UNPROCESSABLE_ENTITY, ['Content-Type' => 'text/html']);
                }
                return new Response($html);
            }

            // set createdBy if current user is present
            if ($currentUser instanceof \App\Entity\User) {
                $transaction->setCreatedBy($currentUser);
            }
            $em->persist($transaction);
            $em->flush(); // Flush transaction first to get ID

            // Generate ticket purchases with QR codes
            try {
                foreach ($ticketEntitiesForPurchases as $entry) {
                    $ticketEntity = $entry['entity'];
                    $qty = $entry['quantity'];
                    for ($i = 0; $i < $qty; $i++) {
                        $purchase = new TicketPurchase();
                        $uniqueCode = strtoupper(uniqid('BP-', true));
                        $purchase->setTransaction($transaction);
                        $purchase->setTicket($ticketEntity);
                        $purchase->setUniqueTicketCode($uniqueCode);
                        $purchase->setQrCode($qrCodeService->generate($uniqueCode));
                        $transaction->addTicketPurchase($purchase);
                        $em->persist($purchase);
                    }
                }
                $em->flush();
            } catch (\Exception $e) {
                // If QR generation fails, log error but don't break transaction creation
                $this->addFlash('warning', 'Transaction created but QR codes could not be generated: ' . $e->getMessage());
                // Log the error for debugging
                error_log('QR Code generation error: ' . $e->getMessage());
                // Log as activity event
                $this->activityLogger->logCreate('QRCode_GenerationFailure', (int) $transaction->getId(), [
                    'transaction_id' => $transaction->getId(),
                    'error' => $e->getMessage(),
                    'ticket_count' => count($ticketEntitiesForPurchases),
                ]);
            }

            // Log ticket inventory changes
            foreach ($ticketEntitiesForPurchases as $entry) {
                $ticketEntity = $entry['entity'];
                $qty = $entry['quantity'];
                $this->activityLogger->logUpdate('Ticket', (int) $ticketEntity->getId(), 
                    ['quantity' => $ticketEntity->getQuantity() + $qty], 
                    ['quantity' => $ticketEntity->getQuantity()]
                );
            }

            $this->addFlash('success', 'Transaction created');
            $this->activityLogger->logCreate('Transaction', (int) $transaction->getId(), [
                'event' => $transaction->getEvent()?->getId(),
                'user' => $transaction->getUser()?->getId(),
            ]);
            return $this->redirectToRoute('admin_transaction_index');
        }

        return $this->render('transaction/new.html.twig', [
            // Staff only see themselves in the user dropdown; admins see only ROLE_USER buyers
            'users' => $isAdmin ? $users->findBuyers() : array_filter([$currentUser]),
            'events' => $events->findAll(),
            'isAdmin' => $isAdmin,
        ]);
    }

    // Public creation route — allows non-admin users to create transactions
    #[Route('/transaction/new', name: 'transaction_new', methods: ['GET','POST'])]
    public function publicNew(Request $request, EntityManagerInterface $em, EventRepository $events, UserRepository $users, \App\Repository\TicketRepository $ticketRepository, QrCodeService $qrCodeService): Response
    {
        if ($request->isMethod('POST')) {
            $errors = [];
            $accept = (string) $request->headers->get('Accept', '');
            $isTurbo = str_contains($accept, 'turbo-stream') || $request->headers->has('Turbo-Frame') || strtolower($request->headers->get('X-Requested-With', '')) === 'xmlhttprequest';

            if (!$this->isCsrfTokenValid('transaction_new', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Invalid CSRF token.');
                return $this->redirectToRoute('transaction_new');
            }

            $eventId = $request->request->get('event');
            $ticketsJson = $request->request->get('tickets');

            $event = $events->find((int)$eventId);
            if (!$event) {
                $this->addFlash('danger', 'Event not found.');
                return $this->redirectToRoute('transaction_new');
            }

            // determine user: prefer logged in user, otherwise look for provided user id, otherwise try buyer_email
            $user = null;
            if ($this->getUser()) {
                $user = $this->getUser();
            } elseif ($request->request->get('user')) {
                $userId = (int)$request->request->get('user');
                $user = $users->find($userId);
            } elseif ($request->request->get('buyer_email')) {
                $buyerEmail = trim($request->request->get('buyer_email'));
                // Try to find user by email first, then username fallback
                $user = $users->findOneBy(['email' => $buyerEmail]);
                if (!$user) {
                    $user = $users->findOneBy(['username' => $buyerEmail]);
                }
            }

            $transaction = new Transaction();
            if ($user) {
                $transaction->setUser($user);
            }
            $transaction->setEvent($event);

            $tickets = is_string($ticketsJson) ? json_decode($ticketsJson, true) ?? [] : $ticketsJson ?? [];

            if (!is_array($tickets) || count($tickets) === 0) {
                $errors[] = 'Please select at least one ticket.';
            }

            $payload = [];
            $ticketEntitiesForPurchases = [];
            foreach ($tickets as $item) {
                $tid = isset($item['ticketId']) ? (int)$item['ticketId'] : null;
                $qty = isset($item['quantity']) ? (int)$item['quantity'] : 0;
                if (!$tid || $qty <= 0) continue;

                $ticketEntity = $ticketRepository->find($tid);
                if (!$ticketEntity || $ticketEntity->getEvent()->getId() !== $event->getId()) {
                    $errors[] = sprintf('Invalid ticket requested: %s', $tid);
                    break;
                }

                if ($qty > $ticketEntity->getQuantity()) {
                    $errors[] = sprintf('Not enough tickets available for %s (requested %d, available %d).', $ticketEntity->getTicketType(), $qty, $ticketEntity->getQuantity());
                    break;
                }

                // deduct stock
                $ticketEntity->setQuantity($ticketEntity->getQuantity() - $qty);
                $em->persist($ticketEntity);

                $payload[] = [
                    'ticketId' => $ticketEntity->getId(),
                    'ticketType' => $ticketEntity->getTicketType(),
                    'quantity' => $qty,
                    'price' => (float)$ticketEntity->getPrice(),
                ];
                $ticketEntitiesForPurchases[] = ['entity' => $ticketEntity, 'quantity' => $qty];
            }

            $transaction->setTickets($payload);
            $transaction->calculateTotalAmount();

            // Get and validate payment info (required for creation)
            $paymentMethod = $request->request->get('payment_method');
            $paymentReference = $request->request->get('payment_reference');

            // Validate that payment method and reference are provided
            if (!$paymentMethod) {
                $errors[] = 'Payment method is required.';
            }
            if (!$paymentReference) {
                $errors[] = 'Payment reference is required.';
            }

            // Validate payment method value
            if ($paymentMethod) {
                $allowedMethods = [Transaction::PAYMENT_GCASH, Transaction::PAYMENT_PAYPAL, Transaction::PAYMENT_MANUAL, 'Card'];
                if (!in_array($paymentMethod, $allowedMethods, true)) {
                    $errors[] = 'Invalid payment method.';
                }
                $transaction->setPaymentMethod($paymentMethod);
            }

            if ($paymentReference) {
                $transaction->setPaymentReference($paymentReference);
            }

            // Auto-mark as paid if both payment method and reference are provided
            if ($paymentMethod && $paymentReference) {
                $transaction->markPaid($paymentReference);
            }

            // If any errors were found validate them before saving
            if (count($errors) > 0) {
                $html = $this->renderView('transaction/new.html.twig', [
                    'events' => $events->findAll(),
                    'users' => $users->findBuyers(),
                    'errors' => $errors,
                    'old' => $request->request->all(),
                ]);
                if ($isTurbo) {
                    return new Response($html, Response::HTTP_UNPROCESSABLE_ENTITY, ['Content-Type' => 'text/html']);
                }
                return new Response($html);
            }

            // set createdBy if current user is present
            $currentUser = $this->getUser();
            if ($currentUser instanceof \App\Entity\User) {
                $transaction->setCreatedBy($currentUser);
            }
            $em->persist($transaction);
            $em->flush(); // Flush transaction first to get ID

            // Generate ticket purchases with QR codes
            try {
                foreach ($ticketEntitiesForPurchases as $entry) {
                    $ticketEntity = $entry['entity'];
                    $qty = $entry['quantity'];
                    for ($i = 0; $i < $qty; $i++) {
                        $purchase = new TicketPurchase();
                        $uniqueCode = strtoupper(uniqid('BP-', true));
                        $purchase->setTransaction($transaction);
                        $purchase->setTicket($ticketEntity);
                        $purchase->setUniqueTicketCode($uniqueCode);
                        $purchase->setQrCode($qrCodeService->generate($uniqueCode));
                        $transaction->addTicketPurchase($purchase);
                        $em->persist($purchase);
                    }
                }
                $em->flush();
            } catch (\Exception $e) {
                // If QR generation fails, log error but don't break transaction creation
                $this->addFlash('warning', 'Transaction created but QR codes could not be generated: ' . $e->getMessage());
                // Log the error for debugging
                error_log('QR Code generation error: ' . $e->getMessage());
            }

            $this->addFlash('success', 'Transaction created successfully.');
            $this->activityLogger->logCreate('Transaction', (int) $transaction->getId(), [
                'event' => $transaction->getEvent()?->getId(),
                'user' => $transaction->getUser()?->getId(),
            ]);

            return $this->redirectToRoute('app_transaction');
        }

        // public view: provide events and users so anyone can select a buyer
        return $this->render('transaction/new.html.twig', [
            'events' => $events->findAll(),
            'users' => $users->findBuyers(),
        ]);
    }

    #[Route('/admin/transaction/{id}/mark-paid', name: 'admin_transaction_mark_paid', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function markPaid(Transaction $transaction, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->ensureAdminOrStaff();
        // Ownership check: only admins or creator may mark paid
        if (!$this->isGranted('ROLE_ADMIN')) {
            /** @var \App\Entity\User|null $currentUser */
            $currentUser = $this->getUser();
            if (!$currentUser || method_exists($transaction, 'getCreatedBy') && $transaction->getCreatedBy()?->getId() !== $currentUser->getId()) {
                return $this->json(['ok' => false, 'message' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
            }
        }

        if (!$this->isCsrfTokenValid('transaction_action_' . $transaction->getId(), $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'message' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $ref = $request->request->get('reference');
        $old = [
            'status' => $transaction->getPaymentStatus(),
        ];
        $transaction->markPaid($ref);
        $em->persist($transaction);
        $em->flush();
        $this->activityLogger->logUpdate('Transaction', (int) $transaction->getId(), $old, [
            'status' => $transaction->getPaymentStatus(),
            'paid_reference' => $ref,
            'paid_at' => $transaction->getPaidAt()?->format('Y-m-d H:i:s'),
            'amount' => $transaction->getTotalAmount(),
        ]);

        return $this->json(['ok' => true, 'message' => 'Marked as paid']);
    }

    #[Route('/admin/transaction/{id}/refund', name: 'admin_transaction_refund', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function refund(Transaction $transaction, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->ensureAdminOrStaff();
        // Ownership check: only admins or creator may refund
        if (!$this->isGranted('ROLE_ADMIN')) {
            /** @var \App\Entity\User|null $currentUser */
            $currentUser = $this->getUser();
            if (!$currentUser || method_exists($transaction, 'getCreatedBy') && $transaction->getCreatedBy()?->getId() !== $currentUser->getId()) {
                return $this->json(['ok' => false, 'message' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
            }
        }

        if (!$this->isCsrfTokenValid('transaction_action_' . $transaction->getId(), $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'message' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $reason = $request->request->get('reason');
        $old = [
            'status' => $transaction->getPaymentStatus(),
        ];
        $transaction->markRefunded($reason);
        $em->persist($transaction);
        $em->flush();
        $this->activityLogger->logUpdate('Transaction', (int) $transaction->getId(), $old, [
            'status' => $transaction->getPaymentStatus(),
            'refund_reason' => $reason ?: 'No reason provided',
            'refunded_at' => $transaction->getRefundedAt()?->format('Y-m-d H:i:s'),
            'amount_refunded' => $transaction->getTotalAmount(),
        ]);

        return $this->json(['ok' => true, 'message' => 'Marked as refunded']);
    }

    #[Route('/admin/transaction/{id}/delete', name: 'admin_transaction_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Transaction $transaction, Request $request, EntityManagerInterface $em): Response
    {
        $this->ensureAdminOrStaff();
        // Ownership check: only admins or creator may delete
        if (!$this->isGranted('ROLE_ADMIN')) {
            /** @var \App\Entity\User|null $currentUser */
            $currentUser = $this->getUser();
            if (!$currentUser || method_exists($transaction, 'getCreatedBy') && $transaction->getCreatedBy()?->getId() !== $currentUser->getId()) {
                $this->addFlash('danger', 'Unauthorized.');
                return $this->redirectToRoute('admin_transaction_index');
            }
        }

        if (!$this->isCsrfTokenValid('transaction_delete_' . $transaction->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_transaction_index');
        }

        $deletedId = $transaction->getId();
        $em->remove($transaction);
        $em->flush();
        $this->activityLogger->logDelete('Transaction', (int) $deletedId, []);

        $this->addFlash('success', 'Transaction deleted.');
        return $this->redirectToRoute('admin_transaction_index');
    }

    #[Route('/admin/transaction/{id}/show', name: 'admin_transaction_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Transaction $transaction): Response
    {
        $this->ensureAdminOrStaff();
        // Ownership check: admins or creator or owner user may view
        if (!$this->isGranted('ROLE_ADMIN')) {
            /** @var \App\Entity\User|null $currentUser */
            $currentUser = $this->getUser();
            $allowed = false;
            if ($currentUser) {
                if (method_exists($transaction, 'getCreatedBy') && $transaction->getCreatedBy()?->getId() === $currentUser->getId()) {
                    $allowed = true;
                }
                if ($transaction->getUser()?->getId() === $currentUser->getId()) {
                    $allowed = true;
                }
            }
            if (!$allowed) {
                throw $this->createAccessDeniedException('You are not allowed to view this transaction.');
            }
        }

        return $this->render('transaction/show.html.twig', ['transaction' => $transaction]);
    }

}
    