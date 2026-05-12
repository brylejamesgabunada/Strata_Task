# n8n Cloud Notes

Your workflow is running on n8n Cloud:

`https://gabunadame.app.n8n.cloud/workflow/aw10EJHYqCenIw6T`

n8n Cloud cannot reach services running on your Mac at `localhost`, `127.0.0.1`, or `host.docker.internal`. That means the original placeholder HTTP Request nodes will fail unless their URLs point to public HTTPS endpoints.

For the demo requirement, use the generated cloud mock workflow:

`Enquiry Classifier & Response Generator - n8n Cloud Mock.json`

This keeps the same n8n structure but replaces:

- `Lookup Client Context` with a Code node containing static client records.
- `Retrieve Similar Cases (RAG)` with a Code node containing static past enquiry records.

The hosted n8n Form Trigger remains the web input. The final `Escalate to Human Review` and `Standard Response` nodes provide the staff-facing result inside the n8n execution output.

If you want n8n Cloud to call the local dashboard app instead, expose the local app through a public HTTPS tunnel or deploy it to a public host, then use that public URL in the HTTP Request nodes.

## ngrok Option

ngrok is installed on this machine. To expose the local app to n8n Cloud:

```bash
PORT=49152 npm start
```

In a second terminal:

```bash
ngrok http 49152
```

Use the generated HTTPS URL in the original n8n workflow:

- `Lookup Client Context`: `https://<your-ngrok-domain>/api/client/context?email={{$json.client_email}}`
- `Retrieve Similar Cases (RAG)`: `https://<your-ngrok-domain>/api/rag/search`

Keep the RAG node as `POST` with this JSON body:

```json
{
  "query": "={{ $(\"Strata Inquiry Form\").item.json.message }}",
  "limit": 3
}
```

If n8n receives HTML instead of JSON from ngrok, add this request header to both HTTP Request nodes:

```text
ngrok-skip-browser-warning: true
```

Keep both the Node app and ngrok process running during the demo. If the ngrok URL changes, update both n8n nodes.

## Custom UI Submission

For a custom client-side form instead of the hosted n8n form, import:

`Enquiry Classifier & Response Generator - Webhook UI.json`

This version replaces the n8n Form Trigger with a Webhook Trigger at:

```text
https://gabunadame.app.n8n.cloud/webhook/strata-enquiry
```

Start the local UI/backend with:

```bash
PORT=49152 N8N_WEBHOOK_URL=https://gabunadame.app.n8n.cloud/webhook/strata-enquiry npm start
```

When that environment variable is present, the web UI posts client enquiries to `/api/n8n/submit`, and the backend forwards them to n8n Cloud. n8n then calls the ngrok client context and RAG endpoints as part of the workflow.
