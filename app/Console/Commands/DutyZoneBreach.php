<?php

namespace App\Console\Commands;

use App\Models\DutyLocation;
use App\Models\EnforcerLocation;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class DutyZoneBreach extends Command
{
    protected $signature = 'temu:duty-zone-breach';
    protected $description = 'Alert admins when enforcers are outside their duty zone';

    public function handle(): int
    {
        $dutyLocations = DutyLocation::where('is_active', true)
            ->where('enforcer_id', '!=', null)
            ->get();

        $count = 0;
        foreach ($dutyLocations as $location) {
            $liveLocation = EnforcerLocation::where('enforcer_id', $location->enforcer_id)
                ->where('is_online', true)
                ->where('last_updated', '>=', now()->subMinutes(2))
                ->first();

            if (!$liveLocation) continue;

            // Haversine in meters
            $lat1 = deg2rad($liveLocation->latitude);
            $lat2 = deg2rad($location->latitude);
            $dLon = deg2rad($location->longitude - $liveLocation->longitude);
            $a = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;
            $distance = 6371000 * 2 * atan2(sqrt($a), sqrt(1 - $a));

            if ($distance > ($location->radius ?? 100)) {
                NotificationService::notifyAdmins(
                    'Duty Zone Breach',
                    "An enforcer is " . round($distance) . "m away from \"{$location->name}\"",
                    Notification::TYPE_DUTY_ZONE_BREACH,
                    'duty_location',
                    $location->id
                );
                $count++;
            }
        }

        $this->info("Flagged {$count} duty zone breaches");
        return self::SUCCESS;
    }
}