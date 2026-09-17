<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\EnforcerLocation;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LocationController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $user = $request->user();
        $wasOffline = false;

        $existing = EnforcerLocation::where('enforcer_id', $user->user_id)->first();
        if ($existing) {
            $wasOffline = !$existing->is_online
                || !$existing->last_updated
                || $existing->last_updated < now()->subMinutes(5);
        } else {
            $wasOffline = true;
        }

        EnforcerLocation::updateOrCreate(
            ['enforcer_id' => $user->user_id],
            [
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'accuracy' => $request->accuracy,
                'speed' => $request->speed,
                'last_updated' => now(),
                'is_online' => true,
            ]
        );

        // =============== NOTIFICATION: Enforcer Came Online ===============
        if ($wasOffline) {
            try {
                $name = trim("{$user->firstname} {$user->lastname}");
                NotificationService::notifyAdmins(
                    'Enforcer Online',
                    "{$name} is now online and sharing location",
                    Notification::TYPE_ENFORCER_ONLINE,
                    'user',
                    $user->user_id
                );
            } catch (\Throwable $ne) {
                Log::warning('Enforcer online notification failed: ' . $ne->getMessage());
            }
        }

        return response()->json(['message' => 'Location updated']);
    }

    public function markOffline(Request $request)
    {
        $user = $request->user();

        $updated = EnforcerLocation::where('enforcer_id', $user->user_id)
            ->update(['is_online' => false, 'last_updated' => now()]);

        // =============== NOTIFICATION: Enforcer Went Offline ===============
        if ($updated > 0) {
            try {
                $name = trim("{$user->firstname} {$user->lastname}");
                NotificationService::notifyAdmins(
                    'Enforcer Offline',
                    "{$name} has gone offline",
                    Notification::TYPE_ENFORCER_OFFLINE,
                    'user',
                    $user->user_id
                );
            } catch (\Throwable $ne) {
                Log::warning('Enforcer offline notification failed: ' . $ne->getMessage());
            }
        }

        return response()->json(['message' => 'Marked offline']);
    }

    public function heartbeat(Request $request)
    {
        $user = $request->user();

        EnforcerLocation::where('enforcer_id', $user->user_id)->update([
            'last_updated' => now(),
            'is_online' => true,
        ]);

        return response()->json(['message' => 'Heartbeat received']);
    }

    public function get(Request $request)
    {
        $location = EnforcerLocation::where('enforcer_id', $request->user()->user_id)->first();
        return response()->json($location);
    }

    public function getEnforcer($id)
    {
        $location = EnforcerLocation::where('enforcer_id', $id)->first();

        // =============== NOTIFICATION: Unauthorized Location Access ===============
        $currentUser = request()->user();
        if ($currentUser && $currentUser->user_id != $id && $currentUser->role === 'enforcer') {
            try {
                NotificationService::notifyAdmins(
                    'Unauthorized Location Access',
                    "{$currentUser->firstname} {$currentUser->lastname} attempted to view another enforcer's location",
                    Notification::TYPE_LOCATION_UNAUTHORIZED,
                    'user',
                    $currentUser->user_id
                );
            } catch (\Throwable $ne) {
                Log::warning('Unauthorized access notification failed: ' . $ne->getMessage());
            }
        }

        return response()->json($location);
    }

    public function getAllActive()
    {
        return response()->json(
            EnforcerLocation::with('enforcer')
                ->where('is_online', true)
                ->where('last_updated', '>=', now()->subMinutes(2))
                ->get()
                ->map(function ($loc) {
                    return [
                        'enforcer_id' => $loc->enforcer_id,
                        'firstname' => $loc->enforcer?->firstname,
                        'lastname' => $loc->enforcer?->lastname,
                        'role' => $loc->enforcer?->role,
                        'email' => $loc->enforcer?->email,
                        'contact_number' => $loc->enforcer?->contact_number,
                        'latitude' => $loc->latitude,
                        'longitude' => $loc->longitude,
                        'accuracy' => $loc->accuracy,
                        'speed' => $loc->speed,
                        'is_online' => $loc->is_online,
                        'last_updated' => $loc->last_updated,
                    ];
                })
        );
    }
}