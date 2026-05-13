<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Keep the browser entry point simple for the demo: "/" always opens the public client form.
Route::redirect('/', '/client');

// Client-facing screen. This renders only the intake form; the form submits to /api/enquiries/submit.
Route::get('/client', [DashboardController::class, 'client'])->name('client.form');

// Staff-facing screen. The initial page render includes the current queue, then JavaScript polls the API.
Route::get('/staff', [DashboardController::class, 'staff'])->name('staff.dashboard');
