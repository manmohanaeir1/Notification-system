<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(public Notification $notification)
    {
        $this->onQueue('notifications');
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(NotificationRepositoryInterface $repository): void
    {
        $repository->incrementAttempts($this->notification->id);

        Log::info('Notification sent', [
            'id'      => $this->notification->id,
            'type'    => $this->notification->type,
            'channel' => $this->notification->channel->value,
            'user_id' => $this->notification->user_id,
        ]);

        $repository->markAsProcessed($this->notification->id);
    }

    public function failed(Throwable $exception): void
    {
        $repository = app(NotificationRepositoryInterface::class);
        $repository->markAsFailed($this->notification->id, $exception->getMessage());

        Log::error('Notification failed', [
            'id'    => $this->notification->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
