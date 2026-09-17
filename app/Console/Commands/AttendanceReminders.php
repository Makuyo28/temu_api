<?php

namespace App\Console\Commands;

use App\Models\EnforcerSchedule;
use App\Models\EnforcerAttendance;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class AttendanceReminders extends Command
{
    protected $signature = 'temu:attendance-reminders';
    protected $description = 'Send attendance reminders to enforcers who have shifts today and have not timed in';

    public function handle(): int
    {
        $today = now()->toDateString();

        $schedules = EnforcerSchedule::whereDate('schedule_date', $today)
            ->where('status', 'scheduled')
            ->get();

        $count = 0;
        foreach ($schedules as $schedule) {
            $alreadyTimedIn = EnforcerAttendance::where('enforcer_id', $schedule->enforcer_id)
                ->where('date', $today)
                ->whereNotNull('time_in')
                ->exists();

            if (!$alreadyTimedIn) {
                $start = \Carbon\Carbon::parse($schedule->start_time)->format('h:i A');
                NotificationService::notifyUser(
                    $schedule->enforcer_id,
                    'Attendance Reminder',
                    "Reminder: You have a shift starting at {$start} today. Don't forget to time in.",
                    Notification::TYPE_ATTENDANCE_REMINDER,
                    'schedule',
                    $schedule->schedule_id
                );
                $count++;
            }
        }

        $this->info("Sent {$count} attendance reminders");
        return self::SUCCESS;
    }
}