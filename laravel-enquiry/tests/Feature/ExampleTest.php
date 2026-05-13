<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
