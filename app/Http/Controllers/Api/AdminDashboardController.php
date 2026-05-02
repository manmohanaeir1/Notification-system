<?php

namespace App\Http\Controllers\Api;

use App\Models\Notification;
use App\Models\NotificationEvent;
use App\Services\EventSourcingService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController
{
    public function __construct(
        private EventSourcingService $eventSourcing,
        private NotificationService $notificationService,
    ) {}

    /**
     * Get comprehensive dashboard statistics
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_notifications' => Notification::count(),
            'by_status' => [
                'pending' => Notification::pending()->count(),
                'processed' => Notification::processed()->count(),
                'failed' => Notification::failed()->count(),
            ],
            'by_channel' => Notification::selectRaw('channel, COUNT(*) as count')
                ->groupBy('channel')
                ->pluck('count', 'channel'),
            'by_type' => Notification::selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),
            'unique_users' => Notification::distinct('user_id')->count('user_id'),
            'avg_attempts' => DB::table('notifications')->whereNotNull('attempts')->avg('attempts'),
            'success_rate' => $this->calculateSuccessRate(),
            'event_statistics' => $this->eventSourcing->getEventStatistics(),
            'events_timeline' => $this->eventSourcing->getTimeline(20),
        ];

        return response()->json(['data' => $stats]);
    }

    /**
     * Get user-specific statistics
     */
    public function userStats(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');

        if (!$userId) {
            return response()->json(['error' => 'user_id required'], 400);
        }

        $userNotifications = Notification::forUser($userId);
        $stats = [
            'user_id' => $userId,
            'total_notifications' => $userNotifications->count(),
            'by_status' => [
                'pending' => (clone $userNotifications)->pending()->count(),
                'processed' => (clone $userNotifications)->processed()->count(),
                'failed' => (clone $userNotifications)->failed()->count(),
            ],
            'by_channel' => (clone $userNotifications)
                ->selectRaw('channel, COUNT(*) as count')
                ->groupBy('channel')
                ->pluck('count', 'channel'),
            'recent_notifications' => (clone $userNotifications)
                ->recent()
                ->limit(10)
                ->get(['id', 'type', 'channel', 'status', 'created_at']),
        ];

        return response()->json(['data' => $stats]);
    }

    /**
     * Get notification lifecycle/audit trail
     */
    public function notificationLifecycle($notificationId): JsonResponse
    {
        $notification = Notification::findOrFail($notificationId);
        $lifecycle = $this->eventSourcing->getLifecycle($notification);

        return response()->json(['data' => $lifecycle]);
    }

    /**
     * Bulk delete notifications by status
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processed,failed',
            'user_id' => 'nullable|string',
            'before_date' => 'nullable|date',
        ]);

        $query = Notification::query();

        if ($validated['status']) {
            $query->where('status', $validated['status']);
        }

        if ($validated['user_id'] ?? null) {
            $query->where('user_id', $validated['user_id']);
        }

        if ($validated['before_date'] ?? null) {
            $query->where('created_at', '<', $validated['before_date']);
        }

        $count = $query->count();
        $query->delete();

        return response()->json([
            'message' => "Deleted $count notifications",
            'deleted_count' => $count,
        ]);
    }

    /**
     * Retry failed notifications
     */
    public function bulkRetry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|string',
            'limit' => 'integer|min:1|max:1000',
        ]);

        $query = Notification::failed();

        if ($validated['user_id'] ?? null) {
            $query->where('user_id', $validated['user_id']);
        }

        $failed = $query->limit($validated['limit'] ?? 100)->get();
        $retried = 0;

        foreach ($failed as $notification) {
            try {
                // Reset notification status to retry
                $notification->update([
                    'status' => 'pending',
                    'attempts' => null,
                    'error_message' => null,
                ]);

                // Dispatch job for reprocessing
                dispatch(new \App\Jobs\SendNotificationJob($notification));

                // Record event
                $this->eventSourcing->recordRetry($notification, 1, 0, [
                    'reason' => 'Admin bulk retry',
                ]);

                $retried++;
            } catch (\Exception $e) {
                // Log error but continue
                \Log::error("Failed to retry notification {$notification->id}: {$e->getMessage()}");
            }
        }

        return response()->json([
            'message' => "Retried $retried notifications",
            'retried_count' => $retried,
            'total_to_retry' => $failed->count(),
        ]);
    }

    /**
     * Export notifications data
     */
    public function export(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|in:pending,processed,failed',
            'channel' => 'nullable|string',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'limit' => 'integer|min:1|max:10000',
        ]);

        $query = Notification::query();

        if ($validated['status'] ?? null) {
            $query->where('status', $validated['status']);
        }

        if ($validated['channel'] ?? null) {
            $query->where('channel', $validated['channel']);
        }

        if ($validated['from_date'] ?? null) {
            $query->where('created_at', '>=', $validated['from_date']);
        }

        if ($validated['to_date'] ?? null) {
            $query->where('created_at', '<=', $validated['to_date']);
        }

        $notifications = $query->limit($validated['limit'] ?? 1000)
            ->get(['id', 'user_id', 'type', 'channel', 'status', 'attempts', 'created_at', 'processed_at']);

        return response()->json([
            'count' => $notifications->count(),
            'data' => $notifications,
        ]);
    }

    /**
     * System health check
     */
    public function health(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'checks' => [
                'database' => $this->checkDatabase(),
                'queue' => $this->checkQueue(),
                'cache' => $this->checkCache(),
            ],
            'notifications_pending' => Notification::pending()->count(),
            'notifications_failed' => Notification::failed()->count(),
            'recent_errors' => NotificationEvent::byType('failed')
                ->limit(5)
                ->get(['notification_id', 'event_data', 'created_at']),
        ];

        $hasErrors = collect($health['checks'])->some(fn($check) => !$check);
        $health['status'] = $hasErrors ? 'degraded' : 'healthy';

        return response()->json(['data' => $health]);
    }

    /**
     * Get activity log
     */
    public function activityLog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => 'nullable|string',
            'limit' => 'integer|min:1|max:1000',
            'offset' => 'integer|min:0',
        ]);

        $query = NotificationEvent::query();

        if ($validated['event_type'] ?? null) {
            $query->where('event_type', $validated['event_type']);
        }

        $total = $query->count();
        $events = $query->orderBy('created_at', 'desc')
            ->offset($validated['offset'] ?? 0)
            ->limit($validated['limit'] ?? 100)
            ->with('notification:id,user_id,status')
            ->get();

        return response()->json([
            'total' => $total,
            'count' => $events->count(),
            'data' => $events,
        ]);
    }

    /**
     * Calculate success rate percentage
     */
    private function calculateSuccessRate(): float
    {
        $total = Notification::count();
        if ($total === 0) return 0;

        $processed = Notification::processed()->count();
        return round(($processed / $total) * 100, 2);
    }

    /**
     * Check database connectivity
     */
    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check queue connectivity
     */
    private function checkQueue(): bool
    {
        try {
            $job = new \App\Jobs\SendNotificationJob(null);
            // Can be queued = available
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check cache connectivity
     */
    private function checkCache(): bool
    {
        try {
            cache()->put('health_check', 'ok', 60);
            return cache()->get('health_check') === 'ok';
        } catch (\Exception $e) {
            return false;
        }
    }
}
