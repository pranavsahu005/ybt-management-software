# YBT Management Software — User Manual

Welcome to **YBT Management Software**, a comprehensive business management, CRM, GST billing, and AI-assisted operational platform.

---

## 1. Getting Started & Logging In

### 1.1 Accessing the Console
Open your web browser and navigate to:
```text
http://localhost/YBT Management Software/login.php
```

### 1.2 Default Accounts
| Role | Email | Default Password | Permissions |
|---|---|---|---|
| **Business Owner** | `owner@ybt.com` | `password123` | Full unrestricted authority, approval of all AI proposals, system settings, backups |
| **Operations Manager** | `manager@ybt.com` | `password123` | CRM, pipeline, invoice creation, payment recording, operational reports |

---

## 2. Main Navigation Overview

- **Executive Dashboard:** High-level revenue collections, pending receivables, 6-month P&L trends, urgent tasks, and overdue invoice follow-ups.
- **Customers CRM:** Directory of clients, billing addresses, GSTIN numbers, and transaction ledgers.
- **Leads & Pipeline:** Drag-and-drop or column Kanban tracking prospective commercial deals. Includes 1-click **Convert to Customer** workflow.
- **Products & Services:** Standardized inventory and service catalog with HSN/SAC codes and tax rates.
- **Tasks & Reminders:** Operational deliverables, deadline tracking, and team assignments.
- **Quotations:** Commercial estimates and proposals with 1-click **Convert to Invoice** capabilities.
- **Billing & Invoices:** GST-compliant tax invoices, auto-calculations, payment statuses, and printable formats.
- **Payments:** Recording client payments (NEFT/RTGS, UPI, Cash, Cheque) and allocating to open invoices.
- **Expenses:** Recording operating costs, software subscriptions, travel, and vendor overheads.
- **Reports:** P&L statements, monthly revenue trends, customer rankings, and GST tax reports (CSV exportable).
- **AI Digital Manager:** Natural-language assistant for searching records, analyzing trends, and drafting action proposals.
- **Managers & Permissions:** Team accounts and granular permission toggles for operational staff.
- **Settings:** Company trade profile, bank account & UPI details, invoice numbering sequences, and database backups.

---

## 3. Financial Invoicing & Billing Workflow

1. Navigate to **Billing & Invoices** &rarr; **New Invoice**.
2. Select an existing client or create a new client.
3. Select items from the **Products Catalog** dropdown or type custom descriptions.
4. The system calculates quantity, rate, GST taxes (Intra-state CGST + SGST or Inter-state IGST), and grand total.
5. Click **Issue Invoice**.
6. On the Invoice View screen, click **Print / Save PDF** to generate a clean, branded tax document for the client.
7. When payment is received, click **Record Payment** to clear the invoice balance.

---

## 4. AI Digital Manager & Zero Direct Mutation

The integrated AI assistant acts as a digital operational manager:
- You can ask questions such as:
  - *"Who has overdue invoices?"*
  - *"Show me our revenue this month"*
  - *"List our top deals in pipeline"*
  - *"Find customer Nova Retail"*
- **Safety Rule:** The AI will never modify or create business records directly. It will formulate an **Action Proposal** which requires the Business Owner to explicitly click **Approve & Execute**.
