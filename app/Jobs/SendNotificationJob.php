<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Services\EventSourcingService;
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

    public function handle(NotificationRepositoryInterface $repository, EventSourcingService $eventSourcing): void
    {
        $attempts = $repository->incrementAttempts($this->notification->id);

        Log::info('Notification sent', [
            'id'      => $this->notification->id,
            'type'    => $this->notification->type,
            'channel' => $this->notification->channel->value,
            'user_id' => $this->notification->user_id,
            'attempt' => $attempts,
        ]);

        $repository->markAsProcessed($this->notification->id);

        // Record sent event
        $eventSourcing->recordSent($this->notification, $attempts, [
            'queue_name' => 'notifications',
            'job_id' => $this->job->getJobId() ?? null,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $repository = app(NotificationRepositoryInterface::class);
        $eventSourcing = app(EventSourcingService::class);

        $attempts = $this->notification->attempts ?? 0;
        $repository->markAsFailed($this->notification->id, $exception->getMessage());

        Log::error('Notification failed', [
            'id'    => $this->notification->id,
            'error' => $exception->getMessage(),
            'attempt' => $attempts,
        ]);

        // Record failed event
        $eventSourcing->recordFailed(
            $this->notification,
            $exception->getMessage(),
            $attempts,
            [
                'exception_class' => get_class($exception),
                'trace' => $exception->getTraceAsString(),
            ]
        );
    }

    public function release(int $delay = 0): void
    {
        $eventSourcing = app(EventSourcingService::class);

        // Record retry event
        $eventSourcing->recordRetry(
            $this->notification,
            $this->notification->attempts ?? 0,
            $delay,
            ['reason' => 'Queue retry scheduled']
        );

        parent::release($delay);
    }
}
