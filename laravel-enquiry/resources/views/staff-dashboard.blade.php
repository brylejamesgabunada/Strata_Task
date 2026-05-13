<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Dashboard | Strata Enquiry Desk</title>
    <style>
        :root {
            --bg: #f5f6f3;
            --panel: #ffffff;
            --ink: #202a25;
            --muted: #68746f;
            --line: #d9dfd6;
            --accent: #176b5d;
            --accent-dark: #104d43;
            --warn: #a5661f;
            --danger: #b9403a;
            --ok: #2f7d4d;
            --shadow: 0 18px 45px rgba(31, 42, 36, 0.08);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: var(--ink);
            background:
                linear-gradient(135deg, rgba(23, 107, 93, 0.07), transparent 35%),
                radial-gradient(circle at 78% 12%, rgba(165, 102, 31, 0.1), transparent 25%),
                var(--bg);
            font-family: "Avenir Next", "Segoe UI", sans-serif;
            letter-spacing: 0;
        }

        header {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: center;
            padding: 24px 32px;
            background: rgba(255, 255, 255, 0.82);
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(12px);
        }

        h1, h2, h3, p { margin: 0; }
        h1 { font-family: Georgia, serif; font-size: 28px; line-height: 1.1; }
        header p, .live-status { margin-top: 6px; color: var(--muted); font-size: 14px; }

        .nav { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .nav a, .live-pill {
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

        main { max-width: 1500px; margin: 0 auto; padding: 24px 32px 42px; }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .stat, .queue-item, .detail {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel);
            box-shadow: var(--shadow);
        }

        .stat { padding: 14px; }
        .stat span, .kv span {
            color: var(--muted);
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
        }
        .stat strong { display: block; margin-top: 6px; font-size: 26px; }

        .workspace {
            display: grid;
            grid-template-columns: minmax(310px, 390px) minmax(0, 1fr);
            gap: 16px;
            align-items: start;
        }

        .queue { display: grid; gap: 10px; }
        .queue-item {
            display: grid;
            gap: 9px;
            padding: 14px;
            cursor: pointer;
            transition: border-color 160ms ease, background 160ms ease, transform 160ms ease;
        }
        .queue-item:hover, .queue-item.active {
            border-color: rgba(23, 107, 93, 0.42);
            background: #fbfdfb;
        }
        .queue-item.is-new { transform: translateY(-2px); border-color: rgba(47, 125, 77, 0.45); }

        .queue-top, .detail-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }

        .queue h3 { font-size: 15px; overflow-wrap: anywhere; }
        .queue p, .detail-meta {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .chip {
            border-radius: 999px;
            padding: 5px 9px;
            background: #f4f7f5;
            border: 1px solid var(--line);
            color: var(--muted);
            font-size: 12px;
            font-weight: 900;
            white-space: nowrap;
        }
        .chip.high, .chip.critical, .chip.escalated, .chip.review { background: rgba(185, 64, 58, 0.1); color: var(--danger); border-color: rgba(185, 64, 58, 0.25); }
        .chip.medium { background: rgba(165, 102, 31, 0.1); color: var(--warn); border-color: rgba(165, 102, 31, 0.25); }
        .chip.low, .chip.processed, .chip.submitted-to-n8n { background: rgba(47, 125, 77, 0.1); color: var(--ok); border-color: rgba(47, 125, 77, 0.25); }

        .detail { min-width: 0; padding: 20px; }
        .detail h2 { font-size: 22px; overflow-wrap: anywhere; }
        .detail-meta { margin-top: 6px; }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-top: 16px;
        }

        .kv { border-left: 3px solid var(--line); padding-left: 10px; }
        .kv strong { display: block; margin-top: 3px; overflow-wrap: anywhere; }

        .section { border-top: 1px solid var(--line); margin-top: 18px; padding-top: 18px; }
        .section h3 { margin-bottom: 10px; color: #35433c; font-size: 14px; text-transform: uppercase; }
        .section p, .section li, textarea { line-height: 1.55; overflow-wrap: anywhere; }
        ul { margin: 0; padding-left: 20px; }
        li + li { margin-top: 7px; }

        textarea {
            width: 100%;
            min-height: 170px;
            border: 1px solid #cbd4ca;
            border-radius: 8px;
            padding: 12px;
            color: var(--ink);
            font: inherit;
            resize: vertical;
        }

        .empty {
            padding: 28px;
            border: 1px dashed var(--line);
            border-radius: 8px;
            color: var(--muted);
            text-align: center;
        }

        @media (max-width: 980px) {
            header { align-items: flex-start; flex-direction: column; padding: 20px; }
            main { padding: 18px; }
            .stats, .workspace, .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header>
        <div>
            <h1>Staff Review Dashboard</h1>
            <p>Review AI classification, suggested response, and recommended staff action</p>
            <p class="live-status" id="liveStatus">Live updates enabled</p>
        </div>
        <nav class="nav">
            <span class="live-pill">Polling every 5s</span>
            <a href="{{ route('client.form') }}">Client form</a>
        </nav>
    </header>

    <main>
        {{-- Dashboard totals are rendered from the same payload used by the enquiry queue below. --}}
        <div class="stats">
            <div class="stat"><span>Total</span><strong id="totalCount">0</strong></div>
            <div class="stat"><span>Processed</span><strong id="processedCount">0</strong></div>
            <div class="stat"><span>Escalated</span><strong id="escalatedCount">0</strong></div>
            <div class="stat"><span>Human review</span><strong id="reviewCount">0</strong></div>
        </div>

        <div class="empty" id="emptyState" hidden>No processed enquiries are ready for staff review yet.</div>

        {{-- The left queue lists submissions; the right detail pane shows the selected AI recommendation. --}}
        <div class="workspace" id="workspace">
            <aside class="queue" id="queue" aria-label="Enquiry queue"></aside>
            <section class="detail" id="detail"></section>
        </div>
    </main>

    <script>
        // DashboardController injects the first payload during page render so staff see data immediately.
        const initialDashboard = @json($dashboard);
        const queue = document.querySelector('#queue');
        const detail = document.querySelector('#detail');
        const workspace = document.querySelector('#workspace');
        const emptyState = document.querySelector('#emptyState');
        const liveStatus = document.querySelector('#liveStatus');
        const stats = {
            totalCount: document.querySelector('#totalCount'),
            processedCount: document.querySelector('#processedCount'),
            escalatedCount: document.querySelector('#escalatedCount'),
            reviewCount: document.querySelector('#reviewCount'),
        };

        let selectedId = null;
        let latestId = initialDashboard.latestId || null;

        // All rendered fields are escaped because they can contain client input or AI-generated text.
        function escapeHtml(value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function chip(label, className = '') {
            return `<span class="chip ${escapeHtml(className)}">${escapeHtml(label)}</span>`;
        }

        // Render the clickable enquiry queue. Selecting an item redraws the detail pane below.
        function renderQueue(items, highlightId = null) {
            queue.innerHTML = items.map((item, index) => {
                const active = item.id === selectedId || (!selectedId && index === 0);
                const review = item.requires_human_review ? chip('Review', 'review') : '';

                return `
                    <article class="queue-item ${active ? 'active' : ''} ${item.id === highlightId ? 'is-new' : ''}" data-id="${escapeHtml(item.id)}">
                        <div class="queue-top">
                            <h3>${escapeHtml(item.client_name || 'Unknown client')}</h3>
                            ${chip(item.workflow_status || 'Pending', item.workflow_status_class || '')}
                        </div>
                        <p>${escapeHtml(item.summary || 'n8n response has not been received yet.')}</p>
                        <div class="chips">
                            ${chip(item.urgency || 'Pending', item.urgency_class || '')}
                            ${chip(item.category || 'Pending')}
                            ${review}
                        </div>
                    </article>
                `;
            }).join('');

            queue.querySelectorAll('.queue-item').forEach((item) => {
                item.addEventListener('click', () => {
                    selectedId = item.dataset.id;
                    renderDashboard(window.currentDashboard);
                });
            });
        }

        // Render the staff review view: classification, context, recommended actions, and response draft.
        function renderDetail(item) {
            if (!item) {
                detail.innerHTML = '<div class="empty">Select an enquiry to review details.</div>';
                return;
            }

            const actions = Array.isArray(item.recommended_actions) && item.recommended_actions.length
                ? `<ul>${item.recommended_actions.map((action) => `<li>${escapeHtml(action)}</li>`).join('')}</ul>`
                : '<p>No recommended actions returned yet.</p>';

            const integration = item.integration_problem
                ? '<div class="section"><h3>Integration Issue</h3><p>This record came from a workflow run where n8n received HTML/error content instead of valid JSON. Treat it as a failed integration test, not a client recommendation.</p></div>'
                : '';

            const error = item.error_message
                ? `<div class="section"><h3>Integration Note</h3><p>${escapeHtml(item.error_message)}</p></div>`
                : '';

            detail.innerHTML = `
                <div class="detail-top">
                    <div>
                        <h2>${escapeHtml(item.category || 'Pending')} from ${escapeHtml(item.client_name || 'Unknown client')}</h2>
                        <p class="detail-meta">${escapeHtml(item.client_email || 'No email')} · ${escapeHtml(item.created_at_label || '')}</p>
                    </div>
                    <div class="chips">
                        ${chip(item.workflow_status || 'Pending', item.workflow_status_class || '')}
                        ${chip(item.urgency || 'Pending', item.urgency_class || '')}
                    </div>
                </div>

                <div class="grid">
                    <div class="kv"><span>Client type</span><strong>${escapeHtml(item.client_type || 'Unclassified')}</strong></div>
                    <div class="kv"><span>Confidence</span><strong>${item.confidence !== null && item.confidence !== undefined ? `${escapeHtml(item.confidence)}%` : 'Pending'}</strong></div>
                    <div class="kv"><span>Assigned</span><strong>${escapeHtml(item.assigned_consultant || 'Unassigned')}</strong></div>
                    <div class="kv"><span>Building</span><strong>${escapeHtml(item.building_name || 'Not provided')}</strong></div>
                    <div class="kv"><span>Units</span><strong>${escapeHtml(item.building_size || 'Not provided')}</strong></div>
                    <div class="kv"><span>Local status</span><strong>${escapeHtml(item.local_status || 'pending')}</strong></div>
                </div>

                <div class="section"><h3>Summary</h3><p>${escapeHtml(item.summary || 'n8n response has not been received yet.')}</p></div>
                <div class="section"><h3>Client Message</h3><p>${escapeHtml(item.message || 'No message captured.')}</p></div>
                <div class="section"><h3>Recommended Actions</h3>${actions}</div>
                <div class="section"><h3>Suggested Response</h3><textarea readonly>${escapeHtml(item.suggested_response || 'No suggested response returned yet.')}</textarea></div>
                <div class="section"><h3>Historical Reference</h3><p>Similar case: ${escapeHtml(item.historical_reference?.similar_case_id || 'n/a')} · Previous resolution: ${escapeHtml(item.historical_reference?.previous_resolution || 'n/a')}</p></div>
                ${integration}
                ${error}
            `;
        }

        // Apply a fresh staff payload to the whole dashboard while keeping the selected enquiry stable.
        function renderDashboard(data, highlightId = null) {
            window.currentDashboard = data;
            const items = data.enquiries || [];

            stats.totalCount.textContent = data.totalCount || 0;
            stats.processedCount.textContent = data.processedCount || 0;
            stats.escalatedCount.textContent = data.escalatedCount || 0;
            stats.reviewCount.textContent = data.reviewCount || 0;

            emptyState.hidden = items.length > 0;
            workspace.hidden = items.length === 0;

            if (!items.length) {
                selectedId = null;
                queue.innerHTML = '';
                detail.innerHTML = '';
                return;
            }

            if (!selectedId || !items.some((item) => item.id === selectedId)) {
                selectedId = items[0].id;
            }

            renderQueue(items, highlightId);
            renderDetail(items.find((item) => item.id === selectedId));
        }

        // Poll Laravel for near-realtime updates. This avoids a full WebSocket setup for the demo.
        async function refreshDashboard() {
            try {
                const response = await fetch('/api/enquiries/staff', {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });

                if (!response.ok) {
                    throw new Error('Unable to refresh staff dashboard');
                }

                const data = await response.json();
                const newLatestId = data.latestId || null;
                const hasNewItem = latestId && newLatestId && latestId !== newLatestId;
                latestId = newLatestId;

                renderDashboard(data, hasNewItem ? newLatestId : null);
                liveStatus.textContent = hasNewItem
                    ? 'New inquiry received'
                    : `Last checked ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
            } catch (error) {
                liveStatus.textContent = error.message;
            }
        }

        // Initial render uses the server-provided data; later renders come from AJAX polling.
        renderDashboard(initialDashboard);
        setInterval(refreshDashboard, 5000);
    </script>
</body>
</html>
