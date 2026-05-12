const http = require("http");
const fs = require("fs/promises");
const path = require("path");
const crypto = require("crypto");

const HOST = process.env.HOST || "127.0.0.1";
const REQUESTED_PORT = process.env.PORT == null ? 0 : Number(process.env.PORT);
const ROOT_DIR = __dirname;
const PUBLIC_DIR = path.join(ROOT_DIR, "public");
const CLIENT_DB_PATH = path.join(ROOT_DIR, "mock_client_database.json");
const RAG_DATA_PATH = path.join(ROOT_DIR, "rag_seed_data.json");
const ENQUIRY_STORE_PATH = path.join(ROOT_DIR, "data", "enquiries.json");
const N8N_WEBHOOK_URL = process.env.N8N_WEBHOOK_URL || "";

const MIME_TYPES = {
  ".html": "text/html; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".json": "application/json; charset=utf-8",
  ".svg": "image/svg+xml",
  ".png": "image/png",
  ".ico": "image/x-icon"
};

const STOP_WORDS = new Set([
  "about", "after", "again", "all", "and", "any", "are", "been", "but", "can",
  "could", "did", "for", "from", "get", "had", "has", "have", "her", "his",
  "how", "into", "its", "just", "lot", "not", "now", "our", "out", "over",
  "please", "she", "should", "that", "the", "their", "them", "there", "they",
  "this", "was", "what", "when", "who", "why", "will", "with", "would", "you",
  "your"
]);

function jsonResponse(res, status, body) {
  res.writeHead(status, {
    "Content-Type": "application/json; charset=utf-8",
    "Cache-Control": "no-store"
  });
  res.end(JSON.stringify(body, null, 2));
}

function textResponse(res, status, body) {
  res.writeHead(status, { "Content-Type": "text/plain; charset=utf-8" });
  res.end(body);
}

async function readJson(filePath, fallback) {
  try {
    const raw = await fs.readFile(filePath, "utf8");
    return JSON.parse(raw);
  } catch (error) {
    if (error.code === "ENOENT") return fallback;
    throw error;
  }
}

async function writeJson(filePath, data) {
  await fs.mkdir(path.dirname(filePath), { recursive: true });
  await fs.writeFile(filePath, `${JSON.stringify(data, null, 2)}\n`, "utf8");
}

async function parseRequestBody(req) {
  const chunks = [];
  let size = 0;

  for await (const chunk of req) {
    size += chunk.length;
    if (size > 1024 * 1024) {
      throw Object.assign(new Error("Request body too large"), { statusCode: 413 });
    }
    chunks.push(chunk);
  }

  if (chunks.length === 0) return {};
  const raw = Buffer.concat(chunks).toString("utf8");
  if (!raw.trim()) return {};

  try {
    return JSON.parse(raw);
  } catch {
    throw Object.assign(new Error("Request body must be valid JSON"), { statusCode: 400 });
  }
}

function normalizeEmail(email) {
  return String(email || "").trim().toLowerCase();
}

function normalizeText(value) {
  return String(value || "").replace(/\s+/g, " ").trim();
}

function tokenize(text) {
  return normalizeText(text)
    .toLowerCase()
    .replace(/[^a-z0-9\s-]/g, " ")
    .split(/\s+/)
    .filter((token) => token.length > 2 && !STOP_WORDS.has(token));
}

function containsAny(text, words) {
  const lower = String(text || "").toLowerCase();
  return words.some((word) => lower.includes(word));
}

function extractLabel(content, label) {
  const pattern = new RegExp(`${label}:\\s*([\\s\\S]*?)(?=\\n[A-Z][A-Z\\s()/-]*:|$)`, "i");
  const match = String(content || "").match(pattern);
  return match ? normalizeText(match[1]) : "";
}

function clamp(value, min, max) {
  return Math.min(max, Math.max(min, value));
}

function splitActionText(text) {
  const cleaned = normalizeText(text);
  if (!cleaned) return [];
  return cleaned
    .split(/(?:\.\s+|;\s+|\n+)/)
    .map((item) => item.replace(/\.$/, "").trim())
    .filter(Boolean)
    .slice(0, 4);
}

function formatCase(seedCase, score = 0) {
  const content = seedCase.pageContent || "";
  const categoryRaw = extractLabel(content, "ENQUIRY CATEGORY") || seedCase.metadata?.category || "General Question";
  const category = categoryRaw.split(/[—-]/)[0].trim();

  return {
    inquiry_id: seedCase.id,
    category,
    subcategory: seedCase.metadata?.subcategory || categoryRaw.split(/[—-]/)[1]?.trim() || "",
    urgency: extractLabel(content, "URGENCY") || seedCase.metadata?.urgency || "Medium",
    client_status: seedCase.metadata?.client_status || "",
    summary: extractLabel(content, "SUMMARY"),
    original_message: extractLabel(content, "ORIGINAL MESSAGE"),
    recommended_action: extractLabel(content, "RECOMMENDED ACTION"),
    suggested_response: extractLabel(content, "SUGGESTED RESPONSE"),
    previous_resolution: seedCase.metadata?.resolution || "",
    score
  };
}

async function lookupClientContext(email) {
  const clients = await readJson(CLIENT_DB_PATH, []);
  const normalizedEmail = normalizeEmail(email);
  const client = clients.find((record) => normalizeEmail(record.email) === normalizedEmail);

  if (!client) {
    return {
      client_exists: false,
      client_id: null,
      client_type: "New",
      assigned_consultant: "Business Development",
      past_inquiries_count: 0,
      active_projects: [],
      profile: null
    };
  }

  const isActive = client.status === "active";
  return {
    client_exists: isActive,
    client_id: client.client_id,
    client_type: isActive ? "Existing" : "Inactive Former",
    assigned_consultant: client.account_manager || "Unassigned",
    past_inquiries_count: Math.max(Number(client.open_requests || 0), 0),
    active_projects: Number(client.open_requests || 0) > 0 ? ["Open enquiry follow-up"] : [],
    open_requests: Number(client.open_requests || 0),
    levy_status: client.levy_status || "unknown",
    portal_access: Boolean(client.portal_access),
    lots: client.lots || [],
    profile: client
  };
}

async function searchSimilarCases(query, limit = 3) {
  const cases = await readJson(RAG_DATA_PATH, []);
  const queryTokens = new Set(tokenize(query));
  const queryLower = String(query || "").toLowerCase();

  return cases
    .map((seedCase) => {
      const content = `${seedCase.pageContent || ""} ${JSON.stringify(seedCase.metadata || {})}`;
      const caseTokens = new Set(tokenize(content));
      let score = 0;

      queryTokens.forEach((token) => {
        if (caseTokens.has(token)) score += 2;
      });

      const metadata = seedCase.metadata || {};
      [metadata.category, metadata.subcategory, metadata.building, metadata.location, metadata.lot_number]
        .filter(Boolean)
        .forEach((value) => {
          const normalized = String(value).toLowerCase();
          if (normalized && queryLower.includes(normalized)) score += 6;
        });

      if (containsAny(queryLower, ["unhappy", "unacceptable", "complaint", "nothing has been done"])) {
        if (metadata.category === "Complaint") score += 6;
      }

      if (containsAny(queryLower, ["leak", "water", "lift", "broken", "urgent", "emergency", "safety"])) {
        if (metadata.category === "Maintenance Request") score += 6;
      }

      if (containsAny(queryLower, ["levy", "invoice", "budget", "payment", "overdue"])) {
        if (metadata.category === "Financial Enquiry") score += 6;
      }

      if (containsAny(queryLower, ["by-law", "bylaw", "pet", "agm", "vote", "tribunal"])) {
        if (metadata.category === "Legal or Compliance Enquiry") score += 6;
      }

      if (containsAny(queryLower, ["change strata", "switch", "new manager", "service include", "proposal"])) {
        if (metadata.category === "New Client Enquiry") score += 6;
      }

      return formatCase(seedCase, score);
    })
    .sort((a, b) => b.score - a.score)
    .slice(0, clamp(Number(limit) || 3, 1, 5));
}

function categoryFromText(message, clientContext) {
  const text = String(message || "").toLowerCase();
  const negative = containsAny(text, [
    "angry", "complaint", "concerned", "delay", "dissatisfied", "frustrated",
    "ignored", "issue", "not happy", "nothing has been done", "problem",
    "unacceptable", "unhappy", "weeks"
  ]);

  if (negative) return "Complaint";
  if (!clientContext.client_exists) return "New Client";
  if (clientContext.active_projects?.length > 0 || clientContext.open_requests > 0) return "Support Request";

  const support = containsAny(text, [
    "access", "account", "document", "help", "login", "portal", "request",
    "update", "where is"
  ]);

  return support ? "Support Request" : "General Question";
}

function urgencyFromMessage(message, category, clientContext) {
  const text = String(message || "").toLowerCase();
  const urgent = containsAny(text, [
    "asap", "broken", "danger", "emergency", "immediately", "legal deadline",
    "leak", "safety", "serious", "urgent", "water"
  ]);

  if (urgent || category === "Complaint") return "High";
  if (category === "Support Request" || clientContext.open_requests > 0) return "Medium";
  return "Low";
}

function recommendedActionsFor(category, urgency, clientContext, similarCases) {
  const similarAction = splitActionText(similarCases[0]?.recommended_action || "");
  if (similarAction.length >= 2) return similarAction.slice(0, 4);

  const owner = clientContext.assigned_consultant || "assigned staff";

  if (category === "Complaint") {
    return [
      `Escalate to ${owner} or a senior consultant for same-day review`,
      "Review related open requests and prior correspondence",
      "Send a service recovery response within one business day",
      "Log follow-up ownership and target resolution date in the staff queue"
    ];
  }

  if (category === "Support Request") {
    return [
      `Assign to ${owner}`,
      "Verify the client profile and any open requests",
      "Respond with the next action and expected turnaround",
      "Update the enquiry record after staff action is complete"
    ];
  }

  if (category === "New Client") {
    return [
      "Assign to Business Development",
      "Send the new client information pack and request key building documents",
      "Schedule a discovery call within two business days",
      "Prepare a proposal once building details are confirmed"
    ];
  }

  return [
    "Assign to the general enquiries queue",
    "Confirm whether the sender is an owner, tenant, committee member, or prospect",
    "Provide a concise answer or route to the appropriate team",
    urgency === "High" ? "Add a same-day follow-up reminder" : "Close the loop within the standard SLA"
  ];
}

function suggestedResponseFor(input, category, urgency, clientContext, similarCases) {
  const name = input.client_name || clientContext.profile?.full_name || "there";
  const similarResponse = normalizeText(similarCases[0]?.suggested_response || "");

  if (similarResponse && category !== "General Question") {
    const adaptedResponse = similarResponse
      .replace(/^(Dear|Hi|Hello)\s+[^,]+,\s*/i, "")
      .trim();
    const sentence = adaptedResponse
      ? adaptedResponse.charAt(0).toLowerCase() + adaptedResponse.slice(1)
      : "thank you for reaching out. We will review your enquiry and respond with the next action.";
    return `Hi ${name}, ${sentence}`;
  }

  const building = input.building_name ? ` for ${input.building_name}` : "";

  if (category === "Complaint") {
    return `Hi ${name}, thank you for raising this with us${building}. I understand the concern and have flagged it for priority review so the team can check the history, confirm the next action, and respond with a clear plan. We will keep the enquiry open until the follow-up action is confirmed.`;
  }

  if (category === "Support Request") {
    return `Hi ${name}, thank you for your message${building}. We will verify the details against your client profile and have the assigned consultant follow up with the next step. You can expect a practical update once the relevant records have been checked.`;
  }

  if (category === "New Client") {
    return `Hi ${name}, thank you for contacting us${building}. We would be happy to learn more about the building and what you need from a strata consultancy team. A business development team member will follow up to arrange a discovery call and share the relevant service information.`;
  }

  return `Hi ${name}, thank you for your enquiry${building}. We have received your message and will route it to the appropriate staff member for review. If any extra details are needed, the team will contact you before preparing the final response.`;
}

function routeFor(category, urgency, clientContext) {
  if (urgency === "High" && category === "Complaint") return "Director - Urgent";
  if (category === "New Client") return "Business Development";
  if (category === "Support Request" && clientContext.assigned_consultant) {
    return `Account Manager: ${clientContext.assigned_consultant}`;
  }
  return "General Queue";
}

function buildFallbackAnalysis(input, clientContext, similarCases) {
  const category = categoryFromText(input.message, clientContext);
  const urgency = urgencyFromMessage(input.message, category, clientContext);
  const confidence = category === "Complaint" ? 90 : category === "New Client" ? 86 : category === "Support Request" ? 82 : 74;
  const recommendedActions = recommendedActionsFor(category, urgency, clientContext, similarCases);
  const historicalCase = similarCases[0] || null;

  return {
    client_type: clientContext.client_exists ? "Existing Client" : "New Client",
    category,
    urgency,
    confidence,
    summary: summarizeInquiry(input.message, category),
    recommended_actions: recommendedActions,
    historical_reference: historicalCase
      ? {
          similar_case_id: historicalCase.inquiry_id,
          previous_resolution: historicalCase.previous_resolution || "No resolution recorded"
        }
      : null,
    suggested_response: suggestedResponseFor(input, category, urgency, clientContext, similarCases),
    requires_human_review: confidence < 70 || urgency === "High",
    routing: routeFor(category, urgency, clientContext),
    analysis_provider: "local_preview_classifier"
  };
}

function summarizeInquiry(message, category) {
  const text = normalizeText(message);
  if (!text) return `${category} enquiry received.`;
  const sentence = text.split(/(?<=[.!?])\s+/)[0] || text;
  return sentence.length > 150 ? `${sentence.slice(0, 147).trim()}...` : sentence;
}

async function analyzeInquiry(input) {
  const clientContext = await lookupClientContext(input.client_email);
  const similarCases = await searchSimilarCases(input.message, 3);
  const analysis = buildFallbackAnalysis(input, clientContext, similarCases);

  return { clientContext, similarCases, analysis };
}

function validateEnquiryInput(body) {
  const input = {
    client_email: normalizeEmail(body.client_email || body.sender_email),
    client_name: normalizeText(body.client_name || body.sender_name),
    building_name: normalizeText(body.building_name),
    building_size: body.building_size === "" || body.building_size == null ? null : Number(body.building_size),
    message: normalizeText(body.message || body.inquiry_message),
    channel: normalizeText(body.channel || "web")
  };

  const errors = [];
  if (!input.client_email) errors.push("Email Address is required.");
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.client_email)) errors.push("Email Address must be valid.");
  if (!input.client_name) errors.push("Name is required.");
  if (!input.message) errors.push("Your Message is required.");
  if (input.building_size != null && (!Number.isFinite(input.building_size) || input.building_size < 0)) {
    errors.push("Building Size must be a positive number.");
  }

  if (errors.length) {
    throw Object.assign(new Error(errors.join(" ")), { statusCode: 400 });
  }

  return input;
}

function buildWorkflowRecord(input, clientContext, similarCases, analysis) {
  const status = analysis.urgency === "High" || analysis.requires_human_review ? "ESCALATED" : "PROCESSED";
  const assignedTo = status === "ESCALATED"
    ? (analysis.routing || clientContext.assigned_consultant || "Senior Consultant")
    : (analysis.routing || routeFor(analysis.category, analysis.urgency, clientContext));
  const id = `ENQ-${new Date().toISOString().slice(0, 10).replace(/-/g, "")}-${crypto.randomUUID().slice(0, 8).toUpperCase()}`;

  return {
    id,
    status,
    notification: status === "ESCALATED"
      ? `HIGH PRIORITY: ${analysis.category} from ${input.client_name}`
      : `${analysis.category} from ${input.client_name} - Auto-processed`,
    assigned_to: assignedTo,
    submitted_at: new Date().toISOString(),
    analysis,
    client_info: {
      inquiry_message: input.message,
      client_email: input.client_email,
      client_name: input.client_name,
      building_name: input.building_name,
      building_size: input.building_size,
      channel: input.channel,
      client_context: clientContext,
      similar_cases: similarCases
    },
    workflow_steps: [
      { name: "Strata Inquiry Form", status: "completed" },
      { name: "Lookup Client Context", status: "completed" },
      { name: "Retrieve Similar Cases (RAG)", status: "completed" },
      { name: "Classify & Analyze Inquiry", status: "completed", provider: analysis.analysis_provider },
      { name: "Check Urgency", status: "completed", route: status }
    ],
    staff_notes: ""
  };
}

async function appendEnquiry(record) {
  const records = await readJson(ENQUIRY_STORE_PATH, []);
  records.unshift(record);
  await writeJson(ENQUIRY_STORE_PATH, records);
  return records;
}

async function updateEnquiry(id, patch) {
  const records = await readJson(ENQUIRY_STORE_PATH, []);
  const index = records.findIndex((record) => record.id === id);
  if (index === -1) return null;

  const allowedStatus = new Set(["ESCALATED", "PROCESSED", "IN_REVIEW", "CLOSED"]);
  const existing = records[index];
  records[index] = {
    ...existing,
    status: allowedStatus.has(patch.status) ? patch.status : existing.status,
    assigned_to: patch.assigned_to ? normalizeText(patch.assigned_to) : existing.assigned_to,
    staff_notes: patch.staff_notes != null ? normalizeText(patch.staff_notes) : existing.staff_notes,
    updated_at: new Date().toISOString()
  };

  await writeJson(ENQUIRY_STORE_PATH, records);
  return records[index];
}

async function submitToN8n(input) {
  if (!N8N_WEBHOOK_URL) {
    throw Object.assign(new Error("N8N_WEBHOOK_URL is not configured."), { statusCode: 503 });
  }

  if (typeof fetch !== "function") {
    throw new Error("This Node.js runtime does not provide fetch; use Node 18 or newer.");
  }

  const response = await fetch(N8N_WEBHOOK_URL, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Accept": "application/json"
    },
    body: JSON.stringify(input)
  });

  const contentType = response.headers.get("content-type") || "";
  const payload = contentType.includes("application/json")
    ? await response.json()
    : { message: await response.text() };

  if (!response.ok) {
    throw Object.assign(new Error(payload.error || payload.message || `n8n webhook failed with ${response.status}`), {
      statusCode: response.status,
      n8nPayload: payload
    });
  }

  return {
    submitted: true,
    webhook_url: N8N_WEBHOOK_URL,
    n8n_response: payload
  };
}

async function serveStatic(req, res, url) {
  const requestedPath = decodeURIComponent(url.pathname === "/" ? "/index.html" : url.pathname);
  const filePath = path.normalize(path.join(PUBLIC_DIR, requestedPath));

  if (!filePath.startsWith(PUBLIC_DIR)) {
    textResponse(res, 403, "Forbidden");
    return;
  }

  try {
    const content = await fs.readFile(filePath);
    const extension = path.extname(filePath);
    res.writeHead(200, {
      "Content-Type": MIME_TYPES[extension] || "application/octet-stream",
      "Cache-Control": "no-store"
    });
    if (req.method === "HEAD") {
      res.end();
      return;
    }
    res.end(content);
  } catch (error) {
    if (error.code === "ENOENT") {
      textResponse(res, 404, "Not found");
      return;
    }
    throw error;
  }
}

async function router(req, res) {
  const url = new URL(req.url, `http://${req.headers.host || "localhost"}`);

  if (req.method === "GET" && url.pathname === "/api/health") {
    jsonResponse(res, 200, {
      ok: true,
      workflow: "Enquiry Classifier & Response Generator",
      role: "context-and-rag-backend",
      n8n_webhook_configured: Boolean(N8N_WEBHOOK_URL),
      storage: path.relative(ROOT_DIR, ENQUIRY_STORE_PATH)
    });
    return;
  }

  if (req.method === "GET" && url.pathname === "/api/client/context") {
    const context = await lookupClientContext(url.searchParams.get("email"));
    jsonResponse(res, 200, context);
    return;
  }

  if (req.method === "POST" && url.pathname === "/api/rag/search") {
    const body = await parseRequestBody(req);
    const similarCases = await searchSimilarCases(body.query || body.message || "", body.limit || 3);
    jsonResponse(res, 200, { similar_cases: similarCases });
    return;
  }

  if (req.method === "POST" && url.pathname === "/api/n8n/submit") {
    const body = await parseRequestBody(req);
    const input = validateEnquiryInput(body);
    const result = await submitToN8n(input);
    jsonResponse(res, 200, result);
    return;
  }

  if (req.method === "GET" && url.pathname === "/api/enquiries") {
    const records = await readJson(ENQUIRY_STORE_PATH, []);
    jsonResponse(res, 200, { enquiries: records });
    return;
  }

  if (req.method === "POST" && url.pathname === "/api/enquiries") {
    const body = await parseRequestBody(req);
    const input = validateEnquiryInput(body);
    const { clientContext, similarCases, analysis } = await analyzeInquiry(input);
    const record = buildWorkflowRecord(input, clientContext, similarCases, analysis);
    await appendEnquiry(record);
    jsonResponse(res, 201, record);
    return;
  }

  const updateMatch = url.pathname.match(/^\/api\/enquiries\/([^/]+)$/);
  if (req.method === "PATCH" && updateMatch) {
    const body = await parseRequestBody(req);
    const updated = await updateEnquiry(updateMatch[1], body);
    if (!updated) {
      jsonResponse(res, 404, { error: "Enquiry not found." });
      return;
    }
    jsonResponse(res, 200, updated);
    return;
  }

  if (req.method === "GET" || req.method === "HEAD") {
    await serveStatic(req, res, url);
    return;
  }

  jsonResponse(res, 405, { error: "Method not allowed." });
}

if (!Number.isInteger(REQUESTED_PORT) || REQUESTED_PORT < 0 || REQUESTED_PORT > 65535) {
  console.error("PORT must be an integer between 0 and 65535.");
  process.exit(1);
}

const server = http.createServer(async (req, res) => {
  try {
    await router(req, res);
  } catch (error) {
    const status = error.statusCode || 500;
    jsonResponse(res, status, {
      error: status === 500 ? "Internal server error." : error.message,
      detail: process.env.NODE_ENV === "development" ? error.stack : undefined
    });
    if (status === 500) console.error(error);
  }
});

server.on("error", (error) => {
  if (error.code === "EADDRINUSE") {
    console.error(`Port ${REQUESTED_PORT} is already in use. Set PORT to another value and restart.`);
    process.exit(1);
  }

  if (error.code === "EACCES" || error.code === "EPERM") {
    console.error(`Cannot listen on ${HOST}:${REQUESTED_PORT}. Try HOST=127.0.0.1 or a different PORT.`);
    process.exit(1);
  }

  throw error;
});

server.listen(REQUESTED_PORT, HOST, () => {
  const address = server.address();
  const port = typeof address === "object" && address ? address.port : REQUESTED_PORT;
  console.log(`Strata enquiry workflow demo running at http://${HOST}:${port}`);
});
