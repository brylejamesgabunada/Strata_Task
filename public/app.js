const state = {
  enquiries: [],
  selectedId: null,
  health: null
};

const demoInputs = [
  {
    client_email: "maria.santos@email.com",
    client_name: "Maria Santos",
    building_name: "22 Harbour Street",
    building_size: 72,
    message: "Hi, I am unhappy that the noise issue from the unit above me is still happening every night after 10pm. This has been going on for weeks and nothing has been done."
  },
  {
    client_email: "newchair@example.com",
    client_name: "Amelia Watson",
    building_name: "Surry Hills Residences",
    building_size: 48,
    message: "Hi, our committee is looking to change strata managers before our current contract renews in three months. What does your service include and how do we make the switch?"
  },
  {
    client_email: "elena.reyes@gmail.com",
    client_name: "Elena Reyes",
    building_name: "55 Park Avenue",
    building_size: 91,
    message: "I recently purchased Lot 22 and still have not received my owner portal login details. Can someone help me get access?"
  }
];

const form = document.querySelector("#enquiryForm");
const submitButton = document.querySelector("#submitButton");
const formStatus = document.querySelector("#formStatus");
const queueList = document.querySelector("#queueList");
const detailPanel = document.querySelector("#detailPanel");
const statsGrid = document.querySelector("#statsGrid");
const healthPill = document.querySelector("#healthPill");
const statusFilter = document.querySelector("#statusFilter");
const categoryFilter = document.querySelector("#categoryFilter");
const searchFilter = document.querySelector("#searchFilter");

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function formatDate(value) {
  if (!value) return "Not recorded";
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short"
  }).format(new Date(value));
}

function chipClass(value) {
  return String(value || "").toLowerCase().replace(/[^a-z0-9]+/g, "_").replace(/^_|_$/g, "");
}

function selectedRecord() {
  return state.enquiries.find((record) => record.id === state.selectedId) || state.enquiries[0] || null;
}

function filteredRecords() {
  const status = statusFilter.value;
  const category = categoryFilter.value;
  const search = searchFilter.value.trim().toLowerCase();

  return state.enquiries.filter((record) => {
    const analysis = record.analysis || {};
    const client = record.client_info || {};
    const matchesStatus = status === "all" || record.status === status;
    const matchesCategory = category === "all" || analysis.category === category;
    const searchText = [
      record.id,
      record.notification,
      analysis.summary,
      analysis.category,
      client.client_name,
      client.client_email,
      client.building_name
    ].join(" ").toLowerCase();
    const matchesSearch = !search || searchText.includes(search);

    return matchesStatus && matchesCategory && matchesSearch;
  });
}

async function requestJson(url, options = {}) {
  const response = await fetch(url, {
    headers: { "Content-Type": "application/json", ...(options.headers || {}) },
    ...options
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(payload.error || `Request failed with status ${response.status}`);
  }
  return payload;
}

async function loadHealth() {
  try {
    state.health = await requestJson("/api/health");
    healthPill.textContent = state.health.n8n_webhook_configured ? "n8n connected" : "Backend online";
    healthPill.className = "health-pill ready";
  } catch (error) {
    healthPill.textContent = "Service offline";
    healthPill.className = "health-pill warn";
  }
}

async function loadEnquiries(selectFirst = false) {
  const payload = await requestJson("/api/enquiries");
  state.enquiries = payload.enquiries || [];

  if (selectFirst || !state.enquiries.some((record) => record.id === state.selectedId)) {
    state.selectedId = state.enquiries[0]?.id || null;
  }

  render();
}

function renderStats() {
  const total = state.enquiries.length;
  const escalated = state.enquiries.filter((record) => record.status === "ESCALATED").length;
  const high = state.enquiries.filter((record) => record.analysis?.urgency === "High").length;
  const existing = state.enquiries.filter((record) => record.analysis?.client_type === "Existing Client").length;

  statsGrid.innerHTML = [
    ["Total", total],
    ["Escalated", escalated],
    ["High urgency", high],
    ["Existing clients", existing]
  ].map(([label, value]) => `
    <div class="stat">
      <span>${escapeHtml(label)}</span>
      <strong>${escapeHtml(value)}</strong>
    </div>
  `).join("");
}

function renderQueue() {
  const records = filteredRecords();

  if (!records.length) {
    queueList.innerHTML = `<div class="empty-state">No enquiries match the current filters.</div>`;
    return;
  }

  queueList.innerHTML = records.map((record) => {
    const analysis = record.analysis || {};
    const client = record.client_info || {};
    const active = record.id === state.selectedId ? " active" : "";

    return `
      <button type="button" class="queue-item${active}" data-id="${escapeHtml(record.id)}">
        <div class="queue-title">
          <strong>${escapeHtml(client.client_name || "Unknown client")}</strong>
          <span class="status-chip ${chipClass(record.status)}">${escapeHtml(record.status)}</span>
        </div>
        <p class="queue-summary">${escapeHtml(analysis.summary || record.notification)}</p>
        <div class="meta-line">
          <span class="urgency-chip ${chipClass(analysis.urgency)}">${escapeHtml(analysis.urgency || "Unscored")}</span>
          <span>${escapeHtml(analysis.category || "Unclassified")}</span>
          <span>${escapeHtml(formatDate(record.submitted_at))}</span>
        </div>
      </button>
    `;
  }).join("");
}

function renderDetail() {
  const record = selectedRecord();

  if (!record) {
    detailPanel.innerHTML = `<div class="empty-state">Submit an enquiry to populate the staff queue.</div>`;
    return;
  }

  const analysis = record.analysis || {};
  const client = record.client_info || {};
  const context = client.client_context || {};
  const actions = analysis.recommended_actions || [];
  const cases = client.similar_cases || [];
  const historical = analysis.historical_reference || {};
  const lots = context.lots || context.profile?.lots || [];

  detailPanel.innerHTML = `
    <div class="detail-top">
      <div>
        <h3>${escapeHtml(analysis.category || "Enquiry")}</h3>
        <p>${escapeHtml(record.id)} · ${escapeHtml(formatDate(record.submitted_at))}</p>
      </div>
      <div class="meta-line">
        <span class="status-chip ${chipClass(record.status)}">${escapeHtml(record.status)}</span>
        <span class="urgency-chip ${chipClass(analysis.urgency)}">${escapeHtml(analysis.urgency)}</span>
      </div>
    </div>

    <div class="detail-section">
      <h4>Client</h4>
      <div class="detail-grid">
        <div class="kv"><span>Name</span><strong>${escapeHtml(client.client_name)}</strong></div>
        <div class="kv"><span>Email</span><strong>${escapeHtml(client.client_email)}</strong></div>
        <div class="kv"><span>Type</span><strong>${escapeHtml(analysis.client_type)}</strong></div>
        <div class="kv"><span>Assigned</span><strong>${escapeHtml(record.assigned_to)}</strong></div>
        <div class="kv"><span>Building</span><strong>${escapeHtml(client.building_name || "Not provided")}</strong></div>
        <div class="kv"><span>Confidence</span><strong>${escapeHtml(analysis.confidence)}%</strong></div>
      </div>
    </div>

    <div class="detail-section">
      <h4>Message</h4>
      <p>${escapeHtml(client.inquiry_message)}</p>
    </div>

    <div class="detail-section">
      <h4>Recommended actions</h4>
      <ul>${actions.map((action) => `<li>${escapeHtml(action)}</li>`).join("")}</ul>
    </div>

    <div class="detail-section">
      <h4>Suggested response</h4>
      <p>${escapeHtml(analysis.suggested_response)}</p>
    </div>

    <div class="detail-section">
      <h4>Historical reference</h4>
      <div class="detail-grid">
        <div class="kv"><span>Similar case</span><strong>${escapeHtml(historical.similar_case_id || cases[0]?.inquiry_id || "None")}</strong></div>
        <div class="kv"><span>Previous resolution</span><strong>${escapeHtml(historical.previous_resolution || cases[0]?.previous_resolution || "None recorded")}</strong></div>
      </div>
    </div>

    <div class="detail-section">
      <h4>Client context</h4>
      <div class="detail-grid">
        <div class="kv"><span>Client ID</span><strong>${escapeHtml(context.client_id || "New prospect")}</strong></div>
        <div class="kv"><span>Consultant</span><strong>${escapeHtml(context.assigned_consultant || "Unassigned")}</strong></div>
        <div class="kv"><span>Open requests</span><strong>${escapeHtml(context.open_requests || 0)}</strong></div>
        <div class="kv"><span>Levy status</span><strong>${escapeHtml(context.levy_status || "n/a")}</strong></div>
      </div>
      ${lots.length ? `<p class="meta-line">${escapeHtml(lots.map((lot) => `Lot ${lot.lot_number} at ${lot.building}`).join("; "))}</p>` : ""}
    </div>

    <div class="detail-section">
      <h4>Staff tools</h4>
      <div class="staff-tools">
        <label>
          <span>Status</span>
          <select id="detailStatus">
            ${["ESCALATED", "PROCESSED", "IN_REVIEW", "CLOSED"].map((status) => `
              <option value="${status}" ${record.status === status ? "selected" : ""}>${status}</option>
            `).join("")}
          </select>
        </label>
        <label>
          <span>Staff notes</span>
          <textarea id="staffNotes">${escapeHtml(record.staff_notes || "")}</textarea>
        </label>
        <div class="staff-tool-row">
          <button class="primary-button" type="button" id="saveStaffTools">Save update</button>
          <span class="source-chip">${escapeHtml(analysis.analysis_provider || "unknown")}</span>
        </div>
      </div>
    </div>
  `;
}

function render() {
  renderStats();
  renderQueue();
  renderDetail();
}

function formDataToJson(formElement) {
  const data = new FormData(formElement);
  return Object.fromEntries(data.entries());
}

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  submitButton.disabled = true;
  formStatus.className = "form-status";
  formStatus.textContent = state.health?.n8n_webhook_configured ? "Submitting to n8n" : "Analysing enquiry";

  try {
    if (state.health?.n8n_webhook_configured) {
      await requestJson("/api/n8n/submit", {
        method: "POST",
        body: JSON.stringify(formDataToJson(form))
      });
      form.reset();
      formStatus.textContent = "Submitted to n8n workflow";
      return;
    }

    const record = await requestJson("/api/enquiries", {
      method: "POST",
      body: JSON.stringify(formDataToJson(form))
    });
    state.selectedId = record.id;
    formStatus.textContent = record.status === "ESCALATED" ? "Escalated for human review" : "Processed into staff queue";
    await loadEnquiries();
  } catch (error) {
    formStatus.textContent = error.message;
    formStatus.className = "form-status error";
  } finally {
    submitButton.disabled = false;
  }
});

document.querySelector("#demoButton").addEventListener("click", () => {
  const demo = demoInputs[Math.floor(Math.random() * demoInputs.length)];
  Object.entries(demo).forEach(([name, value]) => {
    const field = form.elements[name];
    if (field) field.value = value;
  });
  formStatus.textContent = "";
  formStatus.className = "form-status";
});

document.querySelector("#refreshButton").addEventListener("click", () => {
  loadEnquiries(true).catch((error) => {
    formStatus.textContent = error.message;
    formStatus.className = "form-status error";
  });
});

queueList.addEventListener("click", (event) => {
  const item = event.target.closest(".queue-item");
  if (!item) return;
  state.selectedId = item.dataset.id;
  render();
});

detailPanel.addEventListener("click", async (event) => {
  if (event.target.id !== "saveStaffTools") return;
  const record = selectedRecord();
  if (!record) return;

  event.target.disabled = true;
  try {
    const updated = await requestJson(`/api/enquiries/${encodeURIComponent(record.id)}`, {
      method: "PATCH",
      body: JSON.stringify({
        status: document.querySelector("#detailStatus").value,
        staff_notes: document.querySelector("#staffNotes").value
      })
    });

    state.enquiries = state.enquiries.map((item) => item.id === updated.id ? updated : item);
    render();
  } catch (error) {
    alert(error.message);
  } finally {
    event.target.disabled = false;
  }
});

[statusFilter, categoryFilter, searchFilter].forEach((control) => {
  control.addEventListener("input", render);
});

Promise.all([loadHealth(), loadEnquiries(true)]).catch((error) => {
  formStatus.textContent = error.message;
  formStatus.className = "form-status error";
});
