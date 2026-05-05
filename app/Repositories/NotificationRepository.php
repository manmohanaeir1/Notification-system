<?php

namespace App\Repositories;

use App\DTOs\NotificationDTO;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class NotificationRepository implements NotificationRepositoryInterface
{
    public function create(NotificationDTO $dto): Notification
    {
        return Notification::create([
            'user_id' => $dto->userId,
            'type'    => $dto->type,
            'message' => $dto->message,
            'channel' => $dto->channel->value,
            'status'  => NotificationStatus::Pending->value,
        ]);
    }

    public function findById(int $id): ?Notification
    {
        return Notification::find($id);
    }

    public function markAsProcessed(int $id): void
    {
        Notification::where('id', $id)->update([
            'status'       => NotificationStatus::Processed->value,
            'processed_at' => now(),
        ]);

        $this->forgetSummaryCache();
    }

    public function markAsFailed(int $id, string $errorMessage): void
    {
        Notification::where('id', $id)->update([
            'status'        => NotificationStatus::Failed->value,
            'error_message' => $errorMessage,
        ]);

        $this->forgetSummaryCache();
    }

    public function incrementAttempts(int $id): int
    {
        Notification::where('id', $id)->increment('attempts');
        return (int) Notification::find($id)?->attempts ?? 0;
    }

    public function getRecent(array $filters = []): LengthAwarePaginator
    {
        $perPage = $filters['per_page'] ?? 15;

        return Notification::query()
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['user_id']), fn ($q) => $q->where('user_id', $filters['user_id']))
            ->when(isset($filters['channel']), fn ($q) => $q->where('channel', $filters['channel']))
            ->when(isset($filters['from_date']), fn ($q) => $q->where('created_at', '>=', $filters['from_date']))
            ->when(isset($filters['to_date']), fn ($q) => $q->where('created_at', '<=', $filters['to_date']))
            ->latest()
            ->paginate($perPage);
    }

    public function getSummary(?string $userId = null): array
    {
        $cacheKey = 'notification:summary' . ($userId ? ":user_id:{$userId}" : '');

        return Cache::remember($cacheKey, 300, function () use ($userId) {
            $query = Notification::query()
                ->when($userId, fn ($q) => $q->where('user_id', $userId));

            return [
                'total'     => (clone $query)->count(),
                'processed' => (clone $query)->where('status', NotificationStatus::Processed->value)->count(),
                'failed'    => (clone $query)->where('status', NotificationStatus::Failed->value)->count(),
                'pending'   => (clone $query)->where('status', NotificationStatus::Pending->value)->count(),
            ];
        });
    }

    private function forgetSummaryCache(): void
    {
        Cache::forget('notification:summary');
    }
}
