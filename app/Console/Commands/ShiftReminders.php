<?php

namespace App\Console\Commands;

use App\Models\EnforcerSchedule;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class ShiftReminders extends Command
{
    protected $signature = 'temu:shift-reminders';
    protected $description = 'Notify enforcers 30 minutes before their shift starts';

    public function handle(): int
    {
        $today = now()->toDateString();
        $windowStart = now()->addMinutes(28);
        $windowEnd = now()->addMinutes(32);

        $schedules = EnforcerSchedule::whereDate('schedule_date', $today)
            ->where('status', 'scheduled')
            ->get();

        $count = 0;
        foreach ($schedules as $schedule) {
            $startTime = \Carbon\Carbon::parse($schedule->start_time);
            if ($startTime->between($windowStart, $windowEnd)) {
                $start = $startTime->format('h:i A');
                NotificationService::notifyUser(
                    $schedule->enforcer_id,
                    'Shift Starting Soon',
                    "Your shift starts at {$start}. Get ready!",
                    Notification::TYPE_SCHEDULE_STARTING,
                    'schedule',
                    $schedule->schedule_id
                );
                $count++;
            }
        }

        $this->info("Sent {$count} shift reminders");
        return self::SUCCESS;
    }
}