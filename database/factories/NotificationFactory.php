<?php

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id' => $this->faker->uuid(),
            'type'    => $this->faker->randomElement(['alert', 'reminder', 'info']),
            'channel' => $this->faker->randomElement(NotificationChannel::values()),
            'message' => $this->faker->sentence(),
            'status'  => NotificationStatus::Pending->value,
            'attempts' => 0,
            'error_message' => null,
            'processed_at'  => null,
        ];
    }
}
