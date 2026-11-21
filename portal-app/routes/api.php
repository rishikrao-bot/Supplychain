<?php

use App\Http\Controllers\Api\DataController;
use Illuminate\Support\Facades\Route;

// Read-only data API for Power BI, Power Automate and agents. Keys: Microsoft 365 page (Compass admins).
Route::prefix('v1')->middleware(['api.key', 'throttle:120,1'])->group(function () {
    Route::get('/', [DataController::class, 'index']);
    foreach (DataController::TABLES as $table) {
        Route::get('/'.$table, [DataController::class, $table]);
    }
});
