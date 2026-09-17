<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DailySummary extends Command
{
    protected $signature = 'temu:daily-summary';
    protected $description = 'Send daily ticket summary to admins';

    public function handle(): int
    {
        $today = now()->toDateString();

        $created = Ticket::whereDate('created_at', $today)->count();
        $paid = Ticket::whereDate('updated_at', $today)->where('status', 'paid')->count();

        $fineCollected = DB::table('ticket_violations')
            ->join('tickets', 'ticket_violations.ticket_id', '=', 'tickets.ticket_id')
            ->whereDate('tickets.updated_at', $today)
            ->where('tickets.status', 'paid')
            ->sum('ticket_violations.fine_amount');

        NotificationService::notifyAdmins(
            'Daily Summary',
            "Today: {$created} tickets issued, {$paid} paid, ₱" . number_format($fineCollected, 2) . " collected",
            Notification::TYPE_DAILY_SUMMARY
        );

        $this->info("Sent daily summary");
        return self::SUCCESS;
    }
}