<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class WeeklyReport extends Command
{
    protected $signature = 'temu:weekly-report';
    protected $description = 'Send weekly report notification to admins';

    public function handle(): int
    {
        $start = now()->startOfWeek()->toDateString();
        $end = now()->endOfWeek()->toDateString();

        $count = Ticket::whereBetween('created_at', [$start . ' 00:00:00', $end . ' 23:59:59'])->count();

        NotificationService::notifyRoles(
            ['admin', 'staff'],
            'Weekly Report Ready',
            "Week of {$start} – {$end}: {$count} tickets issued",
            Notification::TYPE_REPORT_WEEKLY
        );

        $this->info("Sent weekly report");
        return self::SUCCESS;
    }
}