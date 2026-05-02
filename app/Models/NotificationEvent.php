<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NotificationEvent extends Model
{
    use HasFactory;

    protected $table = 'notification_events';

    protected $fillable = [
        'notification_id',
        'event_type',
        'event_data',
        'triggered_by',
        'metadata',
    ];

    protected $casts = [
        'event_data' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Notification this event belongs to
     */
    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }

    /**
     * Get events for a specific notification
     */
    public static function forNotification($notificationId)
    {
        return static::where('notification_id', $notificationId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get timeline of all events
     */
    public static function timeline($limit = 50)
    {
        return static::with('notification')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get events by type
     */
    public static function byType($eventType)
    {
        return static::where('event_type', $eventType)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get event count by type
     */
    public static function countByType()
    {
        return static::selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->pluck('count', 'event_type');
    }
}
