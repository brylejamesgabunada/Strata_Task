<?php

namespace Database\Seeders;

use App\Models\StrataClient;
use Illuminate\Database\Seeder;

class MockWorkflowDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedClients();
    }

    private function seedClients(): void
    {
        foreach ($this->clients() as $record) {
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

    /**
     * Demo client context used by /api/client/context for n8n enrichment.
     *
     * These records are stored in the real SQLite database during seeding.
     * Laravel's default users table is reserved for app authentication, so
     * client lookup data belongs in strata_clients and client_lots instead.
     *
     * @return array<int, array<string, mixed>>
     */
    private function clients(): array
    {
        return [
            [
                'client_id' => 'CLT-0001',
                'full_name' => 'Maria Santos',
                'email' => 'maria.santos@email.com',
                'phone' => '+61 412 001 001',
                'status' => 'active',
                'lots' => [
                    [
                        'lot_number' => '14',
                        'building' => '22 Harbour Street, Pyrmont NSW 2009',
                        'plan_number' => 'SP12345',
                        'role' => 'owner',
                    ],
                ],
                'account_manager' => 'James Reyes',
                'since' => '2021-03-15',
                'portal_access' => true,
                'open_requests' => 1,
                'levy_status' => 'current',
            ],
            [
                'client_id' => 'CLT-0002',
                'full_name' => 'David Nguyen',
                'email' => 'd.nguyen@outlook.com',
                'phone' => '+61 412 002 002',
                'status' => 'active',
                'lots' => [
                    [
                        'lot_number' => '7',
                        'building' => '10 Marina Cove, Pyrmont NSW 2009',
                        'plan_number' => 'SP54321',
                        'role' => 'owner',
                    ],
                    [
                        'lot_number' => '8',
                        'building' => '10 Marina Cove, Pyrmont NSW 2009',
                        'plan_number' => 'SP54321',
                        'role' => 'investor',
                    ],
                ],
                'account_manager' => 'Sarah Kim',
                'since' => '2019-07-22',
                'portal_access' => true,
                'open_requests' => 0,
                'levy_status' => 'overdue',
            ],
            [
                'client_id' => 'CLT-0003',
                'full_name' => 'Elena Reyes',
                'email' => 'elena.reyes@gmail.com',
                'phone' => '+61 412 003 003',
                'status' => 'active',
                'lots' => [
                    [
                        'lot_number' => '22',
                        'building' => '55 Park Avenue, Chippendale NSW 2008',
                        'plan_number' => 'SP98765',
                        'role' => 'owner',
                    ],
                ],
                'account_manager' => 'James Reyes',
                'since' => '2024-01-10',
                'portal_access' => false,
                'open_requests' => 0,
                'levy_status' => 'current',
            ],
            [
                'client_id' => 'CLT-0004',
                'full_name' => 'Robert Chen',
                'email' => 'rchen@business.com.au',
                'phone' => '+61 412 004 004',
                'status' => 'active',
                'lots' => [
                    [
                        'lot_number' => '3',
                        'building' => '88 Bridge Road, Ultimo NSW 2007',
                        'plan_number' => 'SP11223',
                        'role' => 'committee_chair',
                    ],
                ],
                'account_manager' => 'Sarah Kim',
                'since' => '2018-11-05',
                'portal_access' => true,
                'open_requests' => 3,
                'levy_status' => 'current',
            ],
            [
                'client_id' => 'CLT-0005',
                'full_name' => 'Sophie Tran',
                'email' => 'sophie.tran@yahoo.com',
                'phone' => '+61 412 005 005',
                'status' => 'inactive',
                'lots' => [],
                'account_manager' => null,
                'since' => '2020-05-18',
                'portal_access' => false,
                'open_requests' => 0,
                'levy_status' => 'n/a',
                'note' => 'Lot sold in 2023; conveyancing completed and account archived',
            ],
            [
                'client_id' => 'CLT-0006',
                'full_name' => 'Bryle James Gabunada',
                'email' => 'fdc.brylejames@gmail.com',
                'phone' => '+61 412 006 006',
                'status' => 'active',
                'lots' => [
                    [
                        'lot_number' => '8',
                        'building' => '55 Park Avenue, Chippendale NSW 2008',
                        'plan_number' => 'SP98765',
                        'role' => 'owner',
                    ],
                ],
                'account_manager' => 'Business Development',
                'since' => '2026-05-01',
                'portal_access' => true,
                'open_requests' => 1,
                'levy_status' => 'current',
                'note' => 'Demo client used for local Laravel and n8n workflow testing',
            ],
        ];
    }
}
