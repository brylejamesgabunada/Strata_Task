<?php

namespace Database\Seeders;

use App\Models\PastEnquiry;
use App\Models\StrataClient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class MockWorkflowDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedClients();
        $this->seedPastEnquiries();
    }

    private function seedClients(): void
    {
        $path = base_path('../mock_client_database.json');
        $clients = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        foreach ($clients as $record) {
            $client = StrataClient::query()->updateOrCreate(
                ['client_id' => $record['client_id']],
                [
                    'full_name' => $record['full_name'],
                    'email' => $record['email'],
                    'phone' => $record['phone'] ?? null,
                    'status' => $record['status'] ?? 'active',
                    'account_manager' => $record['account_manager'] ?? null,
                    'since' => $record['since'] ?? null,
                    'portal_access' => (bool) ($record['portal_access'] ?? false),
                    'open_requests' => (int) ($record['open_requests'] ?? 0),
                    'levy_status' => $record['levy_status'] ?? 'unknown',
                    'note' => $record['note'] ?? null,
                ],
            );

            $client->lots()->delete();
            foreach ($record['lots'] ?? [] as $lot) {
                $client->lots()->create([
                    'lot_number' => $lot['lot_number'],
                    'building' => $lot['building'],
                    'plan_number' => $lot['plan_number'] ?? null,
                    'role' => $lot['role'] ?? null,
                ]);
            }
        }
    }

    private function seedPastEnquiries(): void
    {
        $path = base_path('../rag_seed_data.json');
        $cases = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        foreach ($cases as $case) {
            $metadata = $case['metadata'] ?? [];
            $content = $case['pageContent'] ?? '';

            PastEnquiry::query()->updateOrCreate(
                ['inquiry_id' => $case['id']],
                [
                    'category' => Arr::get($metadata, 'category', 'General Question'),
                    'subcategory' => Arr::get($metadata, 'subcategory'),
                    'urgency' => Arr::get($metadata, 'urgency', 'Medium'),
                    'client_status' => Arr::get($metadata, 'client_status'),
                    'summary' => $this->extractLabel($content, 'SUMMARY'),
                    'original_message' => $this->extractLabel($content, 'ORIGINAL MESSAGE'),
                    'recommended_action' => $this->extractLabel($content, 'RECOMMENDED ACTION'),
                    'suggested_response' => $this->extractLabel($content, 'SUGGESTED RESPONSE'),
                    'previous_resolution' => Arr::get($metadata, 'resolution'),
                    'page_content' => $content,
                    'metadata' => $metadata,
                ],
            );
        }
    }

    private function extractLabel(string $content, string $label): ?string
    {
        $pattern = sprintf('/%s:\s*([\s\S]*?)(?=\n[A-Z][A-Z\s()\/-]*:|$)/i', preg_quote($label, '/'));

        if (! preg_match($pattern, $content, $matches)) {
            return null;
        }

        return trim(preg_replace('/\s+/', ' ', $matches[1]));
    }
}
