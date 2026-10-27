<?php
/**
 * YBT Management Software — PDF & Print Service
 * Generates clean, pixel-perfect printable and downloadable invoice/quotation documents
 */

class PdfService {
    /**
     * Render complete invoice document HTML
     */
    public static function renderInvoiceHtml(array $invoice, array $business): string {
        $items = $invoice['items'] ?? [];
        $amountInWords = formatNumberToWords($invoice['grand_total'] ?? 0);

        ob_start();
        ?>
        <div class="invoice-paper">
            <!-- Header -->
            <div class="invoice-header d-flex justify-between align-center">
                <div class="invoice-company">
                    <h2 class="company-name"><?= e($business['name']) ?></h2>
                    <p class="company-tagline text-muted"><?= e($business['tagline'] ?? '') ?></p>
                    <div class="company-address text-sm mt-1">
                        <?= e($business['address_line1']) ?> <?= e($business['address_line2']) ?><br>
                        <?= e($business['city']) ?>, <?= e($business['state']) ?> - <?= e($business['postal_code']) ?><br>
                        <strong>GSTIN:</strong> <?= e($business['gstin']) ?> | <strong>PAN:</strong> <?= e($business['pan']) ?><br>
                        <strong>Email:</strong> <?= e($business['email']) ?> | <strong>Phone:</strong> <?= e($business['phone']) ?>
                    </div>
                </div>
                <div class="invoice-badge-box text-right">
                    <h1 class="invoice-title">TAX INVOICE</h1>
                    <div class="invoice-meta-item">
                        <span class="meta-label">Invoice No:</span>
                        <strong class="meta-val"><?= e($invoice['invoice_number']) ?></strong>
                    </div>
                    <div class="invoice-meta-item">
                        <span class="meta-label">Date:</span>
                        <span class="meta-val"><?= formatDate($invoice['invoice_date']) ?></span>
                    </div>
                    <div class="invoice-meta-item">
                        <span class="meta-label">Due Date:</span>
                        <span class="meta-val"><?= formatDate($invoice['due_date']) ?></span>
                    </div>
                    <div class="invoice-status-pill status-<?= e($invoice['status']) ?> mt-2">
                        <?= strtoupper(e($invoice['status'])) ?>
                    </div>
                </div>
            </div>

            <!-- Bill To -->
            <div class="invoice-parties-grid mt-4">
                <div class="party-box bill-to">
                    <span class="party-label">Billed To:</span>
                    <h4 class="party-name"><?= e($invoice['customer_name']) ?></h4>
                    <?php if (!empty($invoice['company_name'])): ?>
                        <div class="party-company"><?= e($invoice['company_name']) ?></div>
                    <?php endif; ?>
                    <div class="party-contact text-sm mt-1">
                        <?php if (!empty($invoice['customer_gstin'])): ?>
                            <div><strong>GSTIN:</strong> <?= e($invoice['customer_gstin']) ?></div>
                        <?php endif; ?>
                        <div><?= e($invoice['customer_email']) ?> | <?= e($invoice['customer_phone']) ?></div>
                    </div>
                </div>
                <div class="party-box payment-to text-right">
                    <span class="party-label">Bank & Payment Details:</span>
                    <div class="bank-details text-sm mt-1">
                        <div><strong>Bank:</strong> <?= e($business['bank_name']) ?></div>
                        <div><strong>Account:</strong> <?= e($business['account_number']) ?></div>
                        <div><strong>IFSC:</strong> <?= e($business['ifsc_code']) ?></div>
                        <?php if (!empty($business['upi_id'])): ?>
                            <div><strong>UPI ID:</strong> <?= e($business['upi_id']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Line Items Table -->
            <table class="invoice-table mt-4">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 45%;">Item / Description</th>
                        <th style="width: 10%;">HSN/SAC</th>
                        <th style="width: 8%;" class="text-right">Qty</th>
                        <th style="width: 14%;" class="text-right">Unit Price</th>
                        <th style="width: 8%;" class="text-right">Tax</th>
                        <th style="width: 15%;" class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $idx => $item): ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td>
                            <strong><?= e($item['description']) ?></strong>
                        </td>
                        <td><?= e($item['hsn_sac'] ?? '-') ?></td>
                        <td class="text-right"><?= number_format($item['quantity'], 2) ?></td>
                        <td class="text-right"><?= formatCurrency($item['unit_price']) ?></td>
                        <td class="text-right"><?= $item['tax_rate'] ?>%</td>
                        <td class="text-right"><strong><?= formatCurrency($item['line_total']) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Calculation Summary -->
            <div class="invoice-summary-grid mt-4">
                <div class="summary-left">
                    <div class="amount-words-box">
                        <span class="text-muted text-xs">Amount in Words:</span>
                        <div class="amount-words-text"><?= e($amountInWords) ?></div>
                    </div>
                    <?php if (!empty($invoice['terms'])): ?>
                    <div class="terms-box mt-3 text-xs text-muted">
                        <strong>Terms & Conditions:</strong>
                        <p><?= nl2br(e($invoice['terms'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="summary-right">
                    <table class="summary-table">
                        <tr>
                            <td>Subtotal:</td>
                            <td class="text-right"><?= formatCurrency($invoice['subtotal']) ?></td>
                        </tr>
                        <?php if ($invoice['discount_total'] > 0): ?>
                        <tr>
                            <td>Discount:</td>
                            <td class="text-right text-success">- <?= formatCurrency($invoice['discount_total']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($invoice['cgst_total'] > 0): ?>
                        <tr>
                            <td>CGST:</td>
                            <td class="text-right"><?= formatCurrency($invoice['cgst_total']) ?></td>
                        </tr>
                        <tr>
                            <td>SGST:</td>
                            <td class="text-right"><?= formatCurrency($invoice['sgst_total']) ?></td>
                        </tr>
                        <?php elseif ($invoice['igst_total'] > 0): ?>
                        <tr>
                            <td>IGST:</td>
                            <td class="text-right"><?= formatCurrency($invoice['igst_total']) ?></td>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td>Tax:</td>
                            <td class="text-right"><?= formatCurrency($invoice['tax_total']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="total-row">
                            <td><strong>Total Amount:</strong></td>
                            <td class="text-right"><strong><?= formatCurrency($invoice['grand_total']) ?></strong></td>
                        </tr>
                        <tr>
                            <td>Amount Paid:</td>
                            <td class="text-right text-success"><?= formatCurrency($invoice['amount_paid']) ?></td>
                        </tr>
                        <tr class="balance-due-row">
                            <td><strong>Balance Due:</strong></td>
                            <td class="text-right text-danger"><strong><?= formatCurrency($invoice['outstanding']) ?></strong></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Footer & Signatures -->
            <div class="invoice-sign-section mt-5 d-flex justify-between align-end">
                <div class="sign-note text-xs text-muted">
                    This is a computer-generated tax invoice and requires no physical stamp.
                </div>
                <div class="sign-box text-center">
                    <div class="sign-line"></div>
                    <div class="sign-for">For <?= e($business['name']) ?></div>
                    <div class="sign-title text-muted text-xs">Authorized Signatory</div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
