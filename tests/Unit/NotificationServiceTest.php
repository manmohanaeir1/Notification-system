<?php

namespace Tests\Unit;

use App\DTOs\NotificationDTO;
use App\Enums\NotificationChannel;
use App\Exceptions\RateLimitExceededException;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    private NotificationRepositoryInterface $repository;
    private \App\Services\EventSourcingService $eventSourcing;
    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->repository = Mockery::mock(NotificationRepositoryInterface::class);
        $this->eventSourcing = Mockery::mock(\App\Services\EventSourcingService::class);
        $this->eventSourcing->shouldIgnoreMissing();
        $this->service    = new NotificationService($this->repository, $this->eventSourcing);
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('notifications:user:test-user');
        Mockery::close();
        parent::tearDown();
    }

    public function test_send_creates_notification_and_dispatches_job(): void
    {
        $dto          = new NotificationDTO('test-user', 'alert', 'Hello', NotificationChannel::Database);
        $notification = new Notification(['id' => 1, 'user_id' => 'test-user']);

        $this->repository->shouldReceive('create')->once()->with($dto)->andReturn($notification);

        $result = $this->service->send($dto);

        Queue::assertPushed(SendNotificationJob::class);
        $this->assertSame($notification, $result);
    }

    public function test_send_throws_exception_when_rate_limit_exceeded(): void
    {
        $this->expectException(RateLimitExceededException::class);

        $dto = new NotificationDTO('test-user', 'alert', 'Hello', NotificationChannel::Database);

        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit('notifications:user:test-user', 3600);
        }

        $this->service->send($dto);
    }

    public function test_get_summary_delegates_to_repository(): void
    {
        $expected = ['total' => 5, 'processed' => 3, 'failed' => 1, 'pending' => 1];

        $this->repository->shouldReceive('getSummary')->once()->with('user-1')->andReturn($expected);

        $result = $this->service->getSummary('user-1');

        $this->assertSame($expected, $result);
    }
}
