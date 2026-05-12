<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use App\Models\PastEnquiry;
use App\Models\StrataClient;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'clientsCount' => StrataClient::query()->count(),
            'casesCount' => PastEnquiry::query()->count(),
            'enquiries' => Enquiry::query()->latest()->limit(12)->get(),
            'n8nWebhookConfigured' => filled(config('services.n8n.webhook_url')),
        ]);
    }
}
