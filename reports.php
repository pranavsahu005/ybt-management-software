<?php
/**
 * YBT Management Software — Financial Reports & Analytics
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/services/report-service.php';

Auth::requireLogin();
Permissions::require('reports.view');

$businessId = Auth::businessId();

// Date range filters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Handle CSV Export
if (isset($_GET['action']) && $_GET['action'] === 'export_gst') {
    Permissions::require('reports.export');
    $gstData = ReportService::getGstReport($businessId, $startDate, $endDate);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=ybt_gst_report_' . date('Ymd') . '.csv');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Invoice Number', 'Invoice Date', 'Place of Supply', 'Taxable Subtotal', 'CGST', 'SGST', 'IGST', 'Total Tax', 'Grand Total']);

    foreach ($gstData as $row) {
        fputcsv($out, [
            $row['invoice_number'],
            $row['invoice_date'],
            $row['place_of_supply'],
            $row['subtotal'],
            $row['cgst_total'],
            $row['sgst_total'],
            $row['igst_total'],
            $row['tax_total'],
            $row['grand_total']
        ]);
    }
    fclose($out);
    exit;
}

$exec = ReportService::getExecutiveSummary($businessId);
$monthlyTrends = ReportService::getMonthlyTrends($businessId, 6);
$topCustomers = ReportService::getTopCustomers($businessId, 5);
$gstInvoices = ReportService::getGstReport($businessId, $startDate, $endDate);

$pageTitle = 'Financial Reports';
require YBT_ROOT . '/includes/header.php';
?>

<div class="page-header d-flex justify-between align-center mb-4">
    <div>
        <h1 class="page-title">Financial Reports & Statements</h1>
        <p class="page-subtitle">Profit & loss analysis, customer sales contribution, and GST tax filings</p>
    </div>
    <div class="page-header-actions d-flex gap-2 no-print">
        <a href="<?= url('reports.php?action=export_gst&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate)) ?>" class="btn btn-secondary">
            <i class="ti ti-download mr-1"></i> Export GST (CSV)
        </a>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="ti ti-printer mr-1"></i> Print Statement
        </button>
    </div>
</div>

<!-- Date Filter Form (no print) -->
<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="<?= url('reports.php') ?>" class="d-flex flex-wrap gap-3 align-center">
            <div class="d-flex align-center gap-2">
                <label class="text-sm font-medium">Period:</label>
                <input type="date" name="start_date" class="form-input form-input-sm" value="<?= e($startDate) ?>">
                <span class="text-muted">to</span>
                <input type="date" name="end_date" class="form-input form-input-sm" value="<?= e($endDate) ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="ti ti-filter mr-1"></i> Update Report</button>
        </form>
    </div>
</div>

<!-- Executive Summary Cards -->
<div class="kpi-grid mb-4">
    <div class="kpi-card card-glow-primary">
        <div class="kpi-card-header"><span class="kpi-title">Gross Revenue (Cleared)</span></div>
        <div class="kpi-value text-primary"><?= formatCurrency($exec['total_collected']) ?></div>
        <div class="kpi-footer text-xs text-muted">Total Invoiced: <?= formatCurrency($exec['total_invoiced']) ?></div>
    </div>

    <div class="kpi-card card-glow-danger">
        <div class="kpi-card-header"><span class="kpi-title">Operating Expenses</span></div>
        <div class="kpi-value text-danger"><?= formatCurrency($exec['total_expenses']) ?></div>
        <div class="kpi-footer text-xs text-muted">All categories</div>
    </div>

    <div class="kpi-card card-glow-success">
        <div class="kpi-card-header"><span class="kpi-title">Net Operating Profit</span></div>
        <div class="kpi-value text-success"><?= formatCurrency($exec['net_profit']) ?></div>
        <div class="kpi-footer text-xs"><span class="badge badge-success"><?= $exec['profit_margin'] ?>% margin</span></div>
    </div>

    <div class="kpi-card card-glow-warning">
        <div class="kpi-card-header"><span class="kpi-title">Pending Receivables</span></div>
        <div class="kpi-value text-warning"><?= formatCurrency($exec['total_outstanding']) ?></div>
        <div class="kpi-footer text-xs text-muted">Uncollected balances</div>
    </div>
</div>

<div class="grid grid-cols-2 gap-4 mb-4">
    <!-- Monthly P&L -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="ti ti-calendar mr-1 text-primary"></i> 6-Month P&L History</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th class="text-right">Revenue</th>
                            <th class="text-right">Expenses</th>
                            <th class="text-right">Net Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthlyTrends as $m): ?>
                        <tr>
                            <td><strong><?= e($m['month']) ?></strong></td>
                            <td class="text-right text-success font-medium"><?= formatCurrency($m['revenue']) ?></td>
                            <td class="text-right text-danger font-medium"><?= formatCurrency($m['expenses']) ?></td>
                            <td class="text-right font-bold <?= $m['profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= formatCurrency($m['profit']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Top Customers -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="ti ti-trophy mr-1 text-warning"></i> Top Revenue Customers</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th class="text-right">Total Invoiced</th>
                            <th class="text-right">Collected</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topCustomers as $c): ?>
                        <tr>
                            <td>
                                <strong><?= e($c['name']) ?></strong>
                                <?php if (!empty($c['company_name'])): ?>
                                    <div class="text-xs text-muted"><?= e($c['company_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-right font-medium"><?= formatCurrency($c['total_billed']) ?></td>
                            <td class="text-right font-bold text-success"><?= formatCurrency($c['total_paid']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- GST Breakdown Table -->
<div class="card">
    <div class="card-header d-flex justify-between align-center">
        <h3 class="card-title"><i class="ti ti-file-certificate mr-1 text-info"></i> GST Outward Supplies Breakdown (<?= formatDate($startDate) ?> to <?= formatDate($endDate) ?>)</h3>
        <span class="badge badge-subtle"><?= count($gstInvoices) ?> records</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($gstInvoices)): ?>
            <div class="p-4 text-center text-muted text-sm">No tax invoices recorded in this period.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Place of Supply</th>
                            <th class="text-right">Taxable Value</th>
                            <th class="text-right">CGST</th>
                            <th class="text-right">SGST</th>
                            <th class="text-right">IGST</th>
                            <th class="text-right">Total Tax</th>
                            <th class="text-right">Invoice Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $sumTaxable = 0; $sumCGST = 0; $sumSGST = 0; $sumIGST = 0; $sumTax = 0; $sumGrand = 0;
                        foreach ($gstInvoices as $g): 
                            $sumTaxable += $g['subtotal'];
                            $sumCGST += $g['cgst_total'];
                            $sumSGST += $g['sgst_total'];
                            $sumIGST += $g['igst_total'];
                            $sumTax += $g['tax_total'];
                            $sumGrand += $g['grand_total'];
                        ?>
                        <tr>
                            <td><strong><?= e($g['invoice_number']) ?></strong></td>
                            <td><?= formatDate($g['invoice_date']) ?></td>
                            <td><?= e($g['place_of_supply'] ?: 'Local') ?></td>
                            <td class="text-right"><?= formatCurrency($g['subtotal']) ?></td>
                            <td class="text-right"><?= formatCurrency($g['cgst_total']) ?></td>
                            <td class="text-right"><?= formatCurrency($g['sgst_total']) ?></td>
                            <td class="text-right"><?= formatCurrency($g['igst_total']) ?></td>
                            <td class="text-right font-medium"><?= formatCurrency($g['tax_total']) ?></td>
                            <td class="text-right font-bold text-primary"><?= formatCurrency($g['grand_total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="font-bold bg-subtle">
                            <td colspan="3">Total Summary</td>
                            <td class="text-right"><?= formatCurrency($sumTaxable) ?></td>
                            <td class="text-right"><?= formatCurrency($sumCGST) ?></td>
                            <td class="text-right"><?= formatCurrency($sumSGST) ?></td>
                            <td class="text-right"><?= formatCurrency($sumIGST) ?></td>
                            <td class="text-right font-bold text-info"><?= formatCurrency($sumTax) ?></td>
                            <td class="text-right font-bold text-primary"><?= formatCurrency($sumGrand) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require YBT_ROOT . '/includes/footer.php'; ?>
