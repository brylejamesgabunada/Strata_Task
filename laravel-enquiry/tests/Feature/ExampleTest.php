<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\StrataClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_client_form_returns_a_successful_response(): void
    {
        $response = $this->get('/client');

        $response->assertStatus(200);
    }

    public function test_the_staff_dashboard_returns_a_successful_response(): void
    {
        $response = $this->get('/staff');

        $response->assertStatus(200);
    }

    public function test_the_staff_dashboard_api_returns_a_successful_response(): void
    {
        $response = $this->getJson('/api/enquiries/staff');

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'enquiries',
                'totalCount',
                'processedCount',
                'escalatedCount',
                'reviewCount',
                'latestId',
                'refreshedAt',
            ]);
    }

    public function test_the_staff_dashboard_api_excludes_failed_transport_records(): void
    {
        Enquiry::query()->create([
            'public_id' => (string) Str::uuid(),
            'client_email' => 'client@example.com',
            'client_name' => 'Failed Client',
            'building_name' => 'Test Building',
            'building_size' => 8,
            'message' => 'This failed before n8n returned analysis.',
            'status' => 'failed',
            'n8n_response' => [
                'code' => 404,
                'message' => 'The requested webhook is not registered.',
            ],
            'error_message' => 'The requested webhook is not registered.',
        ]);

        $this->getJson('/api/enquiries/staff')
            ->assertOk()
            ->assertJsonPath('totalCount', 0)
            ->assertJsonPath('enquiries', []);
    }

    public function test_the_staff_dashboard_api_includes_processed_workflow_records(): void
    {
        Enquiry::query()->create([
            'public_id' => (string) Str::uuid(),
            'client_email' => 'client@example.com',
            'client_name' => 'Processed Client',
            'building_name' => 'Ready Building',
            'building_size' => 24,
            'message' => 'We need help changing strata managers.',
            'status' => 'submitted_to_n8n',
            'n8n_response' => [[
                'status' => 'PROCESSED',
                'notification' => 'General Question from Processed Client',
                'analysis' => [
                    'client_type' => 'New Client',
                    'category' => 'General Question',
                    'urgency' => 'Low',
                    'confidence' => 85,
                    'summary' => 'The client is asking about strata management services.',
                    'recommended_actions' => ['Follow up with an intro call.'],
                    'historical_reference' => [
                        'similar_case_id' => 'case-001',
                        'previous_resolution' => 'Intro call booked.',
                    ],
                    'suggested_response' => 'Thank you for your enquiry.',
                    'requires_human_review' => false,
                ],
                'client_info' => [
                    'client_email' => 'client@example.com',
                    'client_name' => 'Processed Client',
                    'building_name' => 'Ready Building',
                    'building_size' => 24,
                    'inquiry_message' => 'We need help changing strata managers.',
                    'client_context' => [
                        'assigned_consultant' => 'Business Development',
                    ],
                ],
            ]],
        ]);

        $this->getJson('/api/enquiries/staff')
            ->assertOk()
            ->assertJsonPath('totalCount', 1)
            ->assertJsonPath('enquiries.0.workflow_status', 'PROCESSED')
            ->assertJsonPath('enquiries.0.client_name', 'Processed Client');
    }

    public function test_the_client_context_api_returns_database_client_context(): void
    {
        $client = StrataClient::query()->create([
            'client_id' => 'CLT-TEST',
            'full_name' => 'Demo Existing Client',
            'email' => 'existing@example.com',
            'phone' => '+61 400 000 000',
            'status' => 'active',
            'account_manager' => 'Business Development',
            'portal_access' => true,
            'open_requests' => 2,
            'levy_status' => 'current',
        ]);

        $client->lots()->create([
            'lot_number' => '8',
            'building' => '55 Park Avenue',
            'plan_number' => 'SP98765',
            'role' => 'owner',
        ]);

        $this->getJson('/api/client/context?email=existing@example.com')
            ->assertOk()
            ->assertJsonPath('client_exists', true)
            ->assertJsonPath('client_id', 'CLT-TEST')
            ->assertJsonPath('assigned_consultant', 'Business Development')
            ->assertJsonPath('lots.0.lot_number', '8');
    }

    public function test_the_client_context_api_handles_missing_clients_as_new(): void
    {
        $this->getJson('/api/client/context?email=missing@example.com')
            ->assertOk()
            ->assertJsonPath('client_exists', false)
            ->assertJsonPath('client_type', 'New')
            ->assertJsonPath('assigned_consultant', 'Business Development');
    }

    public function test_laravel_rag_endpoint_is_not_exposed(): void
    {
        $this->postJson('/api/rag/search', [
            'query' => 'leaking ceiling',
        ])->assertNotFound();
    }
}
