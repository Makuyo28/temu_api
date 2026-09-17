<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\Setting;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class OverdueTickets extends Command
{
    protected $signature = 'temu:overdue-tickets';
    protected $description = 'Alert enforcers about tickets with overdue payment';

    public function handle(): int
    {
        $setting = Setting::where('setting_key', 'fine_payment_due_days')->first();
        $dueDays = $setting ? intval($setting->setting_value) : 15;

        $cutoff = now()->subDays($dueDays);

        $overdue = Ticket::where('status', 'issued')
            ->where('created_at', '<=', $cutoff)
            ->get();

        $count = 0;
        foreach ($overdue as $ticket) {
            NotificationService::notifyUser(
                $ticket->enforcer_id,
                'Ticket Overdue',
                "Ticket {$ticket->ticket_number} has been unpaid for over {$dueDays} days",
                Notification::TYPE_TICKET_OVERDUE,
                'ticket',
                $ticket->ticket_id
            );
            $count++;
        }

        $this->info("Flagged {$count} overdue tickets");
        return self::SUCCESS;
    }
}