<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        /*
        |----------------------------------------------------------------------
        | EXISTING: Mark offline enforcers every 2 minutes
        |----------------------------------------------------------------------
        */
        $schedule->call(function () {
            $controller = app(\App\Http\Controllers\API\LocationController::class);
            $controller->markAllOffline();
        })->everyTwoMinutes()
          ->name('mark-enforcers-offline')
          ->withoutOverlapping();

        /*
        |----------------------------------------------------------------------
        | ATTENDANCE NOTIFICATIONS
        |----------------------------------------------------------------------
        */

        // 7:45 AM weekdays — remind enforcers who haven't timed in yet
        $schedule->command('temu:attendance-reminders')
            ->weekdays()
            ->at('07:45')
            ->name('attendance-reminders')
            ->withoutOverlapping();

        // 10:00 AM weekdays — mark anyone still missing a time-in as absent
        $schedule->command('temu:mark-absent')
            ->weekdays()
            ->at('10:00')
            ->name('mark-absent')
            ->withoutOverlapping();

        // 6:00 PM weekdays — flag attendance records missing a time-out
        $schedule->command('temu:missing-timeout')
            ->weekdays()
            ->at('18:00')
            ->name('missing-timeout')
            ->withoutOverlapping();

        /*
        |----------------------------------------------------------------------
        | SCHEDULE NOTIFICATIONS
        |----------------------------------------------------------------------
        */

        // Every 5 min — notify enforcers 30 min before their shift
        $schedule->command('temu:shift-reminders')
            ->everyFiveMinutes()
            ->name('shift-reminders')
            ->withoutOverlapping();

        // Every 15 min — flag shifts that were missed (no show 15+ min after start)
        $schedule->command('temu:missed-shifts')
            ->everyFifteenMinutes()
            ->name('missed-shifts')
            ->withoutOverlapping();

        /*
        |----------------------------------------------------------------------
        | DUTY / LOCATION NOTIFICATIONS
        |----------------------------------------------------------------------
        */

        // Every 5 min — alert admins if enforcers are outside their duty zone
        $schedule->command('temu:duty-zone-breach')
            ->everyFiveMinutes()
            ->name('duty-zone-breach')
            ->withoutOverlapping();

        // Every 5 min — alert admins if an on-shift enforcer has gone silent
        $schedule->command('temu:enforcer-offline-check')
            ->everyFiveMinutes()
            ->name('enforcer-offline-check')
            ->withoutOverlapping();

        /*
        |----------------------------------------------------------------------
        | TICKET / LICENSE NOTIFICATIONS
        |----------------------------------------------------------------------
        */

        // 9:00 AM daily — flag tickets overdue for payment
        $schedule->command('temu:overdue-tickets')
            ->dailyAt('09:00')
            ->name('overdue-tickets')
            ->withoutOverlapping();

        // 8:00 AM daily — notify admins of expired / expiring licenses
        $schedule->command('temu:license-expiry')
            ->dailyAt('08:00')
            ->name('license-expiry')
            ->withoutOverlapping();

        /*
        |----------------------------------------------------------------------
        | REPORT NOTIFICATIONS
        |----------------------------------------------------------------------
        */

        // 5:00 PM daily — send the day's ticket & collection summary
        $schedule->command('temu:daily-summary')
            ->dailyAt('17:00')
            ->name('daily-summary')
            ->withoutOverlapping();

        // Sunday 8:00 PM — weekly report
        $schedule->command('temu:weekly-report')
            ->sundays()
            ->at('20:00')
            ->name('weekly-report')
            ->withoutOverlapping();

        // Last day of month at 11:00 PM — monthly report
        $schedule->command('temu:monthly-report')
            ->lastDayOfMonth()
            ->at('23:00')
            ->name('monthly-report')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}