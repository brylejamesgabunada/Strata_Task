# Laravel Strata Enquiry Demo

This Laravel app replaces the earlier Node backend with a database-backed implementation.

## What It Does

- Serves a client enquiry form at `/`.
- Stores submitted enquiries in SQLite.
- Seeds mock client and past enquiry data from the workspace JSON files.
- Exposes n8n Cloud backend endpoints:
  - `GET /api/client/context?email=...`
  - `POST /api/rag/search`
- Forwards client form submissions to:
  - `POST /api/enquiries/submit`
  - configured by `N8N_WEBHOOK_URL`

AI analysis remains in n8n. Laravel only provides the web UI, database, client lookup, similar case retrieval, and webhook forwarding.

## Run

```bash
composer install
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=49152
```

Expose it to n8n Cloud:

```bash
ngrok http 49152
```

Current demo base URL:

```text
https://e293-103-43-214-18.ngrok-free.app
```

Use these n8n HTTP Request URLs:

```text
https://e293-103-43-214-18.ngrok-free.app/api/client/context?email={{$json.client_email}}
```

```text
https://e293-103-43-214-18.ngrok-free.app/api/rag/search
```

Add this header to both n8n HTTP Request nodes:

```text
ngrok-skip-browser-warning: true
```
