<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StrataClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientContextController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $email = strtolower(trim((string) $request->query('email')));
        $client = StrataClient::query()
            ->with('lots')
            ->whereRaw('lower(email) = ?', [$email])
            ->first();

        if (! $client || $client->status !== 'active') {
            return response()->json([
                'client_exists' => false,
                'client_id' => $client?->client_id,
                'client_type' => $client ? 'Inactive Former' : 'New',
                'assigned_consultant' => $client?->account_manager ?: 'Business Development',
                'past_inquiries_count' => 0,
                'active_projects' => [],
                'open_requests' => 0,
                'levy_status' => $client?->levy_status ?: 'n/a',
                'portal_access' => false,
                'lots' => [],
                'profile' => $client,
            ]);
        }

        return response()->json([
            'client_exists' => true,
            'client_id' => $client->client_id,
            'client_type' => 'Existing',
            'assigned_consultant' => $client->account_manager ?: 'Unassigned',
            'past_inquiries_count' => max((int) $client->open_requests, 0),
            'active_projects' => $client->open_requests > 0 ? ['Open enquiry follow-up'] : [],
            'open_requests' => (int) $client->open_requests,
            'levy_status' => $client->levy_status,
            'portal_access' => (bool) $client->portal_access,
            'lots' => $client->lots->map(fn ($lot) => [
                'lot_number' => $lot->lot_number,
                'building' => $lot->building,
                'plan_number' => $lot->plan_number,
                'role' => $lot->role,
            ])->values(),
            'profile' => [
                'client_id' => $client->client_id,
                'full_name' => $client->full_name,
                'email' => $client->email,
                'phone' => $client->phone,
                'status' => $client->status,
                'account_manager' => $client->account_manager,
                'portal_access' => (bool) $client->portal_access,
                'open_requests' => (int) $client->open_requests,
                'levy_status' => $client->levy_status,
            ],
        ]);
    }
}
