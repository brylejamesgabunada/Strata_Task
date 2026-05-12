const fs = require("fs");
const os = require("os");
const path = require("path");

const root = path.resolve(__dirname, "..");
const sourcePath = path.join(os.homedir(), "Downloads", "Enquiry Classifier & Response Generator.json");
const outputPath = path.join(root, "Enquiry Classifier & Response Generator - n8n Cloud Mock.json");

const workflow = JSON.parse(fs.readFileSync(sourcePath, "utf8"));

const clientContextCode = String.raw`
const form = $("Strata Inquiry Form").item.json;
const email = String(form.client_email || "").trim().toLowerCase();

const clients = [
  {
    client_id: "CLT-0001",
    full_name: "Maria Santos",
    email: "maria.santos@email.com",
    status: "active",
    lots: [
      {
        lot_number: "14",
        building: "22 Harbour Street, Pyrmont NSW 2009",
        plan_number: "SP12345",
        role: "owner"
      }
    ],
    account_manager: "James Reyes",
    portal_access: true,
    open_requests: 1,
    levy_status: "current"
  },
  {
    client_id: "CLT-0002",
    full_name: "David Nguyen",
    email: "d.nguyen@outlook.com",
    status: "active",
    lots: [
      {
        lot_number: "7",
        building: "10 Marina Cove, Pyrmont NSW 2009",
        plan_number: "SP54321",
        role: "owner"
      }
    ],
    account_manager: "Sarah Kim",
    portal_access: true,
    open_requests: 0,
    levy_status: "overdue"
  },
  {
    client_id: "CLT-0003",
    full_name: "Elena Reyes",
    email: "elena.reyes@gmail.com",
    status: "active",
    lots: [
      {
        lot_number: "22",
        building: "55 Park Avenue, Chippendale NSW 2008",
        plan_number: "SP98765",
        role: "owner"
      }
    ],
    account_manager: "James Reyes",
    portal_access: false,
    open_requests: 0,
    levy_status: "current"
  }
];

const client = clients.find((record) => String(record.email).toLowerCase() === email);

if (!client || client.status !== "active") {
  return [
    {
      json: {
        client_exists: false,
        client_id: null,
        client_type: "New",
        assigned_consultant: "Business Development",
        past_inquiries_count: 0,
        active_projects: [],
        open_requests: 0,
        levy_status: "n/a",
        portal_access: false,
        lots: [],
        profile: null
      }
    }
  ];
}

return [
  {
    json: {
      client_exists: true,
      client_id: client.client_id,
      client_type: "Existing",
      assigned_consultant: client.account_manager || "Unassigned",
      past_inquiries_count: client.open_requests || 0,
      active_projects: client.open_requests > 0 ? ["Open enquiry follow-up"] : [],
      open_requests: client.open_requests || 0,
      levy_status: client.levy_status || "unknown",
      portal_access: Boolean(client.portal_access),
      lots: client.lots || [],
      profile: client
    }
  }
];
`.trim();

const ragCode = String.raw`
const form = $("Strata Inquiry Form").item.json;
const message = String(form.message || "").toLowerCase();

const cases = [
  {
    inquiry_id: "enq-001",
    category: "Complaint",
    subcategory: "Noise Dispute",
    urgency: "High",
    client_status: "existing",
    summary: "Owner in Lot 14 reporting persistent late-night noise from upstairs neighbour.",
    original_message: "Noise complaints from the unit above every night after 10pm.",
    recommended_action: "Issue formal noise by-law breach notice to Lot 15. Log the complaint in the dispute register. Follow up with both parties within 48 hours. If repeated, escalate to NCAT mediation referral.",
    previous_resolution: "By-law notice issued and resolved within 7 days.",
    suggested_response: "Thank you for raising this concern. We take noise complaints very seriously and have logged your report. We will issue a formal notice and follow up with you within 48 hours."
  },
  {
    inquiry_id: "enq-004",
    category: "New Client Enquiry",
    subcategory: "Switching Managers",
    urgency: "Low",
    client_status: "new",
    summary: "Committee chairperson exploring switching strata managers for a 48-lot building.",
    original_message: "Looking to change strata managers before current contract renewal.",
    recommended_action: "Assign to Business Development. Send the new client information pack and fee proposal template. Schedule a discovery call within 2 business days. Request the current management agreement and financial statements.",
    previous_resolution: "Discovery call booked and proposal sent.",
    suggested_response: "Thank you for reaching out. We would be happy to learn more about your building and arrange a no-obligation consultation."
  },
  {
    inquiry_id: "enq-006",
    category: "Support Request",
    subcategory: "Portal Access",
    urgency: "Low",
    client_status: "existing",
    summary: "New owner cannot log in to the owner portal after purchase.",
    original_message: "Recently purchased a lot and have not received portal login details.",
    recommended_action: "Verify the ownership transfer. Create or reactivate the portal account. Send login credentials, portal guide, levy schedule, and by-laws within 1 business day.",
    previous_resolution: "Account created and welcome email sent same day.",
    suggested_response: "Welcome to the building. We will verify your ownership details and arrange your portal access as soon as possible."
  },
  {
    inquiry_id: "enq-007",
    category: "Complaint",
    subcategory: "Management Dissatisfaction",
    urgency: "High",
    client_status: "existing",
    summary: "Committee member complains that maintenance requests and emails are not being actioned.",
    original_message: "Three maintenance issues not actioned and emails take weeks to get a response.",
    recommended_action: "Escalate immediately to the Portfolio Manager or Director. Pull the open maintenance request log. Assign each overdue item to a contractor with a deadline. Provide a written service recovery plan within 24 hours.",
    previous_resolution: "Director call made, all items resolved within 5 days, client retained.",
    suggested_response: "I sincerely apologise for the experience you have had. We have escalated this for immediate review and will provide a written action plan."
  }
];

function score(record) {
  const text = [
    record.category,
    record.subcategory,
    record.summary,
    record.original_message,
    record.recommended_action
  ].join(" ").toLowerCase();

  let value = 0;
  for (const token of message.split(/[^a-z0-9]+/).filter((word) => word.length > 2)) {
    if (text.includes(token)) value += 1;
  }

  if (/noise|loud|neighbour|neighbor/.test(message) && record.subcategory === "Noise Dispute") value += 10;
  if (/switch|change|manager|proposal|contract/.test(message) && record.category === "New Client Enquiry") value += 10;
  if (/portal|login|access/.test(message) && record.subcategory === "Portal Access") value += 10;
  if (/unhappy|unacceptable|ignored|complaint|nothing has been done/.test(message) && record.category === "Complaint") value += 8;

  return value;
}

return [
  {
    json: {
      similar_cases: cases
        .map((record) => ({ ...record, score: score(record) }))
        .sort((a, b) => b.score - a.score)
        .slice(0, 3)
    }
  }
];
`.trim();

const setupNote = [
  "## n8n Cloud Demo Configuration",
  "",
  "This version does not call external backend endpoints.",
  "",
  "The original HTTP Request nodes have been replaced with Code nodes:",
  "",
  "1. Lookup Client Context uses static demo client records.",
  "2. Retrieve Similar Cases (RAG) uses static past enquiry records.",
  "",
  "This is suitable for n8n Cloud because localhost and host.docker.internal are not reachable from n8n Cloud.",
  "",
  "For production, replace these Code nodes with public HTTPS endpoints or a real database/RAG service."
].join("\n");

workflow.name = "Enquiry Classifier & Response Generator - n8n Cloud Mock";
workflow.active = false;
delete workflow.id;
delete workflow.versionId;

for (const node of workflow.nodes) {
  if (node.name === "Lookup Client Context") {
    node.type = "n8n-nodes-base.code";
    node.typeVersion = 2;
    node.parameters = { jsCode: clientContextCode };
    delete node.alwaysOutputData;
    delete node.credentials;
  }

  if (node.name === "Retrieve Similar Cases (RAG)") {
    node.type = "n8n-nodes-base.code";
    node.typeVersion = 2;
    node.parameters = { jsCode: ragCode };
    delete node.alwaysOutputData;
    delete node.credentials;
  }

  if (node.name === "Setup Instructions") {
    node.parameters.content = setupNote;
    node.parameters.height = 360;
    node.parameters.width = 520;
  }
}

fs.writeFileSync(outputPath, `${JSON.stringify(workflow, null, 2)}\n`, "utf8");
console.log(outputPath);
