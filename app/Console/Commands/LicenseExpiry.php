<?php

namespace App\Console\Commands;

use App\Models\Violator;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class LicenseExpiry extends Command
{
    protected $signature = 'temu:license-expiry';
    protected $description = 'Notify admins about expired or expiring licenses';

    public function handle(): int
    {
        // Already expired
        $expired = Violator::where('expiry', '<', now()->toDateString())->get();
        foreach ($expired as $v) {
            $name = trim("{$v->firstname} {$v->lastname}");
            NotificationService::notifyAdmins(
                'License Expired',
                "{$name}'s driver license ({$v->license}) expired on " . \Carbon\Carbon::parse($v->expiry)->format('M d, Y'),
                Notification::TYPE_LICENSE_EXPIRED,
                'violator',
                $v->violator_id
            );
        }

        // Expiring within 30 days
        $expiring = Violator::whereBetween('expiry', [
            now()->toDateString(),
            now()->addDays(30)->toDateString(),
        ])->get();

        foreach ($expiring as $v) {
            $name = trim("{$v->firstname} {$v->lastname}");
            NotificationService::notifyAdmins(
                'License Expiring Soon',
                "{$name}'s license ({$v->license}) expires on " . \Carbon\Carbon::parse($v->expiry)->format('M d, Y'),
                Notification::TYPE_LICENSE_EXPIRING,
                'violator',
                $v->violator_id
            );
        }

        $this->info("Notified about " . $expired->count() . " expired and " . $expiring->count() . " expiring licenses");
        return self::SUCCESS;
    }
}