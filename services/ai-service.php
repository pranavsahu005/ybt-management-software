<?php
/**
 * YBT Management Software — AI Assistant Orchestrator
 * Integrates natural language understanding, authorized database queries,
 * external cloud LLM providers (Google AI Studio, OpenRouter, OpenAI, Anthropic),
 * and zero-direct-mutation Owner approval proposals.
 */

require_once __DIR__ . '/ai-tools.php';
require_once __DIR__ . '/ai-provider-service.php';
require_once __DIR__ . '/ai-memory-service.php';
require_once __DIR__ . '/report-service.php';

class AIService {
    /**
     * Process user query and return response with optional action proposal
     */
    public static function process(int $businessId, int $userId, string $message, ?int $conversationId = null): array {
        // Ensure user has AI access
        if (!Permissions::has('ai.access')) {
            return ['success' => false, 'error' => 'You do not have permission to access the AI assistant.'];
        }

        // 1. Check if an active external AI Provider is configured
        $activeProvider = AIProviderService::getActive($businessId);
        if ($activeProvider && (!empty($activeProvider['api_key']) || $activeProvider['provider_type'] === 'custom')) {
            $llmResult = self::queryExternalProvider($businessId, $userId, $activeProvider, $message, $conversationId);
            if ($llmResult['success']) {
                return $llmResult;
            }
            // If LLM failed, log warning and smoothly fall back to built-in rule engine
            error_log("AIService: Cloud provider [{$activeProvider['display_name']}] failed: " . ($llmResult['error'] ?? 'Unknown error') . ". Falling back to built-in engine.");
            $fallback = self::ruleBasedProcess($businessId, $userId, $message, $conversationId);
            $fallback['response'] .= "\n\n*(Provider: Built-in Engine • {$activeProvider['display_name']} connection unavailable: " . htmlspecialchars($llmResult['error'] ?? 'timeout') . ")*";
            return $fallback;
        }

        // 2. Default built-in intelligent reasoning engine
        return self::ruleBasedProcess($businessId, $userId, $message, $conversationId);
    }

    /**
     * Query External Cloud LLM Provider with live Business Context & Safety Invariants
     */
    private static function queryExternalProvider(int $businessId, int $userId, array $provider, string $message, ?int $conversationId): array {
        $business = Database::fetch("SELECT * FROM businesses WHERE id = ?", [$businessId]);
        $userName = Auth::name() ?: 'User';
        $userRole = Auth::isOwner() ? 'Business Owner' : 'Operations Manager';

        // 1. Build live business context snapshot
        $contextLines = [];
        $contextLines[] = "Organization: " . ($business['name'] ?? 'Apex Infotech Solutions');
        $contextLines[] = "Current User: {$userName} ({$userRole})";
        $contextLines[] = "Currency: Indian Rupee (INR ₹)";
        $contextLines[] = "Date: " . date('d M Y');

        // Financial snapshot (if user has permission)
        if (Permissions::has('ai.financial') || Auth::isOwner()) {
            $fin = AITools::getFinancialOverview($businessId);
            $contextLines[] = "Financials: Total Invoiced: ₹" . number_format($fin['total_invoiced'], 2)
                            . ", Total Collected: ₹" . number_format($fin['total_collected'], 2)
                            . ", Outstanding Receivables: ₹" . number_format($fin['total_outstanding'], 2)
                            . ", Total Expenses: ₹" . number_format($fin['total_expenses'], 2)
                            . ", Net Operating Profit: ₹" . number_format($fin['net_profit'], 2)
                            . " ({$fin['profit_margin']}% margin).";
        }

        // Overdue invoices
        $overdue = AITools::getOverdueInvoices($businessId);
        if (!empty($overdue)) {
            $invSummaries = [];
            foreach (array_slice($overdue, 0, 5) as $inv) {
                $invSummaries[] = "{$inv['invoice_number']} ({$inv['customer_name']}): ₹" . number_format($inv['outstanding'], 2) . " due {$inv['due_date']}";
            }
            $contextLines[] = "Overdue Invoices (" . count($overdue) . " total): " . implode('; ', $invSummaries);
        } else {
            $contextLines[] = "Overdue Invoices: None. All accounts are settled.";
        }

        // Pipeline deals
        $deals = AITools::searchLeads($businessId);
        if (!empty($deals)) {
            $dealSummaries = [];
            foreach (array_slice($deals, 0, 5) as $d) {
                $dealSummaries[] = "{$d['title']} ({$d['company_name']}): ₹" . number_format($d['deal_value'], 2) . " [{$d['stage']}]";
            }
            $contextLines[] = "Sales Pipeline Deals: " . implode('; ', $dealSummaries);
        }

        // Top customers
        $customers = AITools::searchCustomers($businessId, '');
        if (!empty($customers)) {
            $custSummaries = [];
            foreach (array_slice($customers, 0, 5) as $c) {
                $custSummaries[] = "{$c['name']} (" . ($c['company_name'] ?: 'Individual') . ", Balance: ₹" . number_format($c['balance'], 2) . ")";
            }
            $contextLines[] = "Recent Customers: " . implode('; ', $custSummaries);
        }

        $businessContext = implode("\n", $contextLines);

        // 2. Formulate System Prompt
        $systemPrompt = <<<PROMPT
You are the AI Digital Operations Manager for {$business['name']}, integrated directly into YBT Management Software.
You assist the team with CRM management, GST invoice generation, expense tracking, debt collections, and executive reporting.

STRICT INVARIANTS & SAFETY RULES:
1. Zero Direct Mutation: You are an advisory AI. You CANNOT mutate database tables directly.
2. If the user asks you to create an invoice, log an expense, or add a quotation, describe the action clearly in natural language, AND append a structured proposal JSON block at the very end of your response inside triple backticks with "json:proposal":

```json:proposal
{
  "action_type": "create_invoice", // create_invoice | create_expense | create_quotation
  "target_entity": "invoice",
  "title": "Short action title",
  "description": "Details of the proposed action",
  "amount": 15000.00,
  "customer": "Customer Name",
  "preview": "Summary for Owner approval card"
}
```

3. Be helpful, professional, and concise. Format responses with clean Markdown bullet points, tables, and bold headers. Always use Indian Rupee (₹) for monetary values.

CURRENT BUSINESS REAL-TIME DATA:
{$businessContext}
PROMPT;

        // 3. Execute chat via AIProviderService
        $chatRes = AIProviderService::executeChat($provider, $systemPrompt, $message);
        if (!$chatRes['success']) {
            return $chatRes;
        }

        $rawText = $chatRes['text'] ?? '';
        $proposalData = null;
        $proposalMeta = null;

        // 4. Extract action proposals if present in LLM output
        if (preg_match('/```json:proposal\s*(\{.*?\})\s*```/s', $rawText, $matches)) {
            $parsedJson = json_decode($matches[1], true);
            if ($parsedJson && !empty($parsedJson['action_type'])) {
                $actionType = $parsedJson['action_type'];
                $targetEntity = $parsedJson['target_entity'] ?? 'record';
                
                // Register proposal in database for Owner review
                $proposalId = AITools::createProposal($businessId, $userId, $actionType, $targetEntity, $parsedJson, $conversationId);
                
                $proposalMeta = [
                    'id' => $proposalId,
                    'title' => $parsedJson['title'] ?? ucfirst(str_replace('_', ' ', $actionType)),
                    'description' => $parsedJson['description'] ?? 'AI proposed operational action',
                    'preview' => $parsedJson['preview'] ?? ('Amount: ₹' . number_format($parsedJson['amount'] ?? 0, 2))
                ];

                // Remove raw JSON block from displayed response text
                $rawText = trim(str_replace($matches[0], '', $rawText));
            }
        }

        return [
            'success' => true,
            'response' => $rawText,
            'proposal' => $proposalMeta,
            'provider' => $provider['display_name'] ?? 'Cloud LLM'
        ];
    }

    /**
     * Built-in Rule-based Reasoning Engine (Autonomous & Zero-Configuration)
     */
    private static function ruleBasedProcess(int $businessId, int $userId, string $message, ?int $conversationId = null): array {
        $cleanMsg = strtolower(trim($message));

        // 1. Overdue Invoices query
        if (str_contains($cleanMsg, 'overdue') || str_contains($cleanMsg, 'pending payment') || str_contains($cleanMsg, 'unpaid')) {
            $overdue = AITools::getOverdueInvoices($businessId);
            if (empty($overdue)) {
                return [
                    'success' => true,
                    'response' => "Great news! There are currently **no overdue invoices** on record for your business."
                ];
            }

            $totalOverdue = array_sum(array_column($overdue, 'outstanding'));
            $res = "Here are the currently **overdue invoices** (Total: **₹" . number_format($totalOverdue, 2) . "**):\n\n";
            foreach ($overdue as $inv) {
                $res .= "- **{$inv['invoice_number']}** — {$inv['customer_name']}: **₹" . number_format($inv['outstanding'], 2) . "** (Due: {$inv['due_date']})\n";
            }
            $res .= "\nWould you like me to prepare reminder follow-up notes for these customers?";
            return ['success' => true, 'response' => $res];
        }

        // 2. Financial Summary query
        if (str_contains($cleanMsg, 'revenue') || str_contains($cleanMsg, 'profit') || str_contains($cleanMsg, 'financial') || str_contains($cleanMsg, 'sales') || str_contains($cleanMsg, 'turnover')) {
            if (!Permissions::has('ai.financial') && !Auth::isOwner()) {
                return [
                    'success' => true,
                    'response' => "I am restricted from displaying financial metrics to your role. Please request access from the Business Owner."
                ];
            }

            $exec = AITools::getFinancialOverview($businessId);
            $res = "### 📊 Executive Financial Overview\n\n"
                 . "- **Total Invoiced:** ₹" . number_format($exec['total_invoiced'], 2) . "\n"
                 . "- **Total Collected:** ₹" . number_format($exec['total_collected'], 2) . "\n"
                 . "- **Outstanding Receivables:** ₹" . number_format($exec['total_outstanding'], 2) . "\n"
                 . "- **Total Expenses:** ₹" . number_format($exec['total_expenses'], 2) . "\n"
                 . "- **Net Profit:** ₹" . number_format($exec['net_profit'], 2) . " (*{$exec['profit_margin']}% margin*)\n"
                 . "- **Active Deal Pipeline:** ₹" . number_format($exec['total_pipeline'], 2) . " across {$exec['active_leads']} deals.\n";

            return ['success' => true, 'response' => $res];
        }

        // 3. Leads & Pipeline query
        if (str_contains($cleanMsg, 'lead') || str_contains($cleanMsg, 'deal') || str_contains($cleanMsg, 'pipeline')) {
            $leads = AITools::searchLeads($businessId);
            if (empty($leads)) {
                return ['success' => true, 'response' => "No active sales leads found."];
            }

            $res = "### 🎯 Top Active Deals in Pipeline\n\n";
            foreach ($leads as $l) {
                $res .= "- **{$l['title']}** ({$l['company_name']}) — **₹" . number_format($l['deal_value'], 2) . "** | Stage: `{$l['stage']}`\n";
            }
            return ['success' => true, 'response' => $res];
        }

        // 4. Customer lookup
        if (str_contains($cleanMsg, 'customer') || str_contains($cleanMsg, 'client')) {
            preg_match('/(?:customer|client)\s+([a-zA-Z0-9]+)/i', $message, $m);
            $search = $m[1] ?? '';
            $customers = AITools::searchCustomers($businessId, $search);

            if (empty($customers)) {
                return ['success' => true, 'response' => "No customers matched your search query."];
            }

            $res = "Found " . count($customers) . " matching customer(s):\n\n";
            foreach ($customers as $c) {
                $res .= "- **{$c['name']}** (" . ($c['company_name'] ?: 'Individual') . ") — City: {$c['billing_city']} | Balance: ₹" . number_format($c['balance'], 2) . "\n";
            }
            return ['success' => true, 'response' => $res];
        }

        // 5. Action proposal: Create invoice draft
        if (str_contains($cleanMsg, 'create invoice') || str_contains($cleanMsg, 'generate invoice')) {
            if (!Permissions::has('ai.propose_actions') && !Auth::isOwner()) {
                return ['success' => true, 'response' => "You do not have permission to propose actions through the AI assistant."];
            }

            $proposalData = [
                'type' => 'invoice',
                'customer' => 'Rajesh Sharma (Nova Retail)',
                'amount' => 15000.00,
                'description' => 'Website maintenance and server monitoring retainer'
            ];

            $proposalId = AITools::createProposal($businessId, $userId, 'create_invoice', 'invoice', $proposalData, $conversationId);

            return [
                'success' => true,
                'response' => "I have drafted an action proposal for creating this invoice. Per YBT safety rules, this change will only be executed once approved by the Business Owner.",
                'proposal' => [
                    'id' => $proposalId,
                    'title' => 'Generate Draft Invoice for Nova Retail Enterprises',
                    'description' => 'Create draft invoice for ₹15,000.00 (Website maintenance retainer)',
                    'preview' => 'Customer: Rajesh Sharma | Amount: ₹15,000.00 | Tax: 18% GST'
                ]
            ];
        }

        // 6. Action proposal: Log expense
        if (str_contains($cleanMsg, 'log expense') || str_contains($cleanMsg, 'record expense')) {
            $proposalData = [
                'type' => 'expense',
                'category' => 'Software Subscriptions & Tools',
                'amount' => 2500.00,
                'description' => 'Monthly Zoom Pro & Collaboration Tools'
            ];

            $proposalId = AITools::createProposal($businessId, $userId, 'create_expense', 'expense', $proposalData, $conversationId);

            return [
                'success' => true,
                'response' => "I have prepared an expense entry proposal for your review.",
                'proposal' => [
                    'id' => $proposalId,
                    'title' => 'Log Expense: Software Subscriptions',
                    'description' => 'Record ₹2,500.00 expense for Zoom Pro licenses',
                    'preview' => 'Vendor: Zoom Video Communications | Category: Software Tools | Amount: ₹2,500.00'
                ]
            ];
        }

        // Default intelligent fallback
        return [
            'success' => true,
            'response' => "Hello! I am your **YBT Digital Manager**. I can assist you with:\n\n"
                        . "- 📈 **Financial Reports:** *'Show me our revenue this month'* or *'What is our net profit?'*\n"
                        . "- ⚠️ **Debt & Receivables:** *'Who has overdue invoices?'* or *'What is our outstanding balance?'*\n"
                        . "- 💼 **Sales Pipeline:** *'Show active deals'* or *'List our top leads'*\n"
                        . "- 👥 **CRM Intelligence:** *'Find customer Nova Retail'* or *'Show client list'*\n"
                        . "- 🛡️ **Assisted Actions:** *'Create invoice for client'* or *'Log expense for software'*\n\n"
                        . "What would you like to examine today?"
        ];
    }
}
