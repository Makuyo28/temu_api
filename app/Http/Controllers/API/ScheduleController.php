<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\EnforcerSchedule;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $date = $request->get('date', now()->toDateString());

        $query = EnforcerSchedule::with(['enforcer', 'dutyLocation'])
            ->whereDate('schedule_date', $date);

        if ($request->filled('enforcer_id')) {
            $query->where('enforcer_id', $request->enforcer_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->orderBy('start_time')->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enforcer_id' => 'required|exists:users,user_id',
            'duty_location_id' => 'nullable|exists:duty_locations,id',
            'schedule_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'shift_type' => 'nullable|string|in:morning,afternoon,night,full',
            'duties' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Conflict detection
        $conflict = EnforcerSchedule::where('enforcer_id', $request->enforcer_id)
            ->whereDate('schedule_date', $request->schedule_date)
            ->where(function ($q) use ($request) {
                $q->whereBetween('start_time', [$request->start_time, $request->end_time])
                    ->orWhereBetween('end_time', [$request->start_time, $request->end_time]);
            })->exists();

        if ($conflict) {
            // =============== NOTIFICATION: Schedule Conflict ===============
            try {
                NotificationService::notifyUser(
                    $request->user()->user_id,
                    'Schedule Conflict',
                    "The schedule for {$request->schedule_date} at {$request->start_time} conflicts with an existing shift",
                    Notification::TYPE_SCHEDULE_CONFLICT
                );
            } catch (\Throwable $ne) {
                Log::warning('Schedule conflict notification failed: ' . $ne->getMessage());
            }

            return response()->json([
                'message' => 'Schedule conflict detected for this enforcer at the same time'
            ], 422);
        }

        $schedule = EnforcerSchedule::create([
            'enforcer_id' => $request->enforcer_id,
            'duty_location_id' => $request->duty_location_id,
            'schedule_date' => $request->schedule_date,
            'start_time' => $request->schedule_date . ' ' . $request->start_time . ':00',
            'end_time' => $request->schedule_date . ' ' . $request->end_time . ':00',
            'shift_type' => $request->shift_type,
            'duties' => $request->duties,
            'status' => 'scheduled',
            'notes' => $request->notes,
        ]);

        // =============== NOTIFICATION: New Schedule Assigned ===============
        try {
            $dateStr = \Carbon\Carbon::parse($schedule->schedule_date)->format('M d, Y');
            $start = \Carbon\Carbon::parse($schedule->start_time)->format('h:i A');
            $end = \Carbon\Carbon::parse($schedule->end_time)->format('h:i A');

            NotificationService::notifyUser(
                $schedule->enforcer_id,
                'New Schedule Assigned',
                "You have a new shift on {$dateStr} from {$start} to {$end}",
                Notification::TYPE_SCHEDULE_ASSIGNED,
                'schedule',
                $schedule->schedule_id
            );
        } catch (\Throwable $ne) {
            Log::warning('Schedule assigned notification failed: ' . $ne->getMessage());
        }

        return response()->json([
            'message' => 'Schedule created successfully',
            'data' => $schedule->load(['enforcer', 'dutyLocation'])
        ], 201);
    }

    public function show($id)
    {
        return response()->json(EnforcerSchedule::with(['enforcer', 'dutyLocation'])->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $schedule = EnforcerSchedule::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'enforcer_id' => 'sometimes|exists:users,user_id',
            'duty_location_id' => 'nullable|exists:duty_locations,id',
            'schedule_date' => 'sometimes|date',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
            'shift_type' => 'nullable|string|in:morning,afternoon,night,full',
            'duties' => 'nullable|string',
            'status' => 'nullable|in:scheduled,in_progress,completed,cancelled',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();

        if ($request->has('start_time') && $request->has('schedule_date')) {
            $data['start_time'] = $request->schedule_date . ' ' . $request->start_time . ':00';
        }
        if ($request->has('end_time') && $request->has('schedule_date')) {
            $data['end_time'] = $request->schedule_date . ' ' . $request->end_time . ':00';
        }

        $schedule->update($data);

        // =============== NOTIFICATION: Schedule Updated ===============
        try {
            NotificationService::notifyUser(
                $schedule->enforcer_id,
                'Schedule Updated',
                "Your shift schedule has been updated",
                Notification::TYPE_SCHEDULE_UPDATED,
                'schedule',
                $schedule->schedule_id
            );
        } catch (\Throwable $ne) {
            Log::warning('Schedule update notification failed: ' . $ne->getMessage());
        }

        return response()->json([
            'message' => 'Schedule updated successfully',
            'data' => $schedule->load(['enforcer', 'dutyLocation'])
        ]);
    }

    public function destroy($id)
    {
        $schedule = EnforcerSchedule::findOrFail($id);
        $enforcerId = $schedule->enforcer_id;
        $scheduleId = $schedule->schedule_id;

        $schedule->delete();

        // =============== NOTIFICATION: Schedule Cancelled ===============
        try {
            NotificationService::notifyUser(
                $enforcerId,
                'Schedule Cancelled',
                "One of your shifts has been cancelled",
                Notification::TYPE_SCHEDULE_CANCELLED,
                'schedule',
                $scheduleId
            );
        } catch (\Throwable $ne) {
            Log::warning('Schedule cancel notification failed: ' . $ne->getMessage());
        }

        return response()->json(['message' => 'Schedule deleted successfully']);
    }

    public function getEnforcerSchedules(Request $request, $enforcerId)
    {
        $startDate = $request->get('start_date', now()->startOfWeek()->toDateString());
        $endDate = $request->get('end_date', now()->endOfWeek()->toDateString());
        $dateFilter = $request->get('date');

        $query = EnforcerSchedule::with(['enforcer', 'dutyLocation'])
            ->where('enforcer_id', $enforcerId);

        if ($dateFilter) {
            $query->whereDate('schedule_date', $dateFilter);
        } else {
            $query->whereBetween('schedule_date', [$startDate, $endDate]);
        }

        return response()->json(
            $query->orderBy('schedule_date')->orderBy('start_time')->get()
        );
    }

    public function getTodaySchedules(Request $request)
    {
        return response()->json(
            EnforcerSchedule::with(['enforcer', 'dutyLocation'])
                ->today()->orderBy('start_time')->get()
        );
    }

    public function getWeeklySchedules(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfWeek()->toDateString());
        $endDate = $request->get('end_date', now()->endOfWeek()->toDateString());

        return response()->json(
            EnforcerSchedule::with(['enforcer', 'dutyLocation'])
                ->whereBetween('schedule_date', [$startDate, $endDate])
                ->orderBy('schedule_date')->orderBy('start_time')
                ->get()->groupBy('schedule_date')
        );
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:scheduled,in_progress,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $schedule = EnforcerSchedule::findOrFail($id);
        $schedule->update(['status' => $request->status]);

        // =============== NOTIFICATION: Schedule Cancelled via status change ===============
        if ($request->status === 'cancelled') {
            try {
                NotificationService::notifyUser(
                    $schedule->enforcer_id,
                    'Schedule Cancelled',
                    "Your shift has been cancelled",
                    Notification::TYPE_SCHEDULE_CANCELLED,
                    'schedule',
                    $schedule->schedule_id
                );
            } catch (\Throwable $ne) {
                Log::warning('Status change notification failed: ' . $ne->getMessage());
            }
        }

        return response()->json([
            'message' => 'Schedule status updated successfully',
            'data' => $schedule
        ]);
    }
}