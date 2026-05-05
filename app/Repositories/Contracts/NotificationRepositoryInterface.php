<?php

namespace App\Repositories\Contracts;

use App\DTOs\NotificationDTO;
use App\Models\Notification;
use Illuminate\Pagination\LengthAwarePaginator;

interface NotificationRepositoryInterface
{
    public function create(NotificationDTO $dto): Notification;

    public function findById(int $id): ?Notification;

    public function markAsProcessed(int $id): void;

    public function markAsFailed(int $id, string $errorMessage): void;

    public function incrementAttempts(int $id): int;

    public function getRecent(array $filters = []): LengthAwarePaginator;

    public function getSummary(?string $userId = null): array;
}
