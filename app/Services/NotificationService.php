<?php

namespace App\Services;

use App\DTOs\NotificationDTO;
use App\Exceptions\RateLimitExceededException;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\RateLimiter;

class NotificationService
{
    public function __construct(
        private readonly NotificationRepositoryInterface $repository,
        private readonly EventSourcingService $eventSourcing,
    ) {}

    public function send(NotificationDTO $dto): Notification
    {
        $rateLimitKey = 'notifications:user:' . $dto->userId;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            throw new RateLimitExceededException(
                RateLimiter::availableIn($rateLimitKey)
            );
        }

        $notification = $this->repository->create($dto);

        // Record creation event
        $this->eventSourcing->recordCreated($notification, [
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        SendNotificationJob::dispatch($notification);

        RateLimiter::hit($rateLimitKey, 3600);

        return $notification;
    }

    public function getRecent(array $filters): LengthAwarePaginator
    {
        return $this->repository->getRecent($filters);
    }

    public function getSummary(?string $userId): array
    {
        return $this->repository->getSummary($userId);
    }
}
