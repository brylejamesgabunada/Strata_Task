# Strata Enquiry Desk: Local Setup Guide

This README explains how to set up and run the Strata Enquiry Desk demo on your own machine.

The project combines:

- A Laravel web application for the client form, staff dashboard, database, and API endpoints.
- An n8n Cloud workflow for automation and AI classification.
- SQLite for local database storage.
- Mock client data and Google Sheets past inquiry knowledge for the demo.
- Optional Google Sheets knowledge data for the n8n workflow.

The main demo flow is:

```text
Client submits inquiry
    -> Laravel stores inquiry
    -> Laravel sends inquiry to n8n
    -> n8n calls Laravel for client context
    -> n8n uses the Google Sheets AI tool for past inquiry knowledge
    -> n8n classifies the inquiry with AI
    -> n8n returns recommended staff actions and suggested response
    -> Laravel stores the result
    -> Staff dashboard displays the inquiry
```

## 1. Project Structure

Important files and folders:

```text
Personal_Task/
├── laravel-enquiry/
│   ├── app/
│   ├── database/
│   ├── resources/views/
│   ├── routes/
│   ├── .env.example
│   └── README-STRATA.md
├── Enquiry Classifier & Response Generator - Webhook UI.json
├── data/strata_knowledge_base_google_sheet.csv
└── N8N_LARAVEL_CLIENT_INQUIRY_DEMO_DOCUMENTATION.md
```

Use `laravel-enquiry/` as the main application.

## 2. Prerequisites

Install these before starting:

- PHP 8.2 or newer
- Composer
- SQLite
- Node.js and npm, optional for Laravel asset builds
- ngrok, needed if n8n Cloud must call your local Laravel app
- An n8n Cloud account
- OpenAI credentials configured in n8n

Check versions:

```bash
php -v
composer -V
sqlite3 --version
node -v
npm -v
ngrok version
```

## 3. Clone or Open the Project

Open a terminal and go to the project folder:

```bash
cd "/Users/fdc.brylejames-nc-web/Documents/Personal_Task"
```

Then enter the Laravel app:

```bash
cd laravel-enquiry
```

## 4. Install Laravel Dependencies

Run:

```bash
composer install
```

This installs Laravel and PHP dependencies into `vendor/`.

## 5. Configure Environment Variables

Copy the example environment file:

```bash
cp .env.example .env
```

Generate the Laravel app key:

```bash
php artisan key:generate
```

Open `.env` and confirm these values:

```text
APP_NAME="Strata Enquiry Desk"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:49152

DB_CONNECTION=sqlite

N8N_WEBHOOK_URL=https://gabunadame.app.n8n.cloud/webhook/strata-enquiry
```

For n8n test mode, temporarily use:

```text
N8N_WEBHOOK_URL=https://gabunadame.app.n8n.cloud/webhook-test/strata-enquiry
```

Important:

- Use `/webhook/strata-enquiry` when the n8n workflow is active.
- Use `/webhook-test/strata-enquiry` only after clicking **Listen for test event** in n8n.
- n8n test webhooks are usually valid for one test call at a time.

## 6. Set Up the SQLite Database

Create the SQLite database file:

```bash
touch database/database.sqlite
```

Run migrations and seed the mock data:

```bash
php artisan migrate:fresh --seed
```

This creates and seeds:

- mock strata clients
- client lots
- enquiry submission table

Seed data is defined directly in `database/seeders/MockWorkflowDataSeeder.php`, then inserted into the SQLite database.

## 7. Frontend Assets

The current demo screens use Blade templates with inline CSS and JavaScript, so you can run the demo without building frontend assets.

If you want to install and build Laravel frontend assets anyway:

```bash
npm install
npm run build
```

For this demo, these commands are optional.

## 8. Run the Laravel App Locally

Use this command:

```bash
PHP_CLI_SERVER_WORKERS=4 php -d max_execution_time=0 artisan serve --host=127.0.0.1 --port=49152 --no-reload
```

Why this command matters:

- The project uses port `49152` to avoid common default ports.
- `PHP_CLI_SERVER_WORKERS=4` lets Laravel handle multiple requests at the same time.
- This is important because Laravel sends a request to n8n, and n8n calls Laravel back while the original request is still waiting.
- `max_execution_time=0` prevents the local dev server from stopping during longer workflow tests.

Open these URLs:

```text
Client form:
http://127.0.0.1:49152/client

Staff dashboard:
http://127.0.0.1:49152/staff

Health check:
http://127.0.0.1:49152/api/health
```

## 9. Expose Laravel to n8n Cloud with ngrok

n8n Cloud cannot call `localhost` on your laptop. Use ngrok so n8n can reach your Laravel API.

In a second terminal, run:

```bash
ngrok http http://127.0.0.1:49152
```

ngrok will show a public HTTPS URL, for example:

```text
https://8137-103-43-214-18.ngrok-free.app
```

Keep ngrok running while testing.

Verify the tunnel:

```bash
curl -H "ngrok-skip-browser-warning: true" \
  https://YOUR-NGROK-URL/api/health
```

Expected response:

```json
{
  "ok": true,
  "workflow": "Enquiry Classifier & Response Generator",
  "role": "laravel-context-and-dashboard-backend",
  "n8n_webhook_configured": true
}
```

## 10. Import and Configure the n8n Workflow

Import this workflow into n8n:

```text
Enquiry Classifier & Response Generator - Webhook UI.json
```

After import, configure these important nodes.

### Strata Inquiry Webhook

This receives the Laravel inquiry submission.

Production URL:

```text
https://gabunadame.app.n8n.cloud/webhook/strata-enquiry
```

Test URL:

```text
https://gabunadame.app.n8n.cloud/webhook-test/strata-enquiry
```

### Lookup Client Context1

Update the URL to your current ngrok URL:

```text
https://YOUR-NGROK-URL/api/client/context?email={{ $json.client_email }}
```

Add this header:

```text
ngrok-skip-browser-warning: true
```

### OpenAI Model1

Confirm OpenAI credentials are configured in n8n.

Current model setting:

```text
gpt-4o-mini
```

### Knowledge Base Sheet Tool

The workflow includes a Google Sheets tool connected directly to the AI agent. This is the primary RAG-style knowledge source for past inquiry examples and recommended actions.

Upload this CSV to Google Sheets:

```text
data/strata_knowledge_base_google_sheet.csv
```

Name the sheet tab:

```text
KnowledgeBase
```

Then select the Google document and sheet in the n8n node:

```text
Knowledge Base Sheet Tool
```

For the intended demo, configure this node. Laravel no longer exposes a RAG endpoint; RAG-style knowledge retrieval is handled by this n8n AI Agent tool.

## 11. Activate or Test the n8n Workflow

For production-style demo:

1. Activate the workflow in n8n.
2. Set Laravel `.env` to:

```text
N8N_WEBHOOK_URL=https://gabunadame.app.n8n.cloud/webhook/strata-enquiry
```

For test mode:

1. Open the workflow canvas.
2. Click **Listen for test event**.
3. Set Laravel `.env` to:

```text
N8N_WEBHOOK_URL=https://gabunadame.app.n8n.cloud/webhook-test/strata-enquiry
```

4. Submit one inquiry from the Laravel form.

## 12. Test the Full Demo

### Step 1: Start Laravel

```bash
cd "/Users/fdc.brylejames-nc-web/Documents/Personal_Task/laravel-enquiry"
PHP_CLI_SERVER_WORKERS=4 php -d max_execution_time=0 artisan serve --host=127.0.0.1 --port=49152 --no-reload
```

### Step 2: Start ngrok

```bash
ngrok http http://127.0.0.1:49152
```

### Step 3: Confirm Laravel through ngrok

```bash
curl -H "ngrok-skip-browser-warning: true" \
  https://YOUR-NGROK-URL/api/client/context?email=test@example.com
```

Expected: JSON response.

### Step 4: Configure n8n URLs

Update the two HTTP Request nodes in n8n to use the current ngrok URL.

### Step 5: Submit a Client Inquiry

Open:

```text
http://127.0.0.1:49152/client
```

Example inquiry:

```text
There is water leaking from the ceiling near the lift and it is getting worse. Can someone attend urgently?
```

After submitting, the form should show a success confirmation with a check mark.

### Step 6: Check n8n Execution

In n8n, open the execution and confirm:

- webhook received the payload
- client lookup ran
- Google Sheets knowledge tool was available or used
- AI classification ran
- response node returned data to Laravel

### Step 7: Review in Staff Dashboard

Open:

```text
http://127.0.0.1:49152/staff
```

The staff dashboard should show:

- inquiry queue
- classification
- urgency
- confidence
- summary
- recommended staff actions
- suggested response
- historical reference

The staff dashboard polls every 5 seconds, so new inquiries appear without a full page refresh.

## 13. Useful Local API Tests

Health check:

```bash
curl http://127.0.0.1:49152/api/health
```

Client lookup:

```bash
curl "http://127.0.0.1:49152/api/client/context?email=test@example.com"
```

Staff dashboard data:

```bash
curl http://127.0.0.1:49152/api/enquiries/staff
```

Submit inquiry directly:

```bash
curl -X POST http://127.0.0.1:49152/api/enquiries/submit \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_email": "demo@example.com",
    "client_name": "Demo Client",
    "building_name": "55 Park Avenue",
    "building_size": 123,
    "message": "There is water leaking from the ceiling near the lift."
  }'
```

## 14. Run Tests

From `laravel-enquiry/`:

```bash
php artisan test
```

The current tests verify:

- client form route loads
- staff dashboard route loads
- staff dashboard polling API returns the expected JSON structure

## 15. Common Issues and Fixes

### n8n says webhook is not registered

Cause:

- The production workflow is not active, or
- The test webhook is not listening.

Fix:

- Activate the workflow for `/webhook/strata-enquiry`.
- Or click **Listen for test event** and use `/webhook-test/strata-enquiry`.

### Browser shows ngrok gateway error

Cause:

- Laravel is not running.
- ngrok points to the wrong port.
- Laravel was started without enough workers.

Fix:

```bash
PHP_CLI_SERVER_WORKERS=4 php -d max_execution_time=0 artisan serve --host=127.0.0.1 --port=49152 --no-reload
```

### n8n receives HTML instead of JSON

Cause:

- ngrok browser warning page
- wrong ngrok URL
- Laravel not running

Fix:

- Add header `ngrok-skip-browser-warning: true`.
- Update n8n HTTP Request node URLs.
- Verify `/api/health` through ngrok.

### Client lookup shows unavailable

Run:

```bash
curl -H "ngrok-skip-browser-warning: true" \
  "https://YOUR-NGROK-URL/api/client/context?email=test@example.com"
```

If this returns HTML or an error, n8n cannot use the lookup correctly.

### Staff dashboard does not update

Check:

```bash
curl http://127.0.0.1:49152/api/enquiries/staff
```

If this works, wait up to 5 seconds or refresh the staff dashboard.

### ngrok URL changed

Free ngrok URLs can change when restarted.

Fix:

1. Copy the new ngrok HTTPS URL.
2. Update `Lookup Client Context1`.
3. Test `/api/health` through ngrok.

## 16. Demo Checklist

Before presenting:

- Laravel server is running on `127.0.0.1:49152`.
- ngrok is running and points to `127.0.0.1:49152`.
- n8n HTTP Request nodes use the current ngrok URL.
- n8n workflow is active, or test listener is enabled.
- `.env` has the correct `N8N_WEBHOOK_URL`.
- `/api/health` works locally.
- `/api/health` works through ngrok.
- `/client` loads.
- `/staff` loads.
- A test inquiry appears in the staff dashboard.

## 17. Additional Documentation

Full detailed system documentation is available here:

```text
N8N_LARAVEL_CLIENT_INQUIRY_DEMO_DOCUMENTATION.md
```

Laravel-specific demo notes:

```text
laravel-enquiry/README-STRATA.md
```
