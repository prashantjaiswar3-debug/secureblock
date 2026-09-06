<?php
require_once 'db.php';
check_session_guard();

// --- RETRIEVE STRATEGIC ENGINE QUERY PARAMETERS ---
$ta_name = isset($_GET['ta_name']) ? trim($_GET['ta_name']) : '';
$billing_month = isset($_GET['billing_month']) ? trim($_GET['billing_month']) : ''; // Format expected: YYYY-MM

if (empty($ta_name) || empty($billing_month)) {
    echo "<div style='font-family:sans-serif; padding:20px; color:#c0392b; font-weight:bold;'>Error: Missing required structural parameter filters (Personnel Profile or Billing Month Space).</div>";
    exit;
}

// Extract year and month cleanly for database mapping
$year = substr($billing_month, 0, 4);
$month = substr($billing_month, 5, 2);
$formatted_month_display = date('F Y', strtotime($billing_month . "-01"));

// 1. Fetch Client Profile Details (Block, Phone, etc.)
$stmt_client = $pdo->prepare("SELECT * FROM clients WHERE name = ? LIMIT 1");
$stmt_client->execute([$ta_name]);
$client_profile = $stmt_client->fetch();

// 2. Fetch All Matching Job Rows within specified Month Metric Range
$stmt_jobs = $pdo->prepare("SELECT * FROM work_tracker WHERE ta_name = ? AND YEAR(received_date) = ? AND MONTH(received_date) = ? ORDER BY id ASC");
$stmt_jobs->execute([$ta_name, $year, $month]);
$invoice_jobs = $stmt_jobs->fetchAll();

// 3. Compute Summary Financial Pools
$total_jobs_count = count($invoice_jobs);
$gross_billed_sum = 0.00;
$total_paid_sum = 0.00;

foreach ($invoice_jobs as $job) {
    $gross_billed_sum += floatval($job['bill_amount']);
    $total_paid_sum += floatval($job['paid_amount']);
}
$net_outstanding_balance = $gross_billed_sum - $total_paid_sum;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice Statement - <?php echo htmlspecialchars($ta_name) . " (" . htmlspecialchars($billing_month) . ")"; ?></title>
    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #fff; margin: 30px; color: #2c3e50; font-size: 13px; line-height: 1.5; }
        
        /* HEADER FRAME */
        .invoice-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px double #1a2a4d; padding-bottom: 15px; margin-bottom: 25px; }
        .company-identity { font-size: 24px; font-weight: 800; color: #1a2a4d; text-transform: uppercase; letter-spacing: 0.5px; }
        .company-identity span { color: #27ae60; }
        .document-title { font-size: 16px; font-weight: bold; color: #7f8c8d; text-transform: uppercase; text-align: right; letter-spacing: 1px; }
        
        /* META MATRIX LAYOUT */
        .meta-container { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 30px; }
        .meta-block-title { font-size: 11px; font-weight: bold; text-transform: uppercase; color: #5f6368; border-bottom: 1px dashed #cfd4da; padding-bottom: 4px; margin-bottom: 8px; letter-spacing: 0.5px; }
        .meta-data-row { margin-bottom: 4px; font-size: 13px; }
        .meta-data-row strong { color: #1a2a4d; }
        
        /* DATA PRESENTATION MATRIX LEDGER */
        .itemized-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 12px; }
        .itemized-table th { background: #1a2a4d; color: white; padding: 10px 8px; font-weight: 600; text-transform: uppercase; font-size: 11px; border: 1px solid #1a2a4d; text-align: left; }
        .itemized-table td { padding: 10px 8px; border: 1px solid #eef2f7; vertical-align: top; }
        .itemized-table tr:nth-child(even) td { background: #fdfdfd; }
        
        /* SUMMARY CARD METRICS LAYOUT */
        .summary-wrapper { display: flex; justify-content: flex-end; margin-bottom: 40px; }
        .summary-box { width: 300px; border-collapse: collapse; font-size: 13px; }
        .summary-box td { padding: 6px 8px; border: 1px solid #eef2f7; }
        .summary-box tr.grand-total { font-weight: bold; background: #f8f9fa; font-size: 14px; color: #1a2a4d; border-top: 2px solid #1a2a4d; }
        
        /* PRINT AND ACTION UTILITY ELEMENTS */
        .utility-print-bar { background: #f8f9fa; border: 1px solid #e6e8eb; border-radius: 4px; padding: 12px 20px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .btn-action-print { background: #2f5597; color: white; border: none; padding: 8px 16px; border-radius: 4px; font-weight: bold; font-size: 12px; text-transform: uppercase; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .btn-action-print:hover { background: #223e6f; }
        .badge-status { padding: 2px 5px; border-radius: 3px; font-size: 10px; font-weight: bold; text-transform: uppercase; border: 1px solid; display: inline-block; }
        .status-completed { background: #e8f8f5; color: #117a65; border-color: #a3e4d7; }
        .status-pending { background: #fff9e6; color: #f39c12; border-color: #ffeaa7; }

        @media print {
            .utility-print-bar { display: none !important; }
            body { margin: 10px; color: #000; }
        }
    </style>
</head>
<body>

<div class="utility-print-bar">
    <div>
        <span style="font-weight: 600;">System Generated Preview</span> — Ready for processing, validation, or manual dispatch.
    </div>
    <button type="button" class="btn-action-print" onclick="window.print()">🖨️ Print Statement</button>
</div>

<div class="invoice-header">
    <div class="company-identity">SECURE <span>SUITE V3</span></div>
    <div class="document-title">
        Monthly Work Statement<br>
        <span style="font-size:12px; font-weight:normal; color:#95a5a6; letter-spacing:0;">Generated: <?php echo date('d-M-Y h:i A'); ?></span>
    </div>
</div>

<div class="meta-container">
    <div>
        <div class="meta-block-title">Statement Account Details (Bill To)</div>
        <div class="meta-data-row">Personnel Name: <strong><?php echo htmlspecialchars($ta_name); ?></strong></div>
        <div class="meta-data-row">Designation: Technical Assistant</div>
        <div class="meta-data-row">Mapped Region Block: <?php echo htmlspecialchars($client_profile['block'] ?? 'N/A'); ?></div>
        <div class="meta-data-row">Contact Number: <?php echo htmlspecialchars($client_profile['phone'] ?? 'N/A'); ?></div>
    </div>
    <div style="text-align: right;">
        <div class="meta-block-title" style="border-bottom: 1px dashed #cfd4da; text-align: right;">Statement Context Cycle</div>
        <div class="meta-data-row">Billing Cycle Month: <strong><?php echo $formatted_month_display; ?></strong></div>
        <div class="meta-data-row">Total Logged Items: <strong><?php echo $total_jobs_count; ?> jobs</strong></div>
        <div class="meta-data-row">Statement Hash Reference: <span style="font-family: monospace; font-weight:600;">STMT-<?php echo strtoupper(substr(md5($ta_name . $billing_month), 0, 8)); ?></span></div>
    </div>
</div>

<table class="itemized-table">
    <thead>
        <tr>
            <th style="width: 50px; text-align: center;">Job ID</th>
            <th style="width: 100px;">Estimate Code</th>
            <th>KHI Work Name Description Context</th>
            <th style="width: 120px;">Gram Panchayat</th>
            <th style="width: 90px; text-align: center;">Log Date</th>
            <th style="width: 80px; text-align: center;">Status</th>
            <th style="width: 95px; text-align: right;">Billed Amount</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($total_jobs_count > 0): ?>
            <?php foreach ($invoice_jobs as $job): ?>
                <tr>
                    <td style="text-align: center; font-family: monospace; color:#7f8c8d;">#<?php echo $job['id']; ?></td>
                    <td><span style="font-family:monospace; font-weight:600; background:#f8f9fa; padding:2px 4px; border:1px solid #dcdde1; border-radius:3px;"><?php echo htmlspecialchars($job['estimate_number'] ?? 'N/A'); ?></span></td>
                    <td style="font-weight: 600; color:#2c3e50; line-height:1.4;"><?php echo htmlspecialchars($job['work_name']); ?></td>
                    <td><strong><?php echo htmlspecialchars($job['gp_name']); ?></strong></td>
                    <td style="text-align: center;"><?php echo date('d-m-Y', strtotime($job['received_date'])); ?></td>
                    <td style="text-align: center;">
                        <?php $status_cls = (strtolower($job['status']) === 'completed') ? 'status-completed' : 'status-pending'; ?>
                        <span class="badge-status <?php echo $status_cls; ?>"><?php echo htmlspecialchars($job['status']); ?></span>
                    </td>
                    <td style="text-align: right; font-weight: 600;">₹<?php echo number_format($job['bill_amount'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="7" style="text-align: center; padding: 30px; color: #95a5a6; font-style: italic;">No work entries found recorded against this user parameter for the selected month window cycle.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="summary-wrapper">
    <table class="summary-box">
        <tr>
            <td style="color: #5f6368;">Gross Billing Accumulation:</td>
            <td style="text-align: right; font-weight: 600;">₹<?php echo number_format($gross_billed_sum, 2); ?></td>
        </tr>
        <tr>
            <td style="color: #27ae60;">Total Applied Receipts:</td>
            <td style="text-align: right; font-weight: 600; color: #27ae60;">₹<?php echo number_format($total_paid_sum, 2); ?></td>
        </tr>
        <tr class="grand-total">
            <td>Net Payable Balance Due:</td>
            <td style="text-align: right; color: <?php echo $net_outstanding_balance > 0 ? '#c0392b' : '#27ae60'; ?>;">₹<?php echo number_format($net_outstanding_balance, 2); ?></td>
        </tr>
    </table>
</div>

<div style="margin-top: 80px; border-top: 1px solid #eef2f7; padding-top:10px; font-size:11px; color:#95a5a6; text-align:center;">
    This statement invoice is auto-compiled securely by the workspace transaction engine layer and does not require physical seals or dynamic authorization tokens.
</div>

</body>
</html>