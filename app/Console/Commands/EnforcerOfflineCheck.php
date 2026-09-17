<?php

namespace App\Console\Commands;

use App\Models\EnforcerSchedule;
use App\Models\EnforcerLocation;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class EnforcerOfflineCheck extends Command
{
    protected $signature = 'temu:enforcer-offline-check';
    protected $description = 'Notify admins when an on-shift enforcer goes offline';

    public function handle(): int
    {
        $today = now()->toDateString();

        $schedules = EnforcerSchedule::whereDate('schedule_date', $today)
            ->where('status', 'in_progress')
            ->pluck('enforcer_id')
            ->toArray();

        $count = 0;
        foreach ($schedules as $enforcerId) {
            $loc = EnforcerLocation::where('enforcer_id', $enforcerId)->first();
            if (!$loc || !$loc->last_updated || $loc->last_updated < now()->subMinutes(5)) {
                NotificationService::notifyAdmins(
                    'Enforcer Offline During Shift',
                    "An on-shift enforcer has not shared location for 5+ minutes",
                    Notification::TYPE_DUTY_ENFORCER_OFFLINE,
                    'user',
                    $enforcerId
                );
                $count++;
            }
        }

        $this->info("Flagged {$count} offline enforcers during shift");
        return self::SUCCESS;
    }
}