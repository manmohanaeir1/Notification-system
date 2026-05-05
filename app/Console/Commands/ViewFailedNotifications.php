<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

class ViewFailedNotifications extends Command
{
    protected $signature = 'notifications:view-failed {--limit=10}';
    protected $description = 'View failed notifications with retry attempts and error messages';

    public function handle(): int
    {
        $limit = $this->option('limit');

        $this->info('📋 Failed Notifications (Last ' . $limit . ')');
        $this->newLine();

        $failed = Notification::where('status', 'failed')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        if ($failed->isEmpty()) {
            $this->warn('No failed notifications found.');
            return 0;
        }

        $rows = $failed->map(function (Notification $notification) {
            return [
                'ID' => $notification->id,
                'User' => $notification->user_id,
                'Type' => $notification->type,
                'Channel' => $notification->channel->value ?? 'N/A',
                'Attempts' => $notification->attempts,
                'Failed At' => $notification->updated_at?->format('Y-m-d H:i:s'),
            ];
        })->toArray();

        $this->table(
            ['ID', 'User', 'Type', 'Channel', 'Attempts', 'Failed At'],
            $rows
        );

        $this->newLine();
        $this->info('Details for each notification:');
        $this->newLine();

        foreach ($failed as $index => $notification) {
            $this->line("<fg=cyan>─ Notification {$notification->id}:</>");
            $this->table(['Field', 'Value'], [
                ['Status', $notification->status],
                ['Type', $notification->type],
                ['Attempts', $notification->attempts],
                ['Channel', $notification->channel->value ?? 'Invalid'],
                ['Error Message', substr($notification->error_message ?? 'N/A', 0, 100)],
                ['Created', $notification->created_at?->format('Y-m-d H:i:s')],
                ['Processed', $notification->processed_at?->format('Y-m-d H:i:s') ?? 'Not yet'],
            ]);

            if ($index < count($failed) - 1) {
                $this->newLine();
            }
        }

        return 0;
    }
}
