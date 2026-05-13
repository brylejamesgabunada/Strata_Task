# n8n + Laravel Client Inquiry Demo Documentation

## 1. Project Overview

This project is a working demo of a client inquiry intake and staff review system. It uses a Laravel web application for the user interface, local database, and backend API endpoints, while n8n Cloud acts as the automation and AI orchestration layer.

The demo is designed for a strata consultancy use case. A client submits an inquiry through a web form, the system stores the inquiry, sends the inquiry to n8n, enriches it with mock client and historical inquiry context, classifies the inquiry with AI, generates recommended staff actions, drafts a suggested response, and displays the result in a staff dashboard.

### Main Goal

The main goal is to demonstrate a complete AI-assisted inquiry triage workflow:

- Accept a client inquiry from a web UI.
- Determine whether the client is new or existing.
- Retrieve relevant historical or mock knowledge context through the n8n AI agent's Google Sheets tool.
- Classify the inquiry type.
- Estimate urgency and whether human review is required.
- Generate a suggested response and recommended staff actions.
- Present the result in a usable staff dashboard.

### Problem Being Solved

Client inquiries often arrive with different levels of detail, urgency, and context. Staff members need to quickly understand:

- Who submitted the inquiry.
- Whether the person is an existing client.
- What kind of issue or request it is.
- Whether it needs urgent attention.
- What similar cases were handled before.
- What response or next action should be taken.

This demo reduces manual triage work by letting n8n and an AI model prepare a structured staff-ready summary.

### How Laravel and n8n Work Together

Laravel handles the application layer:

- Client form UI.
- Staff dashboard UI.
- SQLite database.
- Inquiry storage.
- Client lookup endpoint.
- Google Sheets knowledge retrieval through the n8n AI Agent.
- Staff dashboard polling endpoint.
- Submission forwarding to n8n.

n8n handles the workflow and AI automation layer:

- Receives the inquiry payload from Laravel.
- Calls Laravel back through ngrok for client context.
- Uses the Google Sheets knowledge base tool inside the AI agent for RAG-style reference data.
- Can call Laravel back through ngrok for similar past inquiries as a fallback/mock API.
- Runs AI classification and response generation.
- Routes urgent or human-review cases.
- Returns a structured JSON result to Laravel.

### Main Users

**Client/User submitting an inquiry**

The client uses the Laravel client form at `/client` to submit their inquiry. The form collects email, name, building details, and the inquiry message.

**Staff member reviewing inquiries**

The staff member uses the Laravel staff dashboard at `/staff` to review inquiry classification, urgency, recommended actions, suggested response, and historical reference.

**n8n workflow acting as automation layer**

n8n receives the inquiry, enriches it with backend context, calls the AI model, formats the result, and sends the result back to Laravel.

---

## 2. System Architecture

### High-Level Architecture

```text
Client/User
   |
   v
Laravel Client Form (/client)
   |
   v
Laravel Backend API (/api/enquiries/submit)
   |
   |-- saves initial inquiry
   v
SQLite Database (enquiries table)
   |
   v
n8n Webhook Trigger
   |
   |-- calls Laravel client lookup through ngrok
   |-- uses Google Sheets knowledge base tool for RAG-style retrieval
   |-- calls Google Sheets knowledge tool inside n8n AI Agent
   v
AI Classification / Recommendation Node
   |
   v
n8n Response Node
   |
   v
Laravel stores n8n response in enquiries.n8n_response
   |
   v
Staff Dashboard (/staff)
   |
   v
AJAX polling (/api/enquiries/staff)
```

### Major Components

#### Laravel Application

Path:

```text
laravel-enquiry/
```

Laravel is the main demo application. It owns the web pages, database models, migrations, seeders, and API endpoints required by n8n.

Key responsibilities:

- Render the client form.
- Render the staff dashboard.
- Validate submissions.
- Store inquiry records.
- Forward inquiries to n8n.
- Store n8n workflow results.
- Expose mock client context for n8n enrichment.

#### Client Inquiry Form

Route:

```text
GET /client
```

Blade view:

```text
resources/views/client-form.blade.php
```

The form collects:

- Client email.
- Client name.
- Building name.
- Building size.
- Inquiry message.

On success, the form displays a check mark confirmation and a reference ID. The earlier demo counters and the "n8n connected" pill were removed to keep the client UI focused.

#### Staff Dashboard

Route:

```text
GET /staff
```

Blade view:

```text
resources/views/staff-dashboard.blade.php
```

The staff dashboard displays:

- Total inquiry counts.
- Processed inquiry count.
- Escalated inquiry count.
- Human review count.
- Inquiry queue.
- AI classification details.
- Recommended actions.
- Suggested client response.
- Historical reference.
- Integration notes if the workflow fails.

The dashboard refreshes by AJAX polling:

```text
GET /api/enquiries/staff
```

Polling interval:

```text
5 seconds
```

#### Backend API Endpoints

Current API routes:

```text
GET  /api/health
GET  /api/client/context
RAG is handled inside n8n by the Google Sheets Knowledge Base Sheet Tool.
POST /api/enquiries/submit
GET  /api/enquiries/staff
```

These endpoints are used by the web UI, n8n workflow, and staff dashboard polling.

#### Database

Database:

```text
SQLite
```

Database file:

```text
laravel-enquiry/database/database.sqlite
```

Main tables:

- `strata_clients`
- `client_lots`
- `enquiries`

The `enquiries` table stores both the original submission and the n8n response.

#### n8n Workflow

Workflow export:

```text
Enquiry Classifier & Response Generator - Webhook UI.json
```

n8n receives the Laravel submission, performs enrichment and AI analysis, then returns a structured response.

#### AI Classification / Recommendation Node

Node:

```text
Classify & Analyze Inquiry1
```

This is the AI agent node. It uses the submitted client message as the source of truth, uses the Google Sheets knowledge tool as the RAG-style context source, and uses Laravel client context as supporting context.

#### Google Sheets Knowledge Base

Knowledge source:

```text
data/strata_knowledge_base_google_sheet.csv
```

The primary RAG-style retrieval path is the n8n `Knowledge Base Sheet Tool`, which is connected to the AI agent as a tool. The tool reads rows from a Google Sheet created from `data/strata_knowledge_base_google_sheet.csv`.

Historical knowledge for RAG-style retrieval is provided to the n8n AI Agent through the Google Sheets Knowledge Base Sheet Tool. Laravel no longer exposes a RAG endpoint.

Optional Google Sheets source:

```text
data/strata_knowledge_base_google_sheet.csv
```

This CSV can be uploaded into Google Sheets and connected to the n8n `Knowledge Base Sheet Tool`.

---

## 3. Complete Data Flow

### Step 1. Client submits an inquiry from the Laravel client form

The client opens:

```text
http://127.0.0.1:49152/client
```

or the current ngrok URL:

```text
https://8137-103-43-214-18.ngrok-free.app/client
```

The form sends a JSON request to Laravel:

```http
POST /api/enquiries/submit
Content-Type: application/json
Accept: application/json
```

Example payload:

```json
{
  "client_email": "fdc.brylejames@gmail.com",
  "client_name": "Bryle James Gabunada",
  "building_name": "55 Park Avenue",
  "building_size": 123,
  "message": "There is water leaking from the ceiling near the lift and it is getting worse. Can someone attend urgently?"
}
```

### Step 2. Laravel validates and saves the inquiry

Controller:

```text
app/Http/Controllers/Api/EnquirySubmissionController.php
```

Laravel validates:

- `client_email`: required email.
- `client_name`: required string.
- `building_name`: optional string.
- `building_size`: optional integer.
- `message`: required string.

Laravel creates an `enquiries` row with:

- `public_id`
- client fields
- message
- `status = pending`

### Step 3. Laravel sends inquiry data to n8n

Laravel reads:

```text
N8N_WEBHOOK_URL
```

from `.env`, then posts the validated payload to n8n.

Production webhook:

```text
https://gabunadame.app.n8n.cloud/webhook/strata-enquiry
```

Test webhook:

```text
https://gabunadame.app.n8n.cloud/webhook-test/strata-enquiry
```

Important: n8n test webhooks only work while the workflow is listening for a test event.

### Step 4. n8n receives the inquiry data

n8n node:

```text
Strata Inquiry Webhook
```

The workflow receives the submitted JSON payload and starts the automation.

### Step 5. n8n identifies whether the client is new or existing

n8n normalizes the input and calls Laravel:

```http
GET /api/client/context?email={client_email}
```

If the email exists in the mock database, Laravel returns existing client context. If the email is not found, Laravel returns a valid New Client context instead of an error.

### Step 6. n8n checks past inquiry/mock knowledge data

n8n uses the AI agent's Google Sheets tool for RAG-style knowledge retrieval. The sheet is populated from:

```text
data/strata_knowledge_base_google_sheet.csv
```

The AI agent can query rows such as:

```json
{
  "inquiry_id": "enq-002",
  "category": "Maintenance Request",
  "subcategory": "Plumbing Emergency",
  "urgency": "Critical",
  "keywords": "water leak; ceiling; pipe; plumber; emergency",
  "recommended_action": "Dispatch emergency plumber, notify building manager, isolate affected water supply if safe, and notify affected residents."
}
```

Laravel does not expose a RAG endpoint. RAG-style retrieval is handled inside n8n by the Google Sheets Knowledge Base Sheet Tool connected to the AI Agent.

### Step 7. n8n classifies the inquiry type

The AI node classifies the inquiry into categories such as:

- New Client
- Support Request
- Complaint
- Maintenance Request
- Financial Enquiry
- General Question

### Step 8. n8n generates suggested response and recommended staff action

The AI node returns:

- inquiry summary
- urgency
- confidence
- recommended staff actions
- suggested response
- historical reference
- whether human review is required

### Step 9. n8n sends the processed result back to Laravel

n8n responds to the original Laravel webhook request using a `Respond to Webhook` node.

The response is a structured JSON object or array.

### Step 10. Laravel updates the inquiry record

Laravel stores the n8n response in:

```text
enquiries.n8n_response
```

Laravel updates the local status to:

```text
submitted_to_n8n
```

If n8n returns an error, Laravel stores the error message and marks the record as failed.

### Step 11. Staff dashboard displays the inquiry result

The staff dashboard reads formatted inquiry records from:

```text
GET /api/enquiries/staff
```

The UI renders the queue and selected inquiry details.

### Step 12. Staff reviews and decides the next action

The staff member reviews:

- category
- urgency
- confidence
- summary
- recommended actions
- suggested response
- historical reference

The AI output is a recommendation. Staff still decides the final action.

---

## 4. n8n Workflow Design

Workflow file:

```text
Enquiry Classifier & Response Generator - Webhook UI.json
```

### 4.1 Setup Instructions

**Node type:** `n8n-nodes-base.stickyNote`

**Purpose:** Documents workflow configuration requirements directly on the n8n canvas.

**Input data:** None.

**Output data:** None.

**Important configuration:** Mentions backend endpoints, Google Sheets setup, and negative-case handling.

**Why needed:** Helps reviewers understand required Laravel and Google Sheets configuration.

**Connection:** Documentation-only node, not connected to execution flow.

### 4.2 AI Configuration

**Node type:** `n8n-nodes-base.stickyNote`

**Purpose:** Documents AI model settings.

**Input data:** None.

**Output data:** None.

**Important configuration:** Notes the model, temperature, and token limit.

**Why needed:** Makes AI settings visible during the demo.

**Connection:** Documentation-only node.

### 4.3 Custom UI Webhook Notes

**Node type:** `n8n-nodes-base.stickyNote`

**Purpose:** Documents the custom Laravel UI webhook flow.

**Input data:** None.

**Output data:** None.

**Important configuration:** Shows webhook URL and Laravel/ngrok backend URL.

**Why needed:** Clarifies that the Laravel form, not the original n8n form, is the active client input UI.

**Connection:** Documentation-only node.

### 4.4 OpenAI Model1

**Node type:** `@n8n/n8n-nodes-langchain.lmChatOpenAi`

**Purpose:** Provides the language model used by the AI agent.

**Input data:** Prompt and context from the AI agent.

**Output data:** Model completion used by the structured output parser.

**Important configuration:**

- Model: `gpt-4o-mini`
- Temperature: `0.2`
- Max tokens: `1200`

**Why needed:** Performs the natural language reasoning, classification, and response drafting.

**Connection:** Connected to `Classify & Analyze Inquiry1` as `ai_languageModel`.

### 4.5 Structured Output Parser1

**Node type:** `@n8n/n8n-nodes-langchain.outputParserStructured`

**Purpose:** Forces the AI output into a predictable JSON structure.

**Input data:** AI-generated response text.

**Output data:** Structured JSON with known fields.

**Important configuration:** JSON schema example includes:

- `client_type`
- `category`
- `urgency`
- `confidence`
- `summary`
- `recommended_actions`
- `historical_reference`
- `suggested_response`
- `requires_human_review`

**Why needed:** Laravel and the staff dashboard expect consistent fields. Without structured output, the AI might return prose that is difficult to parse.

**Connection:** Connected to `Classify & Analyze Inquiry1` as `ai_outputParser`.

### 4.6 Strata Inquiry Webhook

**Node type:** `n8n-nodes-base.webhook`

**Purpose:** Receives inquiry payloads from Laravel.

**Input data:** JSON payload from Laravel.

**Example input:**

```json
{
  "client_email": "fdc.brylejames@gmail.com",
  "client_name": "Bryle James Gabunada",
  "building_name": "55 Park Avenue",
  "building_size": 123,
  "message": "There is water leaking from the ceiling near the lift."
}
```

**Output data:** n8n webhook item containing the submitted payload.

**Important configuration:**

- HTTP method: `POST`
- Path: `strata-enquiry`
- Response mode: response node

**Why needed:** This is the entry point from the Laravel web UI into n8n.

**Connection:** Sends data to `Normalize Client Input`.

### 4.7 Normalize Client Input

**Node type:** `n8n-nodes-base.set`

**Purpose:** Normalizes field names and protects the workflow from missing values.

**Input data:** Raw webhook payload.

**Output data:**

```json
{
  "client_email": "fdc.brylejames@gmail.com",
  "client_name": "Bryle James Gabunada",
  "building_name": "55 Park Avenue",
  "building_size": 123,
  "message": "There is water leaking from the ceiling near the lift.",
  "source": "custom_laravel_ui",
  "raw_input": {
    "client_email": "fdc.brylejames@gmail.com",
    "client_name": "Bryle James Gabunada",
    "building_name": "55 Park Avenue",
    "building_size": 123,
    "message": "There is water leaking from the ceiling near the lift."
  },
  "validation_errors": []
}
```

**Important configuration:**

- Converts email to lowercase.
- Defaults missing name to `Client`.
- Converts building size to a number.
- Captures missing required field warnings in `validation_errors`.

**Why needed:** Prevents null fields and root/body payload mismatches from breaking downstream nodes.

**Connection:** Sends data to `Lookup Client Context1`.

### 4.8 Lookup Client Context1

**Node type:** `n8n-nodes-base.httpRequest`

**Purpose:** Calls Laravel to identify whether the submitted email belongs to an existing mock client.

**Input data:**

```json
{
  "client_email": "fdc.brylejames@gmail.com"
}
```

**Request:**

```http
GET https://8137-103-43-214-18.ngrok-free.app/api/client/context?email=fdc.brylejames%40gmail.com
```

**Headers:**

```text
accept: application/json
ngrok-skip-browser-warning: true
```

**Output data for existing client example:**

```json
{
  "client_exists": true,
  "client_id": "CLIENT-001",
  "client_type": "Existing",
  "assigned_consultant": "Sarah Johnson",
  "past_inquiries_count": 12,
  "active_projects": ["Manager Transition"],
  "open_requests": 2,
  "levy_status": "current",
  "portal_access": true
}
```

**Output data for new client example:**

```json
{
  "client_exists": false,
  "client_id": null,
  "client_type": "New",
  "assigned_consultant": "Business Development",
  "past_inquiries_count": 0,
  "active_projects": [],
  "open_requests": 0,
  "levy_status": "n/a",
  "portal_access": false
}
```

**Important configuration:**

- `neverError` is enabled so the workflow can handle bad responses.
- `alwaysOutputData` is enabled.

**Why needed:** Client status affects classification and routing. New client is not an error; it is a valid business state.

**Connection:** Sends data to `Prepare AI Context1`.

### 4.9 Knowledge Base Sheet Tool

**Node type:** `n8n-nodes-base.httpRequest`

**Purpose:** Calls Laravel to retrieve similar historical inquiries as a fallback/mock context source.

**Input data:**

```json
{
  "message": "There is water leaking from the ceiling near the lift."
}
```

**Request:**

```http
Google Sheets tool connected to the AI Agent
Content-Type: application/json
```

**Request body:**

```json
{
  "query": "There is water leaking from the ceiling near the lift.",
  "limit": 3
}
```

**Output data:**

```json
{
  "similar_cases": [
    {
      "inquiry_id": "enq-002",
      "category": "Maintenance Request",
      "subcategory": "Plumbing Emergency",
      "urgency": "Critical",
      "summary": "Water leaking from ceiling in common corridor, Level 3, possible pipe burst",
      "recommended_action": "Dispatch emergency plumber immediately.",
      "suggested_response": "Thank you for alerting us immediately...",
      "previous_resolution": "plumber on-site in 45 mins, pipe joint replaced",
      "score": 14
    }
  ]
}
```

**Important configuration:**

- Method: `POST`
- Sends JSON body.
- Adds `ngrok-skip-browser-warning`.
- `neverError` is enabled.
- `alwaysOutputData` is enabled.

**Why needed:** Provides a backup similar-case source if the Google Sheets tool is not configured or does not return useful rows. For the intended demo, the AI agent's Google Sheets tool is the primary RAG-style retrieval mechanism.

**Connection:** Sends data to `Prepare AI Context1`.

### 4.10 Prepare AI Context1

**Node type:** `n8n-nodes-base.set`

**Purpose:** Combines normalized input, client context, Google Sheets tool metadata, fallback similar cases, source metadata, and validation status into one clean AI input object.

**Input data:**

- Normalized client fields.
- Client context HTTP response.
- Google Sheets knowledge tool result, when available.

**Output data:**

```json
{
  "inquiry_message": "There is water leaking from the ceiling near the lift.",
  "client_email": "fdc.brylejames@gmail.com",
  "client_name": "Bryle James Gabunada",
  "building_name": "55 Park Avenue",
  "building_size": 123,
  "client_context": {
    "client_exists": false,
    "client_type": "New",
    "assigned_consultant": "Business Development",
    "lookup_status": "not_found"
  },
  "similar_cases": [
    {
      "inquiry_id": "enq-002",
      "category": "Maintenance Request",
      "urgency": "Critical"
    }
  ],
  "workflow_source": "custom_laravel_ui",
  "knowledge_sources": {
    "google_sheet_tool": "Knowledge Base Sheet Tool",
    "google_sheet_tool": "Knowledge Base Sheet Tool",
    "client_lookup": "Lookup Client Context1"
  },
  "validation_errors": []
}
```

**Important configuration:**

- Converts failed/HTML/ngrok responses into safe unavailable context.
- Converts client not found into a New Client context.
- Converts missing fallback similar cases into an empty array.

**Why needed:** Prevents infrastructure errors from becoming the text the AI analyzes.

**Connection:** Sends data to `Classify & Analyze Inquiry1`.

### 4.11 Knowledge Base Sheet Tool

**Node type:** `n8n-nodes-base.googleSheetsTool`

**Purpose:** Primary RAG-style knowledge tool that lets the AI retrieve rows from a Google Sheets knowledge base.

**Input data:** Tool queries generated by the AI agent.

**Output data:** Matching rows from the configured Google Sheet.

**Important configuration:**

- Upload `data/strata_knowledge_base_google_sheet.csv` to Google Sheets.
- Name the tab `KnowledgeBase`.
- Configure Google OAuth in n8n.
- Select the Google document and sheet in this node.

**Why needed:** This is the intended RAG-style retrieval mechanism for the demo. It lets the AI agent query structured past inquiry knowledge directly as a tool instead of depending only on a fixed HTTP lookup.

**Connection:** Connected to `Classify & Analyze Inquiry1` as `ai_tool`.

### 4.12 Classify & Analyze Inquiry1

**Node type:** `@n8n/n8n-nodes-langchain.agent`

**Purpose:** Main AI analysis node.

**Input data:** Prepared AI context.

**Output data:**

```json
{
  "output": {
    "client_type": "New Client",
    "category": "Maintenance Request",
    "urgency": "High",
    "confidence": 94,
    "summary": "The client reports an active water leak near the lift requiring urgent maintenance attendance.",
    "recommended_actions": [
      "Treat this as an urgent maintenance issue.",
      "Dispatch or contact the emergency plumbing contractor.",
      "Notify the building manager and ask residents to avoid the affected area.",
      "Record photos and follow up with affected occupants."
    ],
    "historical_reference": {
      "similar_case_id": "enq-002",
      "previous_resolution": "plumber on-site in 45 mins, pipe joint replaced"
    },
    "suggested_response": "Dear Bryle James Gabunada,\n\nThank you for alerting us...",
    "requires_human_review": true
  }
}
```

**Important configuration:**

- Uses `OpenAI Model1`.
- Uses `Structured Output Parser1`.
- Uses `Knowledge Base Sheet Tool` for RAG-style knowledge retrieval when configured.
- Prompt instructs AI to analyze only the submitted client message.
- Prompt treats missing client and unavailable sheet knowledge as normal cases.

**Why needed:** Performs classification, prioritization, recommendation, and response drafting.

**Connection:** Sends structured output to `Check Urgency1`.

### 4.13 Check Urgency1

**Node type:** `n8n-nodes-base.if`

**Purpose:** Routes urgent or human-review cases to escalation.

**Input data:**

```json
{
  "output": {
    "urgency": "High",
    "requires_human_review": true
  }
}
```

**Output data:**

- True branch: `Escalate to Human Review1`
- False branch: `Standard Response1`

**Important configuration:**

Conditions:

- `output.urgency == High`
- OR `output.requires_human_review == true`

**Why needed:** Separates urgent/manual-review cases from standard auto-processed cases.

### 4.14 Escalate to Human Review1

**Node type:** `n8n-nodes-base.set`

**Purpose:** Formats final response for urgent or human-review inquiries.

**Input data:** AI output and prepared client context.

**Output data:**

```json
{
  "status": "ESCALATED",
  "notification": "HIGH PRIORITY: Maintenance Request from Bryle James Gabunada",
  "assigned_to": "Business Development",
  "analysis": {
    "client_type": "New Client",
    "category": "Maintenance Request",
    "urgency": "High"
  },
  "client_info": {
    "client_name": "Bryle James Gabunada",
    "client_email": "fdc.brylejames@gmail.com"
  }
}
```

**Important configuration:**

- Sets `status` to `ESCALATED`.
- Includes assigned consultant.
- Includes full analysis.
- Includes client information.

**Why needed:** Gives staff a clear high-priority queue item.

**Connection:** Sends data to `Respond to Webhook1`.

### 4.15 Standard Response1

**Node type:** `n8n-nodes-base.set`

**Purpose:** Formats final response for normal processed inquiries.

**Input data:** AI output and prepared client context.

**Output data:**

```json
{
  "status": "PROCESSED",
  "notification": "PROCESSED: General Question from Bryle James Gabunada",
  "analysis": {
    "client_type": "New Client",
    "category": "General Question",
    "urgency": "Low"
  },
  "client_info": {
    "client_name": "Bryle James Gabunada",
    "client_email": "fdc.brylejames@gmail.com"
  }
}
```

**Important configuration:** Sets `status` to `PROCESSED`.

**Why needed:** Produces a consistent final object for non-escalated inquiries.

**Connection:** Sends data to `Respond to Webhook`.

### 4.16 Respond to Webhook

**Node type:** `n8n-nodes-base.respondToWebhook`

**Purpose:** Sends the standard processed response back to Laravel.

**Input data:** Final processed object.

**Output data:** HTTP response to Laravel.

**Important configuration:**

- Responds with all incoming items.

**Why needed:** Laravel waits for this response and stores it in the database.

### 4.17 Respond to Webhook1

**Node type:** `n8n-nodes-base.respondToWebhook`

**Purpose:** Sends the escalated response back to Laravel.

**Input data:** Final escalated object.

**Output data:** HTTP response to Laravel.

**Important configuration:**

- Responds with all incoming items.

**Why needed:** Laravel stores the escalated workflow result in the inquiry record.

### 4.18 Workflow Guide1

**Node type:** `n8n-nodes-base.stickyNote`

**Purpose:** High-level overview note on the n8n canvas.

**Input data:** None.

**Output data:** None.

**Connection:** Documentation-only node.

---

## 5. n8n Node Input and Output Examples

### Webhook Input from Laravel

```json
{
  "client_email": "fdc.brylejames@gmail.com",
  "client_name": "Bryle James Gabunada",
  "building_name": "55 Park Avenue",
  "building_size": 123,
  "message": "There is water leaking from the ceiling near the lift and it is getting worse. Can someone attend urgently?"
}
```

### Normalize Client Input Output

```json
{
  "client_email": "fdc.brylejames@gmail.com",
  "client_name": "Bryle James Gabunada",
  "building_name": "55 Park Avenue",
  "building_size": 123,
  "message": "There is water leaking from the ceiling near the lift and it is getting worse. Can someone attend urgently?",
  "source": "custom_laravel_ui",
  "validation_errors": []
}
```

### Client Lookup Output for Existing Client

```json
{
  "client_exists": true,
  "client_id": 381,
  "client_type": "Existing",
  "assigned_consultant": "Sarah Johnson",
  "past_inquiries_count": 12,
  "active_projects": ["Manager Transition"],
  "open_requests": 1,
  "levy_status": "current",
  "portal_access": true
}
```

### Client Lookup Output for Not Found

```json
{
  "client_exists": false,
  "client_id": null,
  "client_type": "New",
  "assigned_consultant": "Business Development",
  "past_inquiries_count": 0,
  "active_projects": [],
  "open_requests": 0,
  "levy_status": "n/a",
  "portal_access": false
}
```

### Fallback Similar-Case API Output

```json
{
  "similar_cases": [
    {
      "inquiry_id": "enq-002",
      "category": "Maintenance Request",
      "subcategory": "Plumbing Emergency",
      "urgency": "Critical",
      "client_status": "existing",
      "summary": "Water leaking from ceiling in common corridor, Level 3, possible pipe burst",
      "original_message": "There is water dripping from the ceiling on Level 3 near the lift.",
      "recommended_action": "Dispatch emergency plumber immediately.",
      "suggested_response": "Thank you for alerting us immediately...",
      "previous_resolution": "plumber on-site in 45 mins, pipe joint replaced",
      "score": 14
    }
  ]
}
```

### Prepared AI Context

```json
{
  "inquiry_message": "There is water leaking from the ceiling near the lift and it is getting worse. Can someone attend urgently?",
  "client_email": "fdc.brylejames@gmail.com",
  "client_name": "Bryle James Gabunada",
  "building_name": "55 Park Avenue",
  "building_size": 123,
  "client_context": {
    "client_exists": false,
    "client_type": "New",
    "assigned_consultant": "Business Development",
    "lookup_status": "not_found"
  },
  "similar_cases": [
    {
      "inquiry_id": "enq-002",
      "category": "Maintenance Request",
      "urgency": "Critical"
    }
  ],
  "validation_errors": []
}
```

### AI Output

```json
{
  "client_type": "New Client",
  "category": "Maintenance Request",
  "urgency": "High",
  "confidence": 94,
  "summary": "The client reports an active water leak near the lift requiring urgent maintenance attendance.",
  "recommended_actions": [
    "Treat this as an urgent maintenance issue.",
    "Contact an emergency plumber or building contractor immediately.",
    "Notify the building manager and ask residents to avoid the affected area.",
    "Record the incident and follow up with affected occupants."
  ],
  "historical_reference": {
    "similar_case_id": "enq-002",
    "previous_resolution": "plumber on-site in 45 mins, pipe joint replaced"
  },
  "suggested_response": "Dear Bryle James Gabunada,\n\nThank you for alerting us to the water leak near the lift. We are treating this as urgent and will arrange contractor attendance as soon as possible.\n\nPlease avoid the affected area and let us know if the leak worsens or creates any immediate safety concern.\n\nBest regards,\nStrata Support Team",
  "requires_human_review": true
}
```

### Escalated Final n8n Response

```json
[
  {
    "status": "ESCALATED",
    "notification": "HIGH PRIORITY: Maintenance Request from Bryle James Gabunada",
    "assigned_to": "Business Development",
    "analysis": {
      "client_type": "New Client",
      "category": "Maintenance Request",
      "urgency": "High",
      "confidence": 94,
      "summary": "The client reports an active water leak near the lift requiring urgent maintenance attendance.",
      "recommended_actions": [
        "Treat this as an urgent maintenance issue.",
        "Contact an emergency plumber or building contractor immediately."
      ],
      "historical_reference": {
        "similar_case_id": "enq-002",
        "previous_resolution": "plumber on-site in 45 mins, pipe joint replaced"
      },
      "suggested_response": "Dear Bryle James Gabunada...",
      "requires_human_review": true
    },
    "client_info": {
      "inquiry_message": "There is water leaking from the ceiling near the lift and it is getting worse.",
      "client_email": "fdc.brylejames@gmail.com",
      "client_name": "Bryle James Gabunada",
      "building_name": "55 Park Avenue",
      "building_size": 123
    }
  }
]
```

---

## 6. Laravel Implementation Details

### Important Laravel Files

```text
routes/web.php
routes/api.php
app/Http/Controllers/DashboardController.php
app/Http/Controllers/Api/EnquirySubmissionController.php
app/Http/Controllers/Api/ClientContextController.php
app/Models/Enquiry.php
app/Models/StrataClient.php
resources/views/client-form.blade.php
resources/views/staff-dashboard.blade.php
database/seeders/MockWorkflowDataSeeder.php
```

### Web Routes

```php
Route::redirect('/', '/client');
Route::get('/client', [DashboardController::class, 'client'])->name('client.form');
Route::get('/staff', [DashboardController::class, 'staff'])->name('staff.dashboard');
```

### API Routes

```php
Route::get('/health', ...);
Route::get('/client/context', ClientContextController::class);
// No Laravel RAG route; n8n AI Agent uses Google Sheets for RAG.
Route::post('/enquiries/submit', EnquirySubmissionController::class);
Route::get('/enquiries/staff', [DashboardController::class, 'staffData']);
```

### Enquiry Storage

The `Enquiry` model stores:

- `public_id`
- `client_email`
- `client_name`
- `building_name`
- `building_size`
- `message`
- `status`
- `n8n_response`
- `error_message`

`n8n_response` is cast as an array.

### Staff Dashboard Polling

The staff dashboard polls:

```text
GET /api/enquiries/staff
```

Every:

```text
5 seconds
```

The response contains:

```json
{
  "enquiries": [],
  "totalCount": 12,
  "processedCount": 4,
  "escalatedCount": 3,
  "reviewCount": 5,
  "latestId": "uuid",
  "refreshedAt": "2026-05-13T09:30:00+08:00"
}
```

---

## 7. Terminal Setup and Run Commands

### Start Laravel

```bash
cd "/Users/fdc.brylejames-nc-web/Documents/Personal Task/laravel-enquiry"
PHP_CLI_SERVER_WORKERS=4 php -d max_execution_time=0 artisan serve --host=127.0.0.1 --port=49152 --no-reload
```

The multi-worker setup is important. During submission, Laravel waits for n8n while n8n calls back into Laravel. A single-worker PHP dev server can block those callback requests.

### Start ngrok

```bash
ngrok http http://127.0.0.1:49152
```

Current example ngrok URL:

```text
https://8137-103-43-214-18.ngrok-free.app
```

### Verify Laravel Locally

```bash
curl http://127.0.0.1:49152/api/health
```

Expected:

```json
{
  "ok": true,
  "workflow": "Enquiry Classifier & Response Generator",
  "role": "laravel-context-and-dashboard-backend",
  "n8n_webhook_configured": true
}
```

### Verify Laravel Through ngrok

```bash
curl -H "ngrok-skip-browser-warning: true" \
  https://8137-103-43-214-18.ngrok-free.app/api/health
```

### Run Migrations and Seeders

```bash
php artisan migrate:fresh --seed
```

### Run Tests

```bash
php artisan test
```

---

## 8. Environment Variables

Required `.env` values:

```text
APP_NAME="Strata Enquiry Desk"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:49152
DB_CONNECTION=sqlite
N8N_WEBHOOK_URL=https://gabunadame.app.n8n.cloud/webhook/strata-enquiry
```

Use test webhook only while listening in n8n:

```text
N8N_WEBHOOK_URL=https://gabunadame.app.n8n.cloud/webhook-test/strata-enquiry
```

---

## 9. Database Setup

Create SQLite file:

```bash
touch database/database.sqlite
```

Run migrations:

```bash
php artisan migrate
```

Fresh reset with seed data:

```bash
php artisan migrate:fresh --seed
```

Seeder:

```text
database/seeders/MockWorkflowDataSeeder.php
```

Demo client seed data is defined directly in `database/seeders/MockWorkflowDataSeeder.php` and inserted into the SQLite database.

Optional Google Sheets data:

```text
../data/strata_knowledge_base_google_sheet.csv
```

---

## 10. Demo Presentation Guide

### Demo Part 1: Explain the Architecture

Show the audience the flow:

```text
Client Form -> Laravel -> SQLite -> n8n -> Laravel client lookup + Google Sheets knowledge tool -> AI -> Laravel -> Staff Dashboard
```

Explain that Laravel is the application and data layer, while n8n is the automation and AI layer.

### Demo Part 2: Show n8n Workflow

Open n8n and walk through:

1. Webhook trigger.
2. Normalize input.
3. Client lookup.
4. Google Sheets knowledge tool for RAG-style past inquiry reference.
5. Optional Laravel similar-case fallback lookup.
6. Prepare AI context.
7. AI classification.
8. Urgency check.
9. Escalated or standard response.
10. Respond to Laravel.

### Demo Part 3: Show Laravel Client Form

Open:

```text
http://127.0.0.1:49152/client
```

Submit a realistic urgent inquiry:

```text
There is water leaking from the ceiling near the lift and it is getting worse. Can someone attend urgently?
```

Show the success check mark and reference ID.

### Demo Part 4: Show n8n Execution

Show that n8n received the payload, called the Laravel client lookup endpoint, used the Google Sheets knowledge tool for past inquiry reference, and generated a structured AI response.

### Demo Part 5: Show Staff Dashboard

Open:

```text
http://127.0.0.1:49152/staff
```

Show that the staff dashboard updates automatically by polling. Review the new inquiry and point out:

- urgency
- category
- confidence
- recommended actions
- suggested response
- historical reference

### Demo Part 6: Explain Negative Cases

Mention:

- Unknown email becomes New Client.
- Empty or unavailable Google Sheets results are allowed.
- Test webhook requires "Listen for test event".
- ngrok URL must stay current.
- Laravel must run with multiple workers for callback reliability.

---

## 11. Troubleshooting

### n8n says webhook is not registered

Cause:

- Production workflow is not active, or
- Test webhook is not currently listening.

Fix:

- Activate workflow for `/webhook/strata-enquiry`.
- Or click **Listen for test event** and use `/webhook-test/strata-enquiry`.

### Browser shows ngrok gateway error

Cause:

- Laravel server is stopped.
- ngrok is forwarding to a port with no running app.
- Laravel single-worker server is blocked while waiting for n8n.

Fix:

```bash
PHP_CLI_SERVER_WORKERS=4 php -d max_execution_time=0 artisan serve --host=127.0.0.1 --port=49152 --no-reload
```

### n8n receives HTML instead of JSON

Cause:

- ngrok browser warning page.
- Laravel server unreachable.
- Wrong ngrok URL in n8n HTTP Request nodes.

Fix:

- Add header: `ngrok-skip-browser-warning: true`.
- Verify `/api/health` through ngrok.
- Update n8n callback URLs after ngrok restart.

### Client lookup is unavailable

Test:

```bash
curl -H "ngrok-skip-browser-warning: true" \
  "https://8137-103-43-214-18.ngrok-free.app/api/client/context?email=test@example.com"
```

Expected: JSON response, not HTML.

### Staff dashboard does not update

Test:

```bash
curl http://127.0.0.1:49152/api/enquiries/staff
```

The staff dashboard polls this endpoint every 5 seconds.

### AI output talks about ngrok or endpoint errors

Cause:

- n8n used infrastructure error content as context.

Fix:

- Confirm Laravel callback endpoints return JSON.
- Confirm the workflow has the latest `Prepare AI Context1` guardrails.
- Resubmit the inquiry after callback endpoints are healthy.

---

## 12. Assumptions and Demo Constraints

- This is a local demo, not a production deployment.
- SQLite is used for simplicity.
- Mock client and historical inquiry data are acceptable for demonstration.
- n8n Cloud is the AI workflow runtime.
- Laravel does not call OpenAI directly.
- AJAX polling is sufficient for near-realtime staff dashboard behavior.
- Staff members review AI output before taking final action.
- Free ngrok URLs can change and must be updated in n8n.
- The production n8n webhook works only when the workflow is active.

---

## 13. Expected Demo Outcome

At the end of the demo, the audience should understand:

- How a client inquiry is submitted.
- How Laravel stores and forwards the inquiry.
- How n8n enriches and analyzes the inquiry.
- How AI classification and recommendations are generated.
- How staff review the results.
- How mock client and past inquiry data support the workflow.
- How the system handles negative cases such as new clients or unavailable context.
