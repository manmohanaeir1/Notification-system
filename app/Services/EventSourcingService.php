<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationEvent;

class EventSourcingService
{
    /**
     * Record a notification event
     */
    public function recordEvent(
        Notification $notification,
        string $eventType,
        array $eventData = [],
        ?string $triggeredBy = null,
        array $metadata = []
    ): NotificationEvent {
        return NotificationEvent::create([
            'notification_id' => $notification->id,
            'event_type' => $eventType,
            'event_data' => $eventData,
            'triggered_by' => $triggeredBy ?? 'system',
            'metadata' => $metadata,
        ]);
    }

    /**
     * Record notification created event
     */
    public function recordCreated(Notification $notification, array $metadata = []): NotificationEvent
    {
        return $this->recordEvent(
            $notification,
            'created',
            [
                'user_id' => $notification->user_id,
                'type' => $notification->type,
                'channel' => $notification->channel,
            ],
            'api',
            $metadata
        );
    }

    /**
     * Record notification sent event
     */
    public function recordSent(Notification $notification, int $attempt = 1, array $metadata = []): NotificationEvent
    {
        return $this->recordEvent(
            $notification,
            'sent',
            [
                'attempt' => $attempt,
                'status_changed_from' => 'pending',
                'status_changed_to' => 'processed',
            ],
            'queue_worker',
            $metadata
        );
    }

    /**
     * Record notification failed event
     */
    public function recordFailed(Notification $notification, string $reason, int $attempt = 1, array $metadata = []): NotificationEvent
    {
        return $this->recordEvent(
            $notification,
            'failed',
            [
                'attempt' => $attempt,
                'reason' => $reason,
                'status_changed_from' => 'pending',
                'status_changed_to' => 'failed',
            ],
            'queue_worker',
            $metadata
        );
    }

    /**
     * Record notification retry event
     */
    public function recordRetry(Notification $notification, int $attempt = 1, int $delaySeconds = 0, array $metadata = []): NotificationEvent
    {
        return $this->recordEvent(
            $notification,
            'retry',
            [
                'attempt' => $attempt,
                'delay_seconds' => $delaySeconds,
                'reason' => $metadata['reason'] ?? 'Scheduled retry',
            ],
            'queue_worker',
            $metadata
        );
    }

    /**
     * Record notification deleted event
     */
    public function recordDeleted(Notification $notification, string $reason = '', array $metadata = []): NotificationEvent
    {
        return $this->recordEvent(
            $notification,
            'deleted',
            ['reason' => $reason],
            'admin',
            $metadata
        );
    }

    /**
     * Get complete notification lifecycle
     */
    public function getLifecycle(Notification $notification): array
    {
        $events = NotificationEvent::forNotification($notification->id);

        return [
            'notification_id' => $notification->id,
            'final_status' => $notification->status,
            'created_at' => $notification->created_at,
            'processed_at' => $notification->processed_at,
            'total_events' => count($events),
            'events' => $events->map(function (NotificationEvent $event) {
                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'triggered_by' => $event->triggered_by,
                    'event_data' => $event->event_data,
                    'metadata' => $event->metadata,
                    'created_at' => $event->created_at,
                ];
            })->toArray(),
        ];
    }

    /**
     * Get statistics by event type
     */
    public function getEventStatistics(): array
    {
        return NotificationEvent::countByType()->toArray();
    }

    /**
     * Get timeline of recent events
     */
    public function getTimeline($limit = 50): array
    {
        $events = NotificationEvent::timeline($limit);

        return $events->map(function (NotificationEvent $event) {
            return [
                'id' => $event->id,
                'notification_id' => $event->notification_id,
                'event_type' => $event->event_type,
                'triggered_by' => $event->triggered_by,
                'user_id' => $event->notification?->user_id,
                'created_at' => $event->created_at,
            ];
        })->toArray();
    }
}
