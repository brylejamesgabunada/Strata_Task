# Strata Enquiry Workflow Demo

This workspace contains the n8n workflow export, mock data, and the Laravel demo app for the Strata Enquiry Desk.

## Primary App

Use the Laravel implementation in:

```text
laravel-enquiry/
```

Demo and setup documentation:

```text
laravel-enquiry/README-STRATA.md
```

## Important Files

- `laravel-enquiry/` - Laravel client form, staff dashboard, API, SQLite database, migrations, and seeders.
- `Enquiry Classifier & Response Generator - Webhook UI.json` - n8n workflow for the custom Laravel UI.
- `data/strata_knowledge_base_google_sheet.csv` - optional Google Sheets knowledge base data for the n8n tool node.
- `mock_client_database.json` - mock client records seeded into Laravel.
- `rag_seed_data.json` - historical enquiry records seeded into Laravel.

## Quick Start

```bash
cd "laravel-enquiry"
composer install
touch database/database.sqlite
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
PHP_CLI_SERVER_WORKERS=4 php -d max_execution_time=0 artisan serve --host=127.0.0.1 --port=49152 --no-reload
```

Open:

```text
http://127.0.0.1:49152/client
http://127.0.0.1:49152/staff
```

For n8n Cloud callbacks, expose Laravel:

```bash
ngrok http http://127.0.0.1:49152
```

Then update the n8n HTTP Request nodes to use the current ngrok HTTPS URL.

The older Node prototype files are kept only as reference material; the Laravel app is the demo target.
