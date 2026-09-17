<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketViolation;
use App\Models\Violator;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    // Get today's report
    public function todayReport(Request $request)
    {
        $today = now()->toDateString();
        
        // Tickets issued today
        $todayTickets = Ticket::with(['violator', 'vehicle', 'enforcer', 'violations.violationType'])
            ->whereDate('created_at', $today)
            ->get();
        
        // Statistics
        $totalTickets = $todayTickets->count();
        $totalViolations = TicketViolation::whereIn('ticket_id', $todayTickets->pluck('ticket_id'))->count();
        $totalFines = TicketViolation::whereIn('ticket_id', $todayTickets->pluck('ticket_id'))->sum('fine_amount');
        $paidTickets = $todayTickets->where('status', 'paid')->count();
        $issuedTickets = $todayTickets->where('status', 'issued')->count();
        
        // Collection rate
        $collectionRate = $totalTickets > 0 ? round(($paidTickets / $totalTickets) * 100, 2) : 0;
        
        // Top violations today
        $topViolations = TicketViolation::select(
            'violation_types.violation_name',
            DB::raw('COUNT(*) as count')
        )
        ->join('violation_types', 'ticket_violations.violation_id', '=', 'violation_types.violation_id')
        ->whereIn('ticket_id', $todayTickets->pluck('ticket_id'))
        ->groupBy('violation_types.violation_name')
        ->orderBy('count', 'desc')
        ->limit(5)
        ->get();
        
        // Enforcer performance today
        $enforcerStats = User::where('role', 'enforcer')
            ->withCount(['tickets' => function ($query) use ($today) {
                $query->whereDate('created_at', $today);
            }])
            ->get()
            ->map(function ($enforcer) {
                return [
                    'name' => $enforcer->firstname . ' ' . $enforcer->lastname,
                    'tickets_count' => $enforcer->tickets_count,
                ];
            })
            ->filter(function ($enforcer) {
                return $enforcer['tickets_count'] > 0;
            })
            ->values();
        
        return response()->json([
            'date' => $today,
            'summary' => [
                'total_tickets' => $totalTickets,
                'total_violations' => $totalViolations,
                'total_fines' => $totalFines,
                'paid_tickets' => $paidTickets,
                'issued_tickets' => $issuedTickets,
                'collection_rate' => $collectionRate,
            ],
            'recent_tickets' => $todayTickets->take(10),
            'top_violations' => $topViolations,
            'enforcer_performance' => $enforcerStats,
        ]);
    }
    
    // Get weekly report
    public function weeklyReport(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfWeek()->toDateString());
        $endDate = $request->get('end_date', now()->endOfWeek()->toDateString());
        
        // Tickets in date range
        $weeklyTickets = Ticket::with(['violator', 'vehicle', 'enforcer', 'violations.violationType'])
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->get();
        
        // Daily breakdown
        $dailyBreakdown = [];
        $currentDate = strtotime($startDate);
        $endDateTime = strtotime($endDate);
        
        while ($currentDate <= $endDateTime) {
            $date = date('Y-m-d', $currentDate);
            $dayTickets = $weeklyTickets->filter(function ($ticket) use ($date) {
                return substr($ticket->created_at, 0, 10) === $date;
            });
            
            $dailyBreakdown[] = [
                'date' => $date,
                'day_name' => date('l', $currentDate),
                'tickets_count' => $dayTickets->count(),
                'fines_total' => TicketViolation::whereIn('ticket_id', $dayTickets->pluck('ticket_id'))->sum('fine_amount'),
            ];
            $currentDate = strtotime('+1 day', $currentDate);
        }
        
        // Weekly statistics
        $totalTickets = $weeklyTickets->count();
        $totalViolations = TicketViolation::whereIn('ticket_id', $weeklyTickets->pluck('ticket_id'))->count();
        $totalFines = TicketViolation::whereIn('ticket_id', $weeklyTickets->pluck('ticket_id'))->sum('fine_amount');
        $paidTickets = $weeklyTickets->where('status', 'paid')->count();
        $issuedTickets = $weeklyTickets->where('status', 'issued')->count();
        $contestedTickets = $weeklyTickets->where('status', 'contested')->count();
        $dismissedTickets = $weeklyTickets->where('status', 'dismissed')->count();
        
        $averageDailyTickets = $totalTickets / 7;
        $collectionRate = $totalTickets > 0 ? round(($paidTickets / $totalTickets) * 100, 2) : 0;
        
        // Top violators of the week
        $topViolators = Violator::select('violators.firstname', 'violators.lastname', 'violators.license')
            ->withCount(['tickets' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            }])
            ->having('tickets_count', '>', 0)
            ->orderBy('tickets_count', 'desc')
            ->limit(5)
            ->get();
        
        // Top violations of the week
        $topViolations = TicketViolation::select(
            'violation_types.violation_name',
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(ticket_violations.fine_amount) as total_fine')
        )
        ->join('violation_types', 'ticket_violations.violation_id', '=', 'violation_types.violation_id')
        ->whereIn('ticket_id', $weeklyTickets->pluck('ticket_id'))
        ->groupBy('violation_types.violation_name')
        ->orderBy('count', 'desc')
        ->limit(5)
        ->get();
        
        return response()->json([
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'summary' => [
                'total_tickets' => $totalTickets,
                'total_violations' => $totalViolations,
                'total_fines' => $totalFines,
                'paid_tickets' => $paidTickets,
                'issued_tickets' => $issuedTickets,
                'contested_tickets' => $contestedTickets,
                'dismissed_tickets' => $dismissedTickets,
                'average_daily_tickets' => round($averageDailyTickets, 2),
                'collection_rate' => $collectionRate,
            ],
            'daily_breakdown' => $dailyBreakdown,
            'top_violators' => $topViolators,
            'top_violations' => $topViolations,
            'recent_tickets' => $weeklyTickets->take(10),
        ]);
    }
    
    // Export report as CSV
    public function exportReport(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfWeek()->toDateString());
        $endDate = $request->get('end_date', now()->endOfWeek()->toDateString());
        
        $tickets = Ticket::with(['violator', 'vehicle', 'enforcer', 'violations.violationType'])
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->get();
        
        $csvData = [];
        $csvData[] = ['Ticket #', 'Violator', 'License', 'Plate #', 'Violations', 'Total Fine', 'Location', 'Date', 'Status', 'Enforcer'];
        
        foreach ($tickets as $ticket) {
            $violationsList = $ticket->violations->map(function ($v) {
                return $v->violationType->violation_name . ' (₱' . number_format($v->fine_amount, 2) . ')';
            })->implode('; ');
            
            $csvData[] = [
                $ticket->ticket_number,
                $ticket->violator->firstname . ' ' . $ticket->violator->lastname,
                $ticket->violator->license,
                $ticket->vehicle->platenumber,
                $violationsList,
                number_format($ticket->violations->sum('fine_amount'), 2),
                $ticket->location,
                date('Y-m-d H:i', strtotime($ticket->violation_datetime)),
                strtoupper($ticket->status),
                $ticket->enforcer->firstname . ' ' . $ticket->enforcer->lastname,
            ];
        }
        
        // Convert to CSV string
        $csv = '';
        foreach ($csvData as $row) {
            $csv .= '"' . implode('","', array_map('addslashes', $row)) . '"' . "\n";
        }
        
        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="report_' . $startDate . '_to_' . $endDate . '.csv"');
    }
}