<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EnquirySubmissionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // The browser client form posts these fields as JSON. Validation happens before any DB write.
        $payload = $request->validate([
            'client_email' => ['required', 'email'],
            'client_name' => ['required', 'string', 'max:255'],
            'building_name' => ['nullable', 'string', 'max:255'],
            'building_size' => ['nullable', 'integer', 'min:0'],
            'message' => ['required', 'string'],
        ]);

        // Save first so the staff dashboard can show a local record even if n8n is slow or unavailable.
        $enquiry = Enquiry::query()->create([
            ...$payload,
            'public_id' => (string) Str::uuid(),
            'status' => 'pending',
        ]);

        // N8N_WEBHOOK_URL points to either the active production webhook or a one-shot test webhook.
        $webhookUrl = config('services.n8n.webhook_url');
        if (! $webhookUrl) {
            $enquiry->update([
                'status' => 'failed',
                'error_message' => 'N8N_WEBHOOK_URL is not configured.',
            ]);

            return response()->json([
                'error' => 'N8N_WEBHOOK_URL is not configured.',
                'enquiry_id' => $enquiry->public_id,
            ], 503);
        }

        try {
            // This is the handoff from Laravel to n8n. n8n will call Laravel back for client context.
            $response = Http::acceptJson()
                ->timeout(300)
                ->post($webhookUrl, $payload);
        } catch (ConnectionException $exception) {
            Log::error('print payload', ['payload' => $payload]);
            Log::error('Error connecting to n8n webhook', ['error' => $exception->getMessage()]);
            if (str_contains($exception->getMessage(), 'cURL error 28')) {
                // Treat webhook timeouts as "submitted" because n8n may still finish asynchronously.
                $enquiry->update([
                    'status' => 'submitted_to_n8n_timeout',
                    'n8n_response' => [
                        'message' => 'n8n did not send an HTTP response before Laravel timed out. The webhook may still have triggered.',
                    ],
                    'error_message' => $exception->getMessage(),
                ]);

                return response()->json([
                    'submitted' => true,
                    'pending_n8n_response' => true,
                    'enquiry_id' => $enquiry->public_id,
                    'message' => 'Submitted to n8n, but n8n did not return a webhook response. Set the n8n Webhook node to respond immediately to remove this warning.',
                ]);
            }

            $enquiry->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            return response()->json([
                'error' => 'Unable to connect to n8n webhook.',
                'detail' => $exception->getMessage(),
                'enquiry_id' => $enquiry->public_id,
            ], 502);
        }

        // n8n should respond with JSON, but this fallback keeps ngrok/html errors readable in the UI.
        $body = str_contains($response->header('content-type', ''), 'application/json')
            ? $response->json()
            : ['message' => $response->body()];

        // Store the exact n8n response. DashboardController formats this raw payload for staff display.
        $errorMessage = $response->successful()
            ? null
            : data_get($body, 'message', 'n8n webhook returned an unsuccessful response.');

        $enquiry->update([
            'status' => $response->successful() ? 'submitted_to_n8n' : 'failed',
            'n8n_response' => $body,
            'error_message' => $errorMessage,
        ]);

        return response()->json([
            'submitted' => $response->successful(),
            'enquiry_id' => $enquiry->public_id,
            'n8n_status' => $response->status(),
            'n8n_response' => $body,
            'error' => $errorMessage,
        ], $response->successful() ? 200 : 502);
    }
}
