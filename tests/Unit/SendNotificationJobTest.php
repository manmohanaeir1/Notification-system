<?php

namespace Tests\Unit;

use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationEvent;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Services\EventSourcingService;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SendNotificationJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_marks_notification_as_processed_on_success(): void
    {
        Log::spy();
        $capturedMetadata = null;

        $notification = new Notification();
        $notification->id      = 1;
        $notification->type    = 'alert';
        $notification->user_id = 'user-1';
        $notification->channel = \App\Enums\NotificationChannel::Database;

        $repository = Mockery::mock(NotificationRepositoryInterface::class);
        $repository->shouldReceive('incrementAttempts')->once()->with(1)->andReturn(1);
        $repository->shouldReceive('markAsProcessed')->once()->with(1);

        $eventSourcing = Mockery::mock(EventSourcingService::class);
        $eventSourcing->shouldReceive('recordSent')->once()->andReturnUsing(
            function (Notification $recordedNotification, int $attempts, array $metadata) use (&$capturedMetadata): NotificationEvent {
                $this->assertSame(1, $recordedNotification->id);
                $this->assertSame(1, $attempts);
                $capturedMetadata = $metadata;

                return Mockery::mock(NotificationEvent::class);
            }
        );

        $job = new SendNotificationJob($notification);
        $job->handle($repository, $eventSourcing);

        $this->assertSame('notifications', $capturedMetadata['queue_name'] ?? null);
        Log::shouldHaveReceived('info')->once();
    }

    public function test_job_marks_as_failed_on_exception(): void
    {
        Log::spy();
        $capturedReason = null;
        $capturedAttempts = null;

        $notification = new Notification();
        $notification->id = 2;

        $repository = Mockery::mock(NotificationRepositoryInterface::class);
        $repository->shouldReceive('markAsFailed')->once()->with(2, 'Something went wrong');
        $eventSourcing = Mockery::mock(EventSourcingService::class);
        $eventSourcing->shouldReceive('recordFailed')->once()->andReturnUsing(
            function (Notification $recordedNotification, string $reason, int $attempts, array $metadata) use (&$capturedReason, &$capturedAttempts): NotificationEvent {
                $this->assertSame(2, $recordedNotification->id);
                $capturedReason = $reason;
                $capturedAttempts = $attempts;
                $this->assertSame('RuntimeException', $metadata['exception_class'] ?? null);

                return Mockery::mock(NotificationEvent::class);
            }
        );

        $this->app->instance(NotificationRepositoryInterface::class, $repository);
        $this->app->instance(EventSourcingService::class, $eventSourcing);

        $job       = new SendNotificationJob($notification);
        $exception = new RuntimeException('Something went wrong');
        $job->failed($exception);

        $this->assertSame('Something went wrong', $capturedReason);
        $this->assertSame(0, $capturedAttempts);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_job_has_correct_retry_config(): void
    {
        $notification = new Notification();
        $notification->id = 1;

        $job = new SendNotificationJob($notification);

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30, 60], $job->backoff());
    }
}
