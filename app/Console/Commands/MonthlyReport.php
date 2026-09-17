<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class MonthlyReport extends Command
{
    protected $signature = 'temu:monthly-report';
    protected $description = 'Send monthly report notification to admins';

    public function handle(): int
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $count = Ticket::whereBetween('created_at', [$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59'])->count();

        NotificationService::notifyAdmins(
            'Monthly Report Ready',
            "Month of " . now()->format('F Y') . ": {$count} tickets issued",
            Notification::TYPE_REPORT_MONTHLY
        );

        $this->info("Sent monthly report");
        return self::SUCCESS;
    }
}