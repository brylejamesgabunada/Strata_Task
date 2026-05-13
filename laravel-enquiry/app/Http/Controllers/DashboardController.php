<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function client(): View
    {
        return view('client-form');
    }

    public function staff(): View
    {
        return view('staff-dashboard', [
            'dashboard' => $this->staffPayload(),
        ]);
    }

    public function staffData(): JsonResponse
    {
        return response()->json($this->staffPayload());
    }

    /**
     * @return array<string, mixed>
     */
    private function staffPayload(): array
    {
        $enquiries = Enquiry::query()
            ->latest()
            ->limit(40)
            ->get()
            ->map(fn (Enquiry $enquiry) => $this->formatForStaff($enquiry));

        return [
            'enquiries' => $enquiries,
            'totalCount' => Enquiry::query()->count(),
            'processedCount' => $enquiries->where('workflow_status', 'PROCESSED')->count(),
            'escalatedCount' => $enquiries->where('workflow_status', 'ESCALATED')->count(),
            'reviewCount' => $enquiries->where('requires_human_review', true)->count(),
            'latestId' => $enquiries->first()['id'] ?? null,
            'refreshedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatForStaff(Enquiry $enquiry): array
    {
        $response = $enquiry->n8n_response ?? [];
        $workflowResult = array_is_list($response) ? ($response[0] ?? []) : $response;
        $analysis = data_get($workflowResult, 'analysis', []);
        $clientInfo = data_get($workflowResult, 'client_info', []);
        $recommendedActions = data_get($analysis, 'recommended_actions', []);
        $similarCases = data_get($clientInfo, 'similar_cases', []);
        $workflowStatus = data_get($workflowResult, 'status', strtoupper($enquiry->status));
        $urgency = data_get($analysis, 'urgency', 'Pending');
        $integrationProblem = $this->hasIntegrationProblem($workflowResult, $clientInfo);

        return [
            'id' => $enquiry->public_id,
            'created_at' => $enquiry->created_at?->toIso8601String(),
            'created_at_label' => $enquiry->created_at?->format('M j, Y g:i A'),
            'local_status' => $enquiry->status,
            'workflow_status' => $workflowStatus,
            'workflow_status_class' => str($workflowStatus)->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-')->toString(),
            'notification' => data_get($workflowResult, 'notification', 'Pending n8n response'),
            'client_name' => data_get($clientInfo, 'client_name') ?: $enquiry->client_name,
            'client_email' => data_get($clientInfo, 'client_email') ?: $enquiry->client_email,
            'building_name' => data_get($clientInfo, 'building_name') ?: $enquiry->building_name,
            'building_size' => data_get($clientInfo, 'building_size') ?: $enquiry->building_size,
            'message' => data_get($clientInfo, 'inquiry_message') ?: $enquiry->message,
            'client_type' => $integrationProblem ? 'Integration issue' : data_get($analysis, 'client_type', 'Unclassified'),
            'category' => $integrationProblem ? 'Workflow Integration' : data_get($analysis, 'category', 'Pending'),
            'urgency' => $urgency,
            'urgency_class' => str($urgency)->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-')->toString(),
            'confidence' => data_get($analysis, 'confidence'),
            'summary' => $integrationProblem ? 'The n8n workflow returned integration error content instead of a valid client-context response.' : data_get($analysis, 'summary', 'n8n response has not been received yet.'),
            'recommended_actions' => $integrationProblem ? [] : (is_array($recommendedActions) ? $recommendedActions : []),
            'suggested_response' => $integrationProblem ? '' : data_get($analysis, 'suggested_response', ''),
            'requires_human_review' => (bool) data_get($analysis, 'requires_human_review', false),
            'historical_reference' => is_array(data_get($analysis, 'historical_reference')) ? data_get($analysis, 'historical_reference') : [],
            'assigned_consultant' => data_get($clientInfo, 'client_context.assigned_consultant', 'Unassigned'),
            'similar_cases' => is_array($similarCases) ? $similarCases : [],
            'error_message' => $enquiry->error_message,
            'integration_problem' => $integrationProblem,
        ];
    }

    /**
     * @param array<string, mixed> $workflowResult
     * @param array<string, mixed> $clientInfo
     */
    private function hasIntegrationProblem(array $workflowResult, array $clientInfo): bool
    {
        $clientContextData = data_get($clientInfo, 'client_context.data');
        $summary = (string) data_get($workflowResult, 'analysis.summary', '');

        return (is_string($clientContextData) && str_contains($clientContextData, '<!DOCTYPE html'))
            || str_contains(strtolower($summary), 'ngrok');
    }
}
