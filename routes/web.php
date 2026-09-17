<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\TicketController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return response()->json([
        'name'    => 'TEMU Traffic System',
        'status'  => 'ok',
        'time'    => now()->toIso8601String(),
    ]);
});

// ✅ Public ticket view (opened by scanning the QR code with a phone camera)
Route::get('/public-ticket/{ticketNumber}', [TicketController::class, 'publicShowHtml']);