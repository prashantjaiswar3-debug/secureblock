<?php
require_once 'db.php';
check_session_guard();

// --- PROCESS CASH GATEWAY RECEIPT FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_payment') {
    $ta_name = trim($_POST['depositing_operator']);
    $amount = floatval($_POST['collected_amount']);
    $pay_date = $_POST['payment_date'];
    $pay_mode = $_POST['payment_mode'];
    $ref_no = trim($_POST['reference_no']);
    
    if (!empty($ta_name) && $amount > 0) {
        $pdo->beginTransaction();
        try {
            // 1. Insert transaction token record with the explicit details
            $stmt_pay = $pdo->prepare("INSERT INTO payments_ledger (ta_name, amount, payment_date, payment_mode, reference_no, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt_pay->execute([$ta_name, $amount, $pay_date, $pay_mode, $ref_no]);
            
            // 2. Clear outstanding balances across work records dynamically
            $stmt_jobs = $pdo->prepare("SELECT id, bill_amount, paid_amount FROM work_tracker WHERE ta_name = ? AND status != 'Completed' ORDER BY id ASC");
            $stmt_jobs->execute([$ta_name]);
            $unpaid_jobs = $stmt_jobs->fetchAll();
            
            $remaining_pool = $amount;
            foreach ($unpaid_jobs as $job) {
                if ($remaining_pool <= 0) break;
                
                $due = $job['bill_amount'] - $job['paid_amount'];
                if ($due > 0) {
                    if ($remaining_pool >= $due) {
                        $new_paid = $job['bill_amount'];
                        $remaining_pool -= $due;
                        $up_stmt = $pdo->prepare("UPDATE work_tracker SET paid_amount = ?, status = 'Completed' WHERE id = ?");
                        $up_stmt->execute([$new_paid, $job['id']]);
                    } else {
                        $new_paid = $job['paid_amount'] + $remaining_pool;
                        $remaining_pool = 0;
                        $up_stmt = $pdo->prepare("UPDATE work_tracker SET paid_amount = ? WHERE id = ?");
                        $up_stmt->execute([$new_paid, $job['id']]);
                    }
                }
            }
            
            $pdo->commit();
            header("Location: accounts.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "Execution Error: " . $e->getMessage();
            exit;
        }
    }
}

// --- CALCULATE CORE WALLET BALANCE MATRIX METRICS ---
$ta_list = $pdo->query("SELECT name, block FROM clients ORDER BY name ASC")->fetchAll();
$wallet_matrix = [];

foreach ($ta_list as $ta) {
    $stmt = $pdo->prepare("SELECT SUM(bill_amount) as total_billed, SUM(paid_amount) as total_paid FROM work_tracker WHERE ta_name = ?");
    $stmt->execute([$ta['name']]);
    $res = $stmt->fetch();
    
    $gross_billed = $res['total_billed'] ?? 0.00;
    $total_receipts = $res['total_paid'] ?? 0.00;
    
    $current_owed = $gross_billed - $total_receipts;
    $advance_pool = 0.00;
    if ($current_owed < 0) {
        $advance_pool = abs($current_owed);
        $current_owed = 0.00;
    }
    
    $wallet_matrix[] = [
        'name' => $ta['name'],
        'block' => $ta['block'],
        'billed' => $gross_billed,
        'receipts' => $total_receipts,
        'owed' => $current_owed,
        'advance' => $advance_pool
    ];
}

// --- FETCH PAYMENT TRACE HISTORY ---
$history = $pdo->query("SELECT * FROM payments_ledger ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SECURE V3 - Integrated Ledger Accounts</title>
    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; background-color: #f0f2f5; margin: 20px; color: #333; }
        .dashboard-layout { display: grid; grid-template-columns: 2.2fr 1fr; gap: 20px; }
        
        .card-node { background: #ffffff; border-radius: 4px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); border: 1px solid #dcdde1; padding: 20px; margin-bottom: 20px; }
        .card-title { font-size: 12px; font-weight: bold; color: #1a2a4d; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #f1f2f6; padding-bottom: 10px; margin-bottom: 15px; display: flex; align-items: center; gap: 6px; }
        
        .ledger-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .ledger-table th { background: #1a2a4d; color: white; padding: 10px 8px; font-weight: 600; text-transform: uppercase; font-size: 11px; border: 1px solid #223661; text-align: left; }
        .ledger-table td { padding: 10px 8px; border: 1px solid #eef2f7; vertical-align: middle; background: #fff; }
        .ledger-table tr:nth-child(even) td { background: #fdfdfd; }
        
        .field-group { display: flex; flex-direction: column; margin-bottom: 12px; }
        .field-group label { font-size: 10px; font-weight: bold; color: #5f6368; text-transform: uppercase; margin-bottom: 5px; }
        .field-group input, .field-group select { padding: 8px 10px; border: 1px solid #cfd4da; border-radius: 4px; font-size: 13px; font-weight: 500; background: #fafafa; box-sizing: border-box; width: 100%; }
        
        .btn-submit-token { background: #27ae60; color: white; border: none; padding: 10px; border-radius: 4px; font-weight: bold; font-size: 12px; text-transform: uppercase; cursor: pointer; width: 100%; margin-top: 5px; }
        .btn-submit-token:hover { background: #219653; }
        .btn-statement { background: #2f5597; color: white; border: none; padding: 10px; border-radius: 4px; font-weight: bold; font-size: 12px; text-transform: uppercase; cursor: pointer; width: 100%; }
        
        .badge-amt { font-weight: 600; }
        .txt-danger { color: #c0392b; font-weight: bold; }
        .txt-success { color: #27ae60; font-weight: bold; }
    </style>
</head>
<body>

<div class="dashboard-layout">
    
    <div>
        <div class="card-node">
            <div class="card-title">💻 Technical Assistant Core Wallets Matrix</div>
            <table class="ledger-table">
                <thead>
                    <tr>
                        <th>Personnel Profile Name</th>
                        <th>Regional Block Mapping</th>
                        <th style="text-align:right;">Gross Billed</th>
                        <th style="text-align:right;">Total Receipts</th>
                        <th style="text-align:right;">Current Owed Balance</th>
                        <th style="text-align:right;">Advance Pool Credit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($wallet_matrix as $w): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($w['name']); ?></strong></td>
                            <td style="color: #7f8c8d; font-size: 11px;"><?php echo htmlspecialchars($w['block']); ?></td>
                            <td style="text-align:right;" class="badge-amt">₹<?php echo number_format($w['billed'], 2); ?></td>
                            <td style="text-align:right;" class="badge-amt">₹<?php echo number_format($w['receipts'], 2); ?></td>
                            <td style="text-align:right;" class="badge-amt <?php echo $w['owed'] > 0 ? 'txt-danger' : ''; ?>">₹<?php echo number_format($w['owed'], 2); ?></td>
                            <td style="text-align:right;" class="badge-amt <?php echo $w['advance'] > 0 ? 'txt-success' : ''; ?>">₹<?php echo number_format($w['advance'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card-node">
            <div class="card-title">📋 Generated Monthly Statement Invoices</div>
            <table class="ledger-table">
                <thead>
                    <tr>
                        <th>Bill #</th>
                        <th>Personnel Profile</th>
                        <th>Month Space</th>
                        <th>Jobs Count</th>
                        <th>Net Payable Due</th>
                        <th>Action Panel</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:15px; color:#95a5a6; font-style:italic;">No custom invoice logs initialized on this structural cycle node.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <div class="card-node">
            <div class="card-title">⚙️ Statement Engine</div>
            <form method="GET" action="generate_invoice.php" target="_blank">
                <div class="field-group">
                    <label>Target Personnel Profile</label>
                    <select name="ta_name" required>
                        <option value="">-- Choose Profile --</option>
                        <?php foreach ($ta_list as $t): ?>
                            <option value="<?php echo htmlspecialchars($t['name']); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-group">
                    <label>Billing Month Space</label>
                    <input type="month" name="billing_month" value="<?php echo date('Y-m'); ?>" required>
                </div>
                <button type="submit" class="btn-statement">Generate Invoice Statement</button>
            </form>
        </div>

        <div class="card-node" style="border-top: 3px solid #27ae60;">
            <div class="card-title">💳 Cash Gateway Receipt</div>
            <form method="POST" action="accounts.php" autocomplete="off">
                <input type="hidden" name="action" value="process_payment">
                
                <div class="field-group">
                    <label>Depositing Operator</label>
                    <select name="depositing_operator" required>
                        <option value="">-- Choose Profile --</option>
                        <?php foreach ($ta_list as $t): ?>
                            <option value="<?php echo htmlspecialchars($t['name']); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field-group">
                    <label>Date of Payment</label>
                    <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="field-group">
                    <label>Mode of Payment</label>
                    <select name="payment_mode" required>
                        <option value="Cash">Cash</option>
                        <option value="PhonePe">PhonePe</option>
                        <option value="GPay">Google Pay (GPay)</option>
                        <option value="Paytm">Paytm</option>
                        <option value="NetBanking">NetBanking / IMPS</option>
                    </select>
                </div>

                <div class="field-group">
                    <label>Reference No. / UTR Code</label>
                    <input type="text" name="reference_no" placeholder="Transaction ID, UTR or N/A">
                </div>

                <div class="field-group">
                    <label>Collected Value Amount (₹)</label>
                    <input type="number" step="0.01" name="collected_amount" placeholder="0.00" required>
                </div>

                <button type="submit" class="btn-submit-token">Approve Token</button>
            </form>
        </div>
    </div>
</div>

<div class="card-node" style="width:100%; box-sizing: border-box;">
    <div class="card-title">📜 Collection History Trace Log Registry</div>
    <table class="ledger-table">
        <thead>
            <tr>
                <th style="width:60px; text-align:center;">Hash ID</th>
                <th>Depositor Target Name</th>
                <th style="width:110px;">Date of Payment</th>
                <th style="width:120px;">Mode of Payment</th>
                <th>Reference No / UTR</th>
                <th style="width:150px; text-align:right;">Deposited Cash Quantum</th>
                <th style="width:160px; text-align:center;">System Processing Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($history)): ?>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td style="text-align:center; font-family:monospace; color:#7f8c8d;">#PAY-<?php echo $h['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($h['ta_name']); ?></strong></td>
                        <td><?php echo date('d-m-Y', strtotime($h['payment_date'])); ?></td>
                        <td>
                            <span style="background:#f1f2f6; border:1px solid #dcdde1; padding:2px 6px; border-radius:3px; font-size:11px; font-weight:600;">
                                <?php echo htmlspecialchars($h['payment_mode']); ?>
                            </span>
                        </td>
                        <td style="font-family:monospace; color:#2c3e50; font-weight:600;">
                            <?php echo !empty($h['reference_no']) ? htmlspecialchars($h['reference_no']) : '<span style="color:#bdc3c7;">N/A</span>'; ?>
                        </td>
                        <td style="text-align:right; font-weight:bold; color:#27ae60;">₹<?php echo number_format($h['amount'], 2); ?></td>
                        <td style="text-align:center; color:#95a5a6; font-size:11px;"><?php echo date('d-M-Y h:i A', strtotime($h['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:25px; color:#95a5a6; font-style:italic;">No payment trace hashes found logged against current V3 schema node.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>