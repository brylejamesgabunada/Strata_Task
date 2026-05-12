# Strata Enquiry Workflow Demo

Local web UI and staff dashboard for the `Enquiry Classifier & Response Generator` n8n structure.

## Run

```bash
npm start
```

Open the local URL printed in the terminal. By default the app asks the operating system for an unused port, so it will not collide with an existing local app.

The app runs without installing packages. For n8n Cloud, this local service provides client context and similar past enquiry records; the AI analysis remains inside the n8n workflow.

## Local API

- `GET /api/client/context?email=maria.santos@email.com`
- `POST /api/rag/search` with `{ "query": "noise complaint", "limit": 3 }`
- `POST /api/enquiries` with the n8n form fields:

```json
{
  "client_email": "maria.santos@email.com",
  "client_name": "Maria Santos",
  "building_name": "22 Harbour Street",
  "building_size": 72,
  "message": "Noise issue still happening every night after 10pm."
}
```

Submitted enquiries are stored in `data/enquiries.json` for the staff dashboard.

## n8n Placeholder URLs

For the original n8n workflow, replace the placeholder HTTP nodes with these local endpoints while the Node app is running:

- Lookup Client Context: `http://host.docker.internal:<printed-port>/api/client/context?email={{$json.client_email}}`
- Retrieve Similar Cases (RAG): `http://host.docker.internal:<printed-port>/api/rag/search`

Use `http://localhost:<printed-port>/...` instead if n8n is running directly on the host rather than inside Docker.

If Docker n8n cannot reach the host service, start the app with:

```bash
HOST=0.0.0.0 npm start
```

To force a specific known-free port:

```bash
PORT=49152 npm start
```

## n8n Cloud

Your n8n Cloud workflow cannot call `localhost`, `127.0.0.1`, or `host.docker.internal` on this machine.

For the cloud demo, import:

`Enquiry Classifier & Response Generator - n8n Cloud Mock.json`

That version keeps the same form, AI analysis, parser, urgency check, escalation, and standard response flow, but replaces the missing backend HTTP Request nodes with Code nodes containing static client and past enquiry records.

## n8n Cloud With ngrok

You can also keep the original n8n Cloud workflow and point its HTTP Request nodes to this local app through ngrok.

Start the local app on a fixed port:

```bash
PORT=49152 npm start
```

In another terminal, expose that port:

```bash
ngrok http 49152
```

ngrok will print a public HTTPS URL like:

```text
https://example.ngrok-free.app
```

Use that public URL in the n8n Cloud workflow:

- Lookup Client Context: `https://example.ngrok-free.app/api/client/context?email={{$json.client_email}}`
- Retrieve Similar Cases (RAG): `https://example.ngrok-free.app/api/rag/search`

For the RAG node, keep the method as `POST` and keep the JSON body:

```json
{
  "query": "={{ $(\"Strata Inquiry Form\").item.json.message }}",
  "limit": 3
}
```

If ngrok returns a browser warning page, add this header to both n8n HTTP Request nodes:

```text
ngrok-skip-browser-warning: true
```

The local app and ngrok tunnel both need to stay running while n8n Cloud executes the workflow. Free ngrok URLs usually change when restarted, so update the two n8n URLs after each restart unless you use a reserved ngrok domain.

## Custom UI to n8n Webhook

For a custom client input form, import:

`Enquiry Classifier & Response Generator - Webhook UI.json`

That workflow replaces the n8n hosted Form Trigger with a Webhook Trigger at:

```text
https://gabunadame.app.n8n.cloud/webhook/strata-enquiry
```

Start this local UI/backend with that webhook configured:

```bash
PORT=49152 N8N_WEBHOOK_URL=https://gabunadame.app.n8n.cloud/webhook/strata-enquiry npm start
```

When `N8N_WEBHOOK_URL` is set, the web UI submits the client form to n8n through `/api/n8n/submit`. n8n then calls the ngrok backend endpoints for client context and RAG data.

## Laravel Version

A Laravel implementation is available in `laravel-enquiry`.

It uses SQLite plus seeders for mock clients and past enquiry records, exposes the same n8n endpoints, and serves a client form that forwards submissions to the n8n webhook.

Current Laravel/ngrok base URL:

```text
https://e293-103-43-214-18.ngrok-free.app
```

Laravel notes:

```text
laravel-enquiry/README-STRATA.md
```
