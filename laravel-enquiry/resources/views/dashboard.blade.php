<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Strata Enquiry Desk</title>
    <style>
        :root {
            --bg: #f4f6f1;
            --panel: #ffffff;
            --ink: #1f2a24;
            --muted: #68746f;
            --line: #d9dfd6;
            --accent: #176b5d;
            --accent-dark: #104d43;
            --warn: #a5661f;
            --error: #b9403a;
            --shadow: 0 22px 60px rgba(31, 42, 36, 0.1);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background:
                linear-gradient(135deg, rgba(23, 107, 93, 0.08), transparent 34%),
                radial-gradient(circle at 82% 16%, rgba(165, 102, 31, 0.12), transparent 25%),
                var(--bg);
            color: var(--ink);
            font-family: "Avenir Next", "Segoe UI", sans-serif;
            letter-spacing: 0;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 26px 34px;
            background: rgba(255, 255, 255, 0.78);
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(12px);
        }

        h1, h2, p {
            margin: 0;
        }

        h1 {
            font-family: Georgia, serif;
            font-size: 28px;
            line-height: 1.1;
        }

        header p {
            margin-top: 6px;
            color: var(--muted);
            font-size: 14px;
        }

        .status {
            border: 1px solid rgba(23, 107, 93, 0.25);
            border-radius: 999px;
            padding: 8px 12px;
            background: rgba(23, 107, 93, 0.08);
            color: var(--accent-dark);
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        main {
            display: grid;
            grid-template-columns: minmax(340px, 460px) minmax(0, 1fr);
            gap: 24px;
            max-width: 1360px;
            margin: 0 auto;
            padding: 28px 34px 44px;
        }

        section {
            min-width: 0;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel);
            box-shadow: var(--shadow);
        }

        .panel-head {
            padding: 20px;
            border-bottom: 1px solid var(--line);
        }

        .eyebrow {
            margin-bottom: 5px;
            color: var(--accent);
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
        }

        h2 {
            font-size: 20px;
        }

        form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            padding: 20px;
        }

        label {
            display: grid;
            gap: 7px;
            min-width: 0;
        }

        label span {
            color: #3d4944;
            font-size: 13px;
            font-weight: 800;
        }

        .wide {
            grid-column: 1 / -1;
        }

        input, textarea {
            width: 100%;
            min-width: 0;
            border: 1px solid #cbd4ca;
            border-radius: 6px;
            padding: 11px 12px;
            font: inherit;
            outline: none;
        }

        textarea {
            resize: vertical;
        }

        input:focus, textarea:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(23, 107, 93, 0.13);
        }

        button {
            min-height: 42px;
            border: 0;
            border-radius: 6px;
            padding: 10px 16px;
            background: var(--accent);
            color: #fff;
            font: inherit;
            font-weight: 900;
            cursor: pointer;
        }

        button:hover {
            background: var(--accent-dark);
        }

        button:disabled {
            cursor: wait;
            opacity: 0.65;
        }

        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        #formStatus {
            color: var(--muted);
            font-size: 13px;
            overflow-wrap: anywhere;
        }

        #formStatus.error {
            color: var(--error);
            font-weight: 800;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            padding: 20px;
        }

        .stat {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 14px;
            background: #fbfcfa;
        }

        .stat span {
            color: var(--muted);
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .stat strong {
            display: block;
            margin-top: 7px;
            font-size: 26px;
        }

        .records {
            border-top: 1px solid var(--line);
        }

        .record {
            display: grid;
            gap: 8px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--line);
        }

        .record:last-child {
            border-bottom: 0;
        }

        .record-top {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
        }

        .record strong,
        .record p {
            overflow-wrap: anywhere;
        }

        .record p {
            color: var(--muted);
            line-height: 1.45;
        }

        .chip {
            border-radius: 999px;
            padding: 5px 9px;
            background: rgba(165, 102, 31, 0.1);
            color: var(--warn);
            font-size: 12px;
            font-weight: 900;
            white-space: nowrap;
        }

        .empty {
            padding: 34px 20px;
            color: var(--muted);
            text-align: center;
        }

        @media (max-width: 920px) {
            header {
                align-items: flex-start;
                flex-direction: column;
                padding: 20px;
            }

            main {
                grid-template-columns: 1fr;
                padding: 18px;
            }

            form,
            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div>
            <h1>Strata Enquiry Desk</h1>
            <p>Laravel client intake, database-backed context, and n8n workflow submission</p>
        </div>
        <div class="status">{{ $n8nWebhookConfigured ? 'n8n webhook configured' : 'n8n webhook missing' }}</div>
    </header>

    <main>
        <section>
            <div class="panel-head">
                <p class="eyebrow">Client input</p>
                <h2>Submit an enquiry</h2>
            </div>

            <form id="enquiryForm">
                <label>
                    <span>Email Address</span>
                    <input name="client_email" type="email" autocomplete="email" required>
                </label>

                <label>
                    <span>Name</span>
                    <input name="client_name" type="text" autocomplete="name" required>
                </label>

                <label>
                    <span>Building Name</span>
                    <input name="building_name" type="text" autocomplete="organization">
                </label>

                <label>
                    <span>Building Size (units)</span>
                    <input name="building_size" type="number" min="0" step="1">
                </label>

                <label class="wide">
                    <span>Your Message</span>
                    <textarea name="message" rows="7" required></textarea>
                </label>

                <div class="form-actions">
                    <button type="submit" id="submitButton">Submit to n8n</button>
                    <p id="formStatus" role="status"></p>
                </div>
            </form>
        </section>

        <section>
            <div class="panel-head">
                <p class="eyebrow">Local database</p>
                <h2>Demo records</h2>
            </div>

            <div class="stats">
                <div class="stat"><span>Clients</span><strong>{{ $clientsCount }}</strong></div>
                <div class="stat"><span>Past cases</span><strong>{{ $casesCount }}</strong></div>
                <div class="stat"><span>Submissions</span><strong>{{ $enquiries->count() }}</strong></div>
            </div>

            <div class="records" id="records">
                @forelse ($enquiries as $enquiry)
                    <article class="record">
                        <div class="record-top">
                            <strong>{{ $enquiry->client_name }}</strong>
                            <span class="chip">{{ $enquiry->status }}</span>
                        </div>
                        <p>{{ $enquiry->message }}</p>
                    </article>
                @empty
                    <div class="empty">No submissions yet.</div>
                @endforelse
            </div>
        </section>
    </main>

    <script>
        const form = document.querySelector('#enquiryForm');
        const button = document.querySelector('#submitButton');
        const status = document.querySelector('#formStatus');

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            button.disabled = true;
            status.className = '';
            status.textContent = 'Submitting to n8n workflow';

            const payload = Object.fromEntries(new FormData(form).entries());
            if (payload.building_size === '') {
                delete payload.building_size;
            }

            try {
                const response = await fetch('/api/enquiries/submit', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.error || 'Submission failed');
                }

                form.reset();
                status.textContent = `Submitted to n8n. Local ID: ${data.enquiry_id}`;
            } catch (error) {
                status.textContent = error.message;
                status.className = 'error';
            } finally {
                button.disabled = false;
            }
        });
    </script>
</body>
</html>
