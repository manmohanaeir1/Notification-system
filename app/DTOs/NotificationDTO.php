<?php

namespace App\DTOs;

use App\Enums\NotificationChannel;
use App\Http\Requests\StoreNotificationRequest;

class NotificationDTO
{
    public function __construct(
        public readonly string $userId,
        public readonly string $type,
        public readonly string $message,
        public readonly NotificationChannel $channel,
    ) {}

    public static function fromRequest(StoreNotificationRequest $request): self
    {
        return new self(
            userId:  $request->validated('user_id'),
            type:    $request->validated('type'),
            message: $request->validated('message'),
            channel: NotificationChannel::from($request->validated('channel', 'database')),
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            userId:  $data['user_id'],
            type:    $data['type'],
            message: $data['message'],
            channel: NotificationChannel::from($data['channel'] ?? 'database'),
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'type'    => $this->type,
            'message' => $this->message,
            'channel' => $this->channel->value,
        ];
    }
}
