<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\SecurityBundle\Security;
use DateTime;
use JsonException;
use Exception;

class ActivityLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack,
    ) {}

    /**
     * Log an activity
     */
    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        mixed $affectedData = null,
        mixed $changes = null
    ): void {
        try {
            $user = $this->security->getUser();
            if (!$user instanceof User) {
                return;
            }

            $log = new ActivityLog();
            $log->setUser($user);
            $log->setAction($action);
            $log->setEntityType($entityType);
            $log->setEntityId($entityId);
            
            if ($affectedData) {
                $log->setAffectedData(is_string($affectedData) ? $affectedData : json_encode($affectedData, JSON_THROW_ON_ERROR));
            }

            if ($changes) {
                $log->setChanges(is_string($changes) ? $changes : json_encode($changes, JSON_THROW_ON_ERROR));
            }

            // Capture IP address
            $request = $this->requestStack->getCurrentRequest();
            if ($request) {
                $log->setIpAddress($request->getClientIp());
            }

            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (JsonException | \Exception $e) {
            // Log silently to avoid disrupting main functionality
            error_log('ActivityLogger error: ' . $e->getMessage());
        }
    }

    /**
     * Log create action
     */
    public function logCreate(string $entityType, int $entityId, mixed $data): void
    {
        $this->log(ActivityLog::ACTION_CREATE, $entityType, $entityId, $data);
    }

    /**
     * Log update action with changes
     */
    public function logUpdate(string $entityType, int $entityId, mixed $oldData, mixed $newData): void
    {
        $changes = $this->calculateChanges($oldData, $newData);
        $this->log(ActivityLog::ACTION_UPDATE, $entityType, $entityId, $newData, $changes);
    }

    /**
     * Log delete action
     */
    public function logDelete(string $entityType, int $entityId, mixed $data): void
    {
        $this->log(ActivityLog::ACTION_DELETE, $entityType, $entityId, $data);
    }

    /**
     * Log login action
     */
    public function logLogin(): void
    {
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $this->log(ActivityLog::ACTION_LOGIN, 'User', $user->getId());
        }
    }

    /**
     * Log logout action
     */
    public function logLogout(): void
    {
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $this->log(ActivityLog::ACTION_LOGOUT, 'User', $user->getId());
        }
    }

    /**
     * Calculate changes between old and new data
     */
    private function calculateChanges(mixed $oldData, mixed $newData): array
    {
        $changes = [];

        $old = $this->normalizeToArray($oldData);
        $new = $this->normalizeToArray($newData);

        // If both are null/empty, nothing to do
        if (empty($old) && empty($new)) {
            return [];
        }

        // Compare union of keys
        $keys = array_unique(array_merge(array_keys($old ?? []), array_keys($new ?? [])));

        foreach ($keys as $key) {
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;

            // Loose comparison to detect changed scalar values
            if ($oldValue != $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Normalize various data shapes into an associative array for comparison.
     */
    private function normalizeToArray(mixed $data): ?array
    {
        if ($data === null) {
            return [];
        }

        if (is_array($data)) {
            return $data;
        }

        // If it's a JSON string, decode it
        if (is_string($data)) {
            try {
                $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (\JsonException $e) {
                // fall through and return as scalar under a generic key
            }

            return ['value' => $data];
        }

        if (is_object($data)) {
            // If object implements JsonSerializable or has public properties, json_encode+decode will usually work
            try {
                $encoded = json_encode($data, JSON_THROW_ON_ERROR);
                $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (\Throwable $e) {
                // ignore and fallback
            }

            // If object has toArray(), use it
            if (method_exists($data, 'toArray')) {
                try {
                    $arr = $data->toArray();
                    if (is_array($arr)) {
                        return $arr;
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            // As last resort, expose public properties
            return get_object_vars($data);
        }

        // scalar (int/float/bool)
        return ['value' => $data];
    }
}
