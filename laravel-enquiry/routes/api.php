<?php

use App\Http\Controllers\Api\ClientContextController;
use App\Http\Controllers\Api\EnquirySubmissionController;
use App\Http\Controllers\Api\RagSearchController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'ok' => true,
        'workflow' => 'Enquiry Classifier & Response Generator',
        'role' => 'laravel-context-and-rag-backend',
        'n8n_webhook_configured' => filled(config('services.n8n.webhook_url')),
    ]);
});

Route::get('/client/context', ClientContextController::class);
Route::post('/rag/search', RagSearchController::class);
Route::post('/enquiries/submit', EnquirySubmissionController::class);
Route::get('/enquiries/staff', [DashboardController::class, 'staffData']);
