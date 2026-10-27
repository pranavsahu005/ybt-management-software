# YBT Management Software — AI Digital Manager & API Provider Configuration Guide

The **AI Digital Manager** in YBT Management Software provides automated business operations management, real-time ledger intelligence, CRM search, and supervised action proposals.

---

## 1. Supported AI Providers

YBT Management Software features a flexible multi-provider architecture with preconfigured vendor presets:

| Provider Platform | Supported Models (Quick-Select) | Authentication Protocol | API Key Link |
| :--- | :--- | :--- | :--- |
| **Google AI Studio (Gemini)** | `gemini-1.5-flash` *(Recommended)*, `gemini-1.5-pro`, `gemini-2.0-flash-exp` | Query param (`?key=...`) | [Google AI Studio API Keys](https://aistudio.google.com/app/apikey) |
| **OpenRouter (Unified Gateway)** | `google/gemini-flash-1.5`, `openai/gpt-4o-mini`, `anthropic/claude-3.5-sonnet`, `deepseek/deepseek-chat`, `meta-llama/llama-3.3-70b-instruct` | Bearer Token (`Authorization: Bearer sk-or-v1-...`) | [OpenRouter Keys](https://openrouter.ai/keys) |
| **OpenAI (ChatGPT)** | `gpt-4o-mini`, `gpt-4o`, `gpt-3.5-turbo` | Bearer Token (`Authorization: Bearer sk-proj-...`) | [OpenAI API Keys](https://platform.openai.com/api-keys) |
| **Anthropic Claude** | `claude-3-5-sonnet-20241022`, `claude-3-haiku-20240307` | Custom Header (`x-api-key: ...`, `anthropic-version`) | [Anthropic Console](https://console.anthropic.com/settings/keys) |
| **Custom / Local AI** | Ollama, Groq, LM Studio, vLLM, LocalAI | OpenAI-compatible endpoint | Localhost or self-hosted URL |

---

## 2. In-App Setup Instructions

1. Navigate to **Settings** (`/settings.php`) from the sidebar or click the AI provider badge on the **AI Digital Manager** page.
2. Click the **AI Digital Manager & APIs** tab (`#section-ai-providers`).
3. In the **Configure New AI Provider API** section:
   - **Provider Platform**: Select **Google AI Studio (Gemini)**, **OpenRouter**, or another provider.
   - **Configuration Label**: Enter a friendly name (e.g. *Google Gemini 1.5 Flash*).
   - **API Key**: Paste your secret API key.
   - **Model ID**: Type a custom model ID or click any of the **Quick Select** chips.
   - **Base API URL**: Pre-filled automatically. Modify only if using custom proxies or local models.
   - **Temperature & Max Output Tokens**: Fine-tune precision vs creativity (default `0.70`).
   - Check **Enable this AI provider** and **Set as primary default AI provider**.
4. Click **Test Connection** (<i class="ti ti-bolt"></i>). The system immediately verifies credentials and network latency.
5. Click **Save AI Provider**.

---

## 3. Security & Key Storage

- **AES-256-CBC Encryption at Rest:** All API keys are encrypted before storage in MySQL table `ai_provider_configs` using the application secret key defined in `config/config.php` (`encryption_key`).
- **Zero Key Leaks:** Plaintext keys are never outputted into raw HTML form values or browser logs. The UI displays masked snippets (e.g. `AIza••••••••4a9f`).
- **Owner-Only Privilege:** Modifying AI provider configurations requires `settings.edit` permissions or Owner role.

---

## 4. Architecture & Safeguards

### Invariant 1: Zero Direct Mutation
The AI assistant is strictly advisory and operates in a read-only sandboxed database context:
- It **cannot** execute `INSERT`, `UPDATE`, or `DELETE` SQL commands.
- When an operational action is requested (e.g., *"Create an invoice for Nova Retail"*), the assistant drafts a structured **Action Proposal** (`ai_action_proposals`).
- The **Business Owner** receives an approval notification card and must click **Approve & Execute** to finalize the ledger entry.

### Invariant 2: Permission-Bounded Context
- Operational Managers without `ai.financial` permissions will not receive financial summaries or profit/loss metrics.
- All context passed to cloud LLMs is filtered by the user's active session role.

### Invariant 3: Autonomous Offline Fallback
- If your external cloud provider experiences an outage, network timeout, or quota exhaustion, YBT gracefully and automatically falls back to its built-in rule-based reasoning engine.
- Business operations and common inquiries (overdue invoices, customer lookups, lead stages) continue working without interruption.

---

## 5. Testing via CLI or API

### Live Probe Endpoint
```http
POST /api/ai-test-provider.php
Content-Type: application/json

{
  "provider_type": "google_gemini",
  "api_key": "YOUR_API_KEY",
  "model_id": "gemini-1.5-flash"
}
```
**Response:**
```json
{
  "success": true,
  "message": "Connection successful! Provider Google AI Studio responded.",
  "latency_ms": 284
}
```

### AI Chat Endpoint
```http
POST /api/ai.php
Content-Type: application/json

{
  "message": "Who are our top customers and do they have any outstanding balance?"
}
```
