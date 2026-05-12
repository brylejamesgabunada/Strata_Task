const fs = require("fs");
const os = require("os");
const path = require("path");

const root = path.resolve(__dirname, "..");
const sourcePath = path.join(os.homedir(), "Downloads", "Enquiry Classifier & Response Generator.json");
const outputPath = path.join(root, "Enquiry Classifier & Response Generator - Webhook UI.json");
const backendBaseUrl = (process.env.BACKEND_BASE_URL || "https://e293-103-43-214-18.ngrok-free.app").replace(/\/$/, "");

const workflow = JSON.parse(fs.readFileSync(sourcePath, "utf8"));

workflow.name = "Enquiry Classifier & Response Generator - Webhook UI";
workflow.active = false;
delete workflow.id;
delete workflow.versionId;

for (const node of workflow.nodes) {
  if (node.name === "Strata Inquiry Form") {
    node.name = "Custom UI Webhook";
    node.type = "n8n-nodes-base.webhook";
    node.typeVersion = 2;
    node.webhookId = "strata-enquiry-custom-ui";
    node.parameters = {
      httpMethod: "POST",
      path: "strata-enquiry",
      responseMode: "onReceived",
      responseData: "firstEntryJson",
      options: {}
    };
  }

  if (node.name === "Lookup Client Context") {
    node.parameters = {
      url: `={{ "${backendBaseUrl}/api/client/context?email=" + $json.client_email }}`,
      sendHeaders: true,
      headerParameters: {
        parameters: [
          {
            name: "ngrok-skip-browser-warning",
            value: "true"
          }
        ]
      },
      options: {
        response: {
          response: {
            neverError: true
          }
        }
      }
    };
  }

  if (node.name === "Retrieve Similar Cases (RAG)") {
    node.parameters = {
      method: "POST",
      url: `${backendBaseUrl}/api/rag/search`,
      sendHeaders: true,
      headerParameters: {
        parameters: [
          {
            name: "ngrok-skip-browser-warning",
            value: "true"
          }
        ]
      },
      sendBody: true,
      specifyBody: "json",
      jsonBody: "={{ { \"query\": $(\"Strata Inquiry Form\").item.json.message, \"limit\": 3 } }}",
      options: {
        response: {
          response: {
            neverError: true
          }
        }
      }
    };
  }
}

workflow.nodes.push({
  parameters: {
    assignments: {
      assignments: [
        {
          id: "client_email",
          name: "client_email",
          value: "={{ $json.body?.client_email ?? $json.body?.email ?? $json.client_email ?? $json.email }}",
          type: "string"
        },
        {
          id: "client_name",
          name: "client_name",
          value: "={{ $json.body?.client_name ?? $json.body?.name ?? $json.client_name ?? $json.name }}",
          type: "string"
        },
        {
          id: "building_name",
          name: "building_name",
          value: "={{ $json.body?.building_name ?? $json.building_name ?? \"\" }}",
          type: "string"
        },
        {
          id: "building_size",
          name: "building_size",
          value: "={{ Number($json.body?.building_size ?? $json.building_size ?? 0) }}",
          type: "number"
        },
        {
          id: "message",
          name: "message",
          value: "={{ $json.body?.message ?? $json.body?.inquiry ?? $json.message ?? $json.inquiry }}",
          type: "string"
        }
      ]
    },
    options: {}
  },
  id: "custom-ui-normalize-input",
  name: "Strata Inquiry Form",
  type: "n8n-nodes-base.set",
  typeVersion: 3.4,
  position: [-320, 400]
});

const originalFormConnections = workflow.connections["Strata Inquiry Form"];
delete workflow.connections["Strata Inquiry Form"];
workflow.connections["Custom UI Webhook"] = {
  main: [
    [
      {
        node: "Strata Inquiry Form",
        type: "main",
        index: 0
      }
    ]
  ]
};
workflow.connections["Strata Inquiry Form"] = originalFormConnections;

workflow.nodes.push({
  parameters: {
    content: [
      "## Custom UI Webhook",
      "",
      "Use this workflow when the client form is your own web UI.",
      "",
      "Production webhook URL after activation:",
      "`https://gabunadame.app.n8n.cloud/webhook/strata-enquiry`",
      "",
      "Local backend/RAG URL configured in HTTP nodes:",
      `\`${backendBaseUrl}\``
    ].join("\n"),
    height: 260,
    width: 460,
    color: 3
  },
  id: "custom-ui-webhook-note",
  name: "Custom UI Webhook Notes",
  type: "n8n-nodes-base.stickyNote",
  typeVersion: 1,
  position: [-120, 96]
});

fs.writeFileSync(outputPath, `${JSON.stringify(workflow, null, 2)}\n`, "utf8");
console.log(outputPath);
