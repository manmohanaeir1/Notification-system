<?php

namespace Tests\Unit;

use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
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

        $notification = new Notification();
        $notification->id      = 1;
        $notification->type    = 'alert';
        $notification->user_id = 'user-1';
        $notification->channel = \App\Enums\NotificationChannel::Database;

        $repository = Mockery::mock(NotificationRepositoryInterface::class);
        $repository->shouldReceive('incrementAttempts')->once()->with(1);
        $repository->shouldReceive('markAsProcessed')->once()->with(1);

        $job = new SendNotificationJob($notification);
        $job->handle($repository);

        Log::shouldHaveReceived('info')->once();
    }

    public function test_job_marks_as_failed_on_exception(): void
    {
        Log::spy();

        $notification = new Notification();
        $notification->id = 2;

        $repository = Mockery::mock(NotificationRepositoryInterface::class);
        $repository->shouldReceive('markAsFailed')->once()->with(2, 'Something went wrong');

        $this->app->instance(NotificationRepositoryInterface::class, $repository);

        $job       = new SendNotificationJob($notification);
        $exception = new RuntimeException('Something went wrong');
        $job->failed($exception);

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
