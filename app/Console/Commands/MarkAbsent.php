<?php

namespace App\Console\Commands;

use App\Models\EnforcerSchedule;
use App\Models\EnforcerAttendance;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class MarkAbsent extends Command
{
    protected $signature = 'temu:mark-absent';
    protected $description = 'Mark enforcers as absent if they have not timed in by 10 AM';

    public function handle(): int
    {
        $today = now()->toDateString();

        $schedules = EnforcerSchedule::whereDate('schedule_date', $today)
            ->where('status', 'scheduled')
            ->get();

        $count = 0;
        foreach ($schedules as $schedule) {
            $hasAttendance = EnforcerAttendance::where('enforcer_id', $schedule->enforcer_id)
                ->where('date', $today)
                ->whereNotNull('time_in')
                ->exists();

            if (!$hasAttendance) {
                EnforcerAttendance::updateOrCreate(
                    ['enforcer_id' => $schedule->enforcer_id, 'date' => $today],
                    ['status' => 'absent', 'notes' => 'Auto-marked absent']
                );

                NotificationService::notifyAdminStaff(
                    'Absent Today',
                    "An enforcer did not time in for their {$schedule->start_time} shift today",
                    Notification::TYPE_ATTENDANCE_ABSENT,
                    'schedule',
                    $schedule->schedule_id
                );
                $count++;
            }
        }

        $this->info("Marked {$count} enforcers as absent");
        return self::SUCCESS;
    }
}