<?php

use App\Http\Controllers\Api\ClientContextController;
use App\Http\Controllers\Api\EnquirySubmissionController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Lightweight smoke-test endpoint for the browser, terminal, ngrok, and n8n HTTP Request nodes.
Route::get('/health', function () {
    return response()->json([
        'ok' => true,
        'workflow' => 'Enquiry Classifier & Response Generator',
        'role' => 'laravel-context-and-dashboard-backend',
        'n8n_webhook_configured' => filled(config('services.n8n.webhook_url')),
    ]);
});

// n8n calls this during workflow execution to decide if the submitter is an existing client.
Route::get('/client/context', ClientContextController::class);

// Client form submits here. This endpoint saves locally, sends to n8n, then stores the n8n response.
Route::post('/enquiries/submit', EnquirySubmissionController::class);

// Staff dashboard polls this endpoint every few seconds so new inquiries appear without a page refresh.
Route::get('/enquiries/staff', [DashboardController::class, 'staffData']);
