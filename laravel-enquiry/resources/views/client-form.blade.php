<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Client Enquiry | Strata Enquiry Desk</title>
    <style>
        :root {
            --bg: #f4f6f1;
            --panel: #ffffff;
            --ink: #1f2a24;
            --muted: #68746f;
            --line: #d9dfd6;
            --accent: #176b5d;
            --accent-dark: #104d43;
            --success: #2f7d4d;
            --warn: #a5661f;
            --error: #b9403a;
            --shadow: 0 22px 60px rgba(31, 42, 36, 0.1);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: var(--ink);
            background:
                linear-gradient(135deg, rgba(23, 107, 93, 0.08), transparent 34%),
                radial-gradient(circle at 82% 16%, rgba(165, 102, 31, 0.12), transparent 25%),
                var(--bg);
            font-family: "Avenir Next", "Segoe UI", sans-serif;
            letter-spacing: 0;
        }

        header {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: center;
            padding: 26px 34px;
            background: rgba(255, 255, 255, 0.78);
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(12px);
        }

        h1, h2, p { margin: 0; }
        h1 { font-family: Georgia, serif; font-size: 28px; line-height: 1.1; }
        header p { margin-top: 6px; color: var(--muted); font-size: 14px; }

        .nav a {
            border: 1px solid rgba(23, 107, 93, 0.25);
            border-radius: 999px;
            padding: 8px 12px;
            background: rgba(23, 107, 93, 0.08);
            color: var(--accent-dark);
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
        }

        main {
            max-width: 760px;
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

        h2 { font-size: 20px; }

        form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            padding: 20px;
        }

        label { display: grid; gap: 7px; min-width: 0; }
        label span { color: #3d4944; font-size: 13px; font-weight: 800; }
        .wide { grid-column: 1 / -1; }

        input, textarea {
            width: 100%;
            min-width: 0;
            border: 1px solid #cbd4ca;
            border-radius: 6px;
            padding: 11px 12px;
            font: inherit;
            outline: none;
        }

        textarea { resize: vertical; }
        input:focus, textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(23, 107, 93, 0.13); }

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

        button:hover { background: var(--accent-dark); }
        button:disabled { cursor: wait; opacity: 0.65; }

        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        #formStatus {
            grid-column: 1 / -1;
            display: none;
            align-items: flex-start;
            gap: 12px;
            width: 100%;
            border: 1px solid rgba(47, 125, 77, 0.24);
            border-radius: 8px;
            padding: 14px;
            background: rgba(47, 125, 77, 0.08);
            color: var(--success);
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        #formStatus.visible { display: flex; }
        #formStatus.error { color: var(--error); font-weight: 800; }

        .status-icon {
            display: grid;
            flex: 0 0 auto;
            place-items: center;
            width: 28px;
            height: 28px;
            border-radius: 999px;
            background: var(--success);
            color: #fff;
            font-weight: 900;
            line-height: 1;
        }

        .status-copy strong {
            display: block;
            margin-bottom: 3px;
            color: var(--success);
            font-size: 15px;
        }

        .status-copy span {
            display: block;
            color: #52615b;
        }

        @media (max-width: 900px) {
            header { align-items: flex-start; flex-direction: column; padding: 20px; }
            main { padding: 18px; }
            form { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header>
        <div>
            <h1>Strata Enquiry Desk</h1>
            <p>Client intake connected to the n8n workflow</p>
        </div>
        <nav class="nav">
            <a href="{{ route('staff.dashboard') }}">Staff dashboard</a>
        </nav>
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
                    <button type="submit" id="submitButton">Submit enquiry</button>
                </div>

                <div id="formStatus" role="status" aria-live="polite"></div>
            </form>
        </section>
    </main>

    <script>
        const form = document.querySelector('#enquiryForm');
        const button = document.querySelector('#submitButton');
        const status = document.querySelector('#formStatus');

        function escapeHtml(value) {
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            button.disabled = true;
            status.className = 'visible';
            status.innerHTML = '<span class="status-icon">...</span><span class="status-copy"><strong>Submitting enquiry</strong><span>Sending your enquiry to the review workflow.</span></span>';

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

                const contentType = response.headers.get('content-type') || '';
                const data = contentType.includes('application/json')
                    ? await response.json()
                    : { error: await response.text() };

                if (!response.ok) {
                    const workflowError = data.error
                        || data.n8n_response?.message
                        || data.n8n_response?.hint
                        || 'Submission failed';

                    throw new Error(workflowError);
                }

                form.reset();
                const message = data.pending_n8n_response
                    ? `Your enquiry was saved. Staff can review it once the workflow response is available. Reference: ${data.enquiry_id}`
                    : `Your enquiry was submitted successfully. Staff dashboard reference: ${data.enquiry_id}`;

                status.className = 'visible';
                status.innerHTML = `<span class="status-icon">&#10003;</span><span class="status-copy"><strong>Submission received</strong><span>${escapeHtml(message)}</span></span>`;
            } catch (error) {
                status.className = 'visible error';
                status.innerHTML = `<span class="status-icon">!</span><span class="status-copy"><strong>Submission needs attention</strong><span>${escapeHtml(error.message)}</span></span>`;
            } finally {
                button.disabled = false;
            }
        });
    </script>
</body>
</html>
