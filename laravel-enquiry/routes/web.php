<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/client');
Route::get('/client', [DashboardController::class, 'client'])->name('client.form');
Route::get('/staff', [DashboardController::class, 'staff'])->name('staff.dashboard');
