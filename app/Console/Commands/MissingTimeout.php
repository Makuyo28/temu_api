<?php

namespace App\Console\Commands;

use App\Models\EnforcerAttendance;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class MissingTimeout extends Command
{
    protected $signature = 'temu:missing-timeout';
    protected $description = 'Notify enforcers who forgot to time out today';

    public function handle(): int
    {
        $today = now()->toDateString();

        $records = EnforcerAttendance::where('date', $today)
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->get();

        $count = 0;
        foreach ($records as $record) {
            NotificationService::notifyUser(
                $record->enforcer_id,
                'Missing Time-Out',
                "You forgot to time out today. Please record your time-out.",
                Notification::TYPE_ATTENDANCE_MISSING_OUT,
                'attendance',
                $record->attendance_id
            );

            NotificationService::notifyAdmins(
                'Missing Time-Out',
                "An enforcer did not time out today",
                Notification::TYPE_ATTENDANCE_MISSING_OUT,
                'attendance',
                $record->attendance_id
            );
            $count++;
        }

        $this->info("Notified {$count} enforcers about missing time-out");
        return self::SUCCESS;
    }
}