<?php

namespace App\Console\Commands;

use App\Models\EnforcerSchedule;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class MissedShifts extends Command
{
    protected $signature = 'temu:missed-shifts';
    protected $description = 'Flag schedules that were missed (no show after start time)';

    public function handle(): int
    {
        $today = now()->toDateString();

        $schedules = EnforcerSchedule::whereDate('schedule_date', $today)
            ->where('status', 'scheduled')
            ->get();

        $count = 0;
        foreach ($schedules as $schedule) {
            $startTime = \Carbon\Carbon::parse($schedule->start_time);
            if ($startTime->addMinutes(15)->lt(now())) {
                NotificationService::notifyUser(
                    $schedule->enforcer_id,
                    'Missed Shift',
                    "You missed your shift that started at {$startTime->format('h:i A')}",
                    Notification::TYPE_SCHEDULE_MISSED,
                    'schedule',
                    $schedule->schedule_id
                );

                NotificationService::notifyAdminStaff(
                    'Missed Shift',
                    "An enforcer missed their {$startTime->format('h:i A')} shift today",
                    Notification::TYPE_SCHEDULE_MISSED,
                    'schedule',
                    $schedule->schedule_id
                );
                $count++;
            }
        }

        $this->info("Flagged {$count} missed shifts");
        return self::SUCCESS;
    }
}