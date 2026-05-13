# Strata Enquiry Desk Demo Guide

## Project Overview

Strata Enquiry Desk is a Laravel demo application that connects a custom client enquiry form to an n8n Cloud workflow. The goal is to show an end-to-end intake and staff review process:

1. A client submits an enquiry from a web form.
2. Laravel stores the enquiry in SQLite.
3. Laravel forwards the enquiry to n8n.
4. n8n enriches the enquiry with client context and Google Sheets past inquiry knowledge.
5. n8n uses an AI model to classify the enquiry and generate staff recommendations.
6. Laravel stores the n8n response and displays it in a staff dashboard.
7. The staff dashboard polls for new enquiries every 5 seconds.

The AI analysis and RAG-style knowledge retrieval happen in n8n. Laravel handles the web UI, local database, mock client data, submission storage, and dashboard display.

## Purpose

This system demonstrates how a strata consultancy can triage incoming enquiries before a staff member reviews them. It classifies the enquiry, estimates urgency, suggests next staff actions, drafts a response, and references similar historical cases.

Minimum task coverage:

- Accepts a text-based client enquiry.
- Uses an AI workflow in n8n for analysis.
- Classifies the enquiry type.
- Generates recommended staff actions and a suggested response.
- Presents results in a client form and staff dashboard.

## Main Screens

- Client form: `/client`
- Staff dashboard: `/staff`
- Staff dashboard JSON polling endpoint: `/api/enquiries/staff`

## Laravel Setup

Install PHP dependencies:

```bash
cd "/Users/fdc.brylejames-nc-web/Documents/Personal Task/laravel-enquiry"
composer install
```

Create the SQLite database if it does not exist:

```bash
touch database/database.sqlite
```

Copy and configure the environment file:

```bash
cp .env.example .env
php artisan key:generate
```

Run migrations and seed mock data:

```bash
php artisan migrate:fresh --seed
```

## Required Environment Variables

Important `.env` values:

```text
APP_NAME="Strata Enquiry Desk"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:49152
DB_CONNECTION=sqlite
N8N_WEBHOOK_URL=https://gabunadame.app.n8n.cloud/webhook/strata-enquiry
```

Use the production webhook only when the n8n workflow is active. While testing in the n8n editor, use:

```text
N8N_WEBHOOK_URL=https://gabunadame.app.n8n.cloud/webhook-test/strata-enquiry
```

The test webhook only works after clicking **Listen for test event** in n8n, and it is usually valid for one call.

## Run Locally

Use a non-default port and multiple PHP server workers so n8n can call the Laravel callback endpoints while the client submission request is still waiting:

```bash
PHP_CLI_SERVER_WORKERS=4 php -d max_execution_time=0 artisan serve --host=127.0.0.1 --port=49152 --no-reload
```

Open:

```text
http://127.0.0.1:49152/client
```

## Expose Laravel to n8n Cloud with ngrok

n8n Cloud cannot call `localhost`, so expose the Laravel server:

```bash
ngrok http http://127.0.0.1:49152
```

Use the ngrok HTTPS URL in the n8n HTTP Request nodes. Current demo example:

```text
https://8137-103-43-214-18.ngrok-free.app
```

Required n8n callback endpoints:

```text
GET https://8137-103-43-214-18.ngrok-free.app/api/client/context?email={{ $json.client_email }}
```

Add this header to the n8n HTTP Request node:

```text
ngrok-skip-browser-warning: true
```

Free ngrok URLs can change after restart. If ngrok changes, update the n8n workflow JSON or the HTTP Request nodes in n8n.

## Database and Mock Data

Laravel uses SQLite at:

```text
database/database.sqlite
```

Mock client data is defined directly in `Database\Seeders\MockWorkflowDataSeeder` and inserted into the SQLite database.

Tables used in the demo:

- `strata_clients`: mock existing clients.
- `client_lots`: lot/building records linked to clients.
- `enquiries`: client submissions and stored n8n responses.

Reset and seed:

```bash
php artisan migrate:fresh --seed
```

## Client Inquiry Submission Flow

1. User opens `/client`.
2. User enters email, name, building details, and message.
3. Browser submits JSON to `POST /api/enquiries/submit`.
4. Laravel validates the request and stores a local `enquiries` row.
5. Laravel posts the same payload to `N8N_WEBHOOK_URL`.
6. Laravel stores the response from n8n in `enquiries.n8n_response`.
7. The client form shows a success confirmation with a check mark and reference ID.

The client form no longer shows demo counters or the n8n status pill; it only focuses on enquiry intake.

## Staff Dashboard Flow

1. Staff opens `/staff`.
2. Laravel renders the initial queue and detail state.
3. JavaScript polls `GET /api/enquiries/staff` every 5 seconds.
4. New enquiries appear in the left queue without a full page refresh.
5. Selecting an enquiry shows:
   - status
   - urgency
   - category
   - confidence
   - client message
   - summary
   - recommended staff actions
   - suggested response
   - historical reference
   - integration notes when applicable

AJAX polling is used because it is simple and reliable for this demo. It avoids adding WebSocket infrastructure.

## Laravel API Endpoints Used by n8n

### Client context

```http
GET /api/client/context?email=maria.santos@email.com
```

Returns existing client context when the email matches the mock database. If no client is found, Laravel returns a valid JSON "New Client" context. This is intentional and prevents "not found" cases from becoming workflow failures.

### Submit enquiry

```http
POST /api/enquiries/submit
Content-Type: application/json

{
  "client_email": "maria.santos@email.com",
  "client_name": "Maria Santos",
  "building_name": "22 Harbour Street",
  "building_size": 72,
  "message": "Noise is still happening every night after 10pm."
}
```

### Staff polling data

```http
GET /api/enquiries/staff
```

Returns the latest 40 formatted enquiries plus dashboard counters.

## n8n Workflow Design

Workflow file:

```text
../Enquiry Classifier & Response Generator - Webhook UI.json
```

Recommended flow:

1. **Strata Inquiry Webhook**
   - Receives the client form payload from Laravel.
   - Path: `strata-enquiry`.

2. **Normalize Client Input**
   - Normalizes `client_email`, `client_name`, `building_name`, `building_size`, and `message`.
   - Captures basic validation errors.

3. **Lookup Client Context**
   - Calls Laravel `/api/client/context`.
   - Existing clients return account/project context.
   - Missing clients return a safe `New Client` context.

4. **Knowledge Base Sheet Tool**
   - The AI agent queries Google Sheets for past inquiry knowledge.
   - This is the RAG-style retrieval path for the demo.

5. **Prepare AI Context**
   - Combines normalized input, client context, validation state, and sheet knowledge.
   - Handles negative cases like unavailable sheet data, empty fallback results, or unavailable lookup responses.

7. **Classify & Analyze Inquiry**
   - Uses the AI model and structured output parser.
   - The prompt instructs the AI to analyze only the submitted client message and use lookup/sheet/fallback data only as supporting context.

8. **Check Urgency**
   - Routes High urgency or human-review cases to escalation.

9. **Escalate to Human Review / Standard Response**
   - Builds the final response object for Laravel.

10. **Respond to Webhook**
   - Returns the final classification and recommendation payload to Laravel.

## AI Classification and Staff Recommendations

The AI returns:

- `client_type`: New Client or Existing Client.
- `category`: New Client, Support Request, Complaint, Maintenance Request, Financial Enquiry, or General Question.
- `urgency`: High, Medium, or Low.
- `confidence`: classification confidence from 0 to 100.
- `summary`: one-sentence summary of the enquiry.
- `recommended_actions`: practical staff next steps.
- `historical_reference`: similar case and previous resolution where useful.
- `suggested_response`: client-facing response draft.
- `requires_human_review`: true for high urgency, complaints, low confidence, or ambiguity.

## Demo Script

1. **Open n8n workflow**
   - Show the webhook trigger, client lookup, Google Sheets knowledge tool, optional fallback lookup, AI analysis, urgency check, and response nodes.

2. **Explain Laravel role**
   - Laravel is the UI and database layer.
   - n8n is the automation and AI analysis layer.

3. **Start Laravel**
   - Run the multi-worker serve command on port `49152`.

4. **Start ngrok**
   - Show the public URL and verify `/api/health`.

5. **Submit a client enquiry**
   - Open `/client`.
   - Enter a realistic message, for example: "There is water leaking from the ceiling near the lift and it is getting worse."
   - Submit and point out the check mark success confirmation.

6. **Show n8n execution**
   - Show the workflow receiving the payload.
   - Show the client context and Google Sheets knowledge retrieval.
   - Show the AI result.

7. **Show staff dashboard**
   - Open `/staff`.
   - The new enquiry appears automatically through polling.
   - Review classification, urgency, suggested response, and staff actions.

8. **Discuss negative cases**
   - Unknown email becomes New Client.
   - Empty Google Sheet/fallback results are allowed.
   - n8n test webhook requires "Listen for test event".
   - ngrok restart requires updating callback URLs.

## Troubleshooting

### Browser shows an ngrok gateway or invalid JSON error

Laravel may not be running, or the submit request may have timed out. Restart Laravel with:

```bash
PHP_CLI_SERVER_WORKERS=4 php -d max_execution_time=0 artisan serve --host=127.0.0.1 --port=49152 --no-reload
```

### n8n returns `webhook not registered`

Production webhook is not active, or test webhook is not listening.

- For production: activate the n8n workflow and use `/webhook/strata-enquiry`.
- For testing: click **Listen for test event** and use `/webhook-test/strata-enquiry`.

### n8n client lookup says unavailable

Check:

```bash
curl -H "ngrok-skip-browser-warning: true" https://YOUR-NGROK-URL/api/client/context?email=test@example.com
```

It must return JSON, not an HTML page.

### Staff dashboard does not update

Check:

```bash
curl http://127.0.0.1:49152/api/enquiries/staff
```

The dashboard polls this endpoint every 5 seconds.

### ngrok URL changed

Update the n8n HTTP Request node:

- Client context node

### Google Sheets tool is not configured

The RAG-style path is the Google Sheets AI tool. To enable sheet knowledge, upload:

```text
../data/strata_knowledge_base_google_sheet.csv
```

Then select the Google document and `KnowledgeBase` sheet in the n8n `Knowledge Base Sheet Tool` node.

## Assumptions

- This is a demo, not a production deployment.
- SQLite is acceptable for local demonstration.
- AJAX polling is sufficient for near-realtime staff dashboard updates.
- AI analysis remains in n8n; Laravel does not call OpenAI directly.
- Mock data is acceptable for client existence checks and historical cases.
- The n8n workflow must be active for production webhooks.
- Free ngrok tunnels may change URL when restarted.
