<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        RateLimiter::clear('notifications:user:user-123');
    }

    public function test_can_create_notification_successfully(): void
    {
        $response = $this->postJson('/api/v1/notifications', [
            'user_id' => 'user-123',
            'type'    => 'alert',
            'message' => 'Test notification message',
            'channel' => 'database',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('notifications', [
            'user_id' => 'user-123',
            'status'  => NotificationStatus::Pending->value,
        ]);
        Queue::assertPushed(\App\Jobs\SendNotificationJob::class);
    }

    public function test_validation_fails_with_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/notifications', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'type', 'message']);
    }

    public function test_rate_limit_blocks_after_10_requests(): void
    {
        RateLimiter::clear('notifications:user:rate-limit-user');

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/notifications', [
                'user_id' => 'rate-limit-user',
                'type'    => 'alert',
                'message' => 'Message ' . $i,
            ])->assertStatus(201);
        }

        $response = $this->postJson('/api/v1/notifications', [
            'user_id' => 'rate-limit-user',
            'type'    => 'alert',
            'message' => '11th message',
        ]);

        $response->assertStatus(429);
    }

    public function test_can_get_recent_notifications(): void
    {
        Notification::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    public function test_can_filter_notifications_by_status(): void
    {
        Notification::factory()->count(3)->create(['status' => NotificationStatus::Pending->value]);
        Notification::factory()->count(2)->create(['status' => NotificationStatus::Processed->value]);

        $response = $this->getJson('/api/v1/notifications?status=pending');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_summary_returns_correct_counts(): void
    {
        Notification::factory()->count(2)->create(['status' => NotificationStatus::Pending->value]);
        Notification::factory()->count(3)->create(['status' => NotificationStatus::Processed->value]);
        Notification::factory()->count(1)->create(['status' => NotificationStatus::Failed->value]);

        $response = $this->getJson('/api/v1/notifications/summary');

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 6)
            ->assertJsonPath('data.pending', 2)
            ->assertJsonPath('data.processed', 3)
            ->assertJsonPath('data.failed', 1);
    }
}
