<?php
require_once 'db.php';
check_session_guard();

// --- PROCESS FORM SUBMISSION: NEW ENTRY & UPDATE PIPELINE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // --- SUBACTION: INITIALIZE NEW JOB ENTRY ---
    if ($_POST['action'] === 'add_job') {
        $work = trim($_POST['work_name']);
        $ta = trim($_POST['ta_name']);
        $gp = trim($_POST['gp_name']);
        $c_date = $_POST['created_date']; 
        
        $bill_standard = 300.00;
        $paid_computed = 0.00;
        $status = 'Pending';
        
        $block = '';
        if (!empty($ta)) {
            $stmt_b = $pdo->prepare("SELECT block FROM clients WHERE name = ? LIMIT 1");
            $stmt_b->execute([$ta]);
            $c_row = $stmt_b->fetch();
            if ($c_row) { $block = $c_row['block']; }
        }
        
        // Step 1: Insert row first with placeholder string definitions
        $stmt = $pdo->prepare("INSERT INTO work_tracker (work_name, ta_name, block_name, gp_name, status, bill_amount, paid_amount, received_date, estimate_number, pdf_filename) VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', '')");
        $stmt->execute([$work, $ta, $block, $gp, $status, $bill_standard, $paid_computed, $c_date]);
        
        // Step 2: Extract the freshly allocated database row primary ID
        $new_job_id = $pdo->lastInsertId();
        
        // Step 3: Compute uniform naming structure parameters (e.g., 103 -> Est-00103)
        $est_num = "Est-" . str_pad($new_job_id, 5, "0", STR_PAD_LEFT);
        $pdf_filename = $est_num . '.pdf';
        
        // Step 4: Handle physical file movement matching your strict name format structure
        if (isset($_FILES['estimate_pdf']) && $_FILES['estimate_pdf']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['estimate_pdf']['tmp_name'];
            $file_orig = basename($_FILES['estimate_pdf']['name']);
            $ext = strtolower(pathinfo($file_orig, PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                move_uploaded_file($file_tmp, 'uploads/estimates/' . $pdf_filename);
            } else {
                $pdf_filename = ''; // Reset if file type format criteria fails
            }
        } else {
            $pdf_filename = ''; // Default reset fallback logic boundary
        }
        
        // Step 5: Save matching string codes back down into database records layout
        $update_est_stmt = $pdo->prepare("UPDATE work_tracker SET estimate_number = ?, pdf_filename = ? WHERE id = ?");
        $update_est_stmt->execute([$est_num, $pdf_filename, $new_job_id]);
        
        header("Location: tracker.php");
        exit;
    }
    
    // --- SUBACTION: MODIFICATION ENGINE PIPELINE ---
    if ($_POST['action'] === 'update_job') {
        $id = intval($_POST['job_id']);
        $work = trim($_POST['work_name']);
        $c_date = $_POST['created_date'];
        $ta = trim($_POST['ta_name']);
        $gp = trim($_POST['gp_name']);
        $status = $_POST['status'];
        
        $block = '';
        if (!empty($ta)) {
            $stmt_b = $pdo->prepare("SELECT block FROM clients WHERE name = ? LIMIT 1");
            $stmt_b->execute([$ta]);
            $c_row = $stmt_b->fetch();
            if ($c_row) { $block = $c_row['block']; }
        }
        
        // Calculate dynamic filename format strictly aligned with job ID (e.g., Est-00103.pdf)
        $pdf_filename = "Est-" . str_pad($id, 5, "0", STR_PAD_LEFT) . '.pdf';
        
        if (isset($_FILES['edit_estimate_pdf']) && $_FILES['edit_estimate_pdf']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['edit_estimate_pdf']['tmp_name'];
            $file_orig = basename($_FILES['edit_estimate_pdf']['name']);
            $ext = strtolower(pathinfo($file_orig, PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                if (move_uploaded_file($file_tmp, 'uploads/estimates/' . $pdf_filename)) {
                    $stmt = $pdo->prepare("UPDATE work_tracker SET work_name = ?, received_date = ?, ta_name = ?, block_name = ?, gp_name = ?, status = ?, pdf_filename = ? WHERE id = ?");
                    $stmt->execute([$work, $c_date, $ta, $block, $gp, $status, $pdf_filename, $id]);
                    header("Location: tracker.php");
                    exit;
                }
            }
        }
        
        // Fallback execution keeps the current filename string intact if no new document asset is provided
        $stmt = $pdo->prepare("UPDATE work_tracker SET work_name = ?, received_date = ?, ta_name = ?, block_name = ?, gp_name = ?, status = ? WHERE id = ?");
        $stmt->execute([$work, $c_date, $ta, $block, $gp, $status, $id]);
        
        header("Location: tracker.php");
        exit;
    }
}

// --- FETCH WORKLIST WITH MULTI-FILTER PARAMETERS ---
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_ta = isset($_GET['filter_ta']) ? trim($_GET['filter_ta']) : '';
$filter_block = isset($_GET['filter_block']) ? trim($_GET['filter_block']) : '';

$sql = "SELECT w.*, c.phone FROM work_tracker w LEFT JOIN clients c ON w.ta_name = c.name WHERE 1=1";
$params = [];

if (!empty($search_query)) {
    $sql .= " AND (w.work_name LIKE ? OR w.estimate_number LIKE ? OR w.gp_name LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}
if (!empty($filter_ta)) {
    $sql .= " AND w.ta_name = ?";
    $params[] = $filter_ta;
}
if (!empty($filter_block)) {
    $sql .= " AND w.block_name = ?";
    $params[] = $filter_block;
}
$sql .= " ORDER BY w.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$ta_list = $pdo->query("SELECT name, block, villages, phone FROM clients ORDER BY name ASC")->fetchAll();
$all_blocks = ['FATEHPUR MANDAON', 'BADRAON', 'RATANPURA', 'DOHRIGHAT', 'GHOSI', 'KOPAGANJ', 'PARDHAHA', 'RANIPUR', 'MUHAMMADABAD GOHNA'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SECURE V3 - Progress Tracking Engine</title>
    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; background-color: #f0f2f5; margin: 20px; color: #333; }
        .ingest-card, .registry-card { background: #ffffff; border-radius: 4px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); border: 1px solid #dcdde1; padding: 20px; margin-bottom: 20px; }
        .card-title { font-size: 13px; font-weight: bold; color: #1a2a4d; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #f1f2f6; padding-bottom: 10px; margin-bottom: 15px; }
        .form-row-three { display: grid; grid-template-columns: 2fr 1.5fr 2fr; gap: 15px; margin-bottom: 15px; }
        .form-row-two { display: grid; grid-template-columns: 3fr 1.5fr 1fr; gap: 15px; margin-bottom: 15px; }
        .field-group { display: flex; flex-direction: column; }
        .field-group label { font-size: 10px; font-weight: bold; color: #5f6368; text-transform: uppercase; margin-bottom: 5px; }
        .field-group input, .field-group select { padding: 8px 10px; border: 1px solid #cfd4da; border-radius: 4px; font-size: 13px; font-weight: 500; background: #fafafa; }
        .btn-initialize { background: #27ae60; color: white; border: none; padding: 10px; border-radius: 4px; font-weight: bold; font-size: 12px; text-transform: uppercase; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; width: 100%; }
        .btn-initialize:hover { background: #219653; }
        .filter-utilities-bar { display: flex; gap: 10px; align-items: center; padding: 10px; background: #f8f9fa; border: 1px solid #e6e8eb; border-radius: 4px; margin-bottom: 15px; }
        .filter-utilities-bar input { flex: 1; padding: 6px 10px; font-size: 12px; border: 1px solid #cfd4da; border-radius: 4px; }
        .filter-utilities-bar select { padding: 5px 10px; font-size: 12px; border: 1px solid #cfd4da; border-radius: 4px; width: 160px; }
        .btn-utility { padding: 6px 12px; font-size: 12px; font-weight: bold; border: 1px solid #cfd4da; border-radius: 4px; cursor: pointer; background: #fff; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; color: #333; }
        .btn-utility:hover { background: #f1f2f6; }
        .btn-query { background: #2f5597; color: white; border-color: #2f5597; }
        .btn-excel { background: #1d7343; color: white; border-color: #1d7343; }
        .btn-print { background: #7f8c8d; color: white; border-color: #7f8c8d; }
        .ledger-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .ledger-table th { background: #1a2a4d; color: white; padding: 10px 8px; font-weight: 600; text-transform: uppercase; font-size: 11px; border: 1px solid #223661; }
        .ledger-table td { padding: 10px 8px; border: 1px solid #eef2f7; vertical-align: middle; background: #fff; }
        .ledger-table tr:nth-child(even) td { background: #fdfdfd; }
        .txt-job-id { font-family: monospace; font-weight: bold; color: #7f8c8d; text-align: center; }
        .badge-est { background: #f8f9fa; border: 1px solid #dcdde1; padding: 2px 5px; border-radius: 3px; font-family: monospace; font-weight: 600; }
        .txt-work-description { font-weight: 600; color: #212529; line-height: 1.4; }
        .badge-status { padding: 3px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; display: inline-block; border: 1px solid; }
        .status-pending { background: #fff9e6; color: #f39c12; border-color: #ffeaa7; }
        .status-completed { background: #e8f8f5; color: #117a65; border-color: #a3e4d7; }
        .action-flex-links { display: flex; gap: 6px; align-items: center; justify-content: center; }
        .btn-row-action { width: 72px; height: 26px; font-size: 11px; font-weight: 600; border-radius: 3px; cursor: pointer; text-decoration: none; border: 1px solid #cfd4da; background: #fff; color: #333; display: inline-flex; align-items: center; justify-content: center; gap: 2px; box-sizing: border-box; }
        .btn-row-edit { background: #fff9e6; border-color: #f5c068; color: #b7791f; }
        .btn-row-pdf { background: #edf2f7; border-color: #cbd5e0; color: #4a5568; }
        .btn-row-info { background: #ebf8ff; border-color: #bee3f8; color: #2b6cb0; }
        .modal-mask { position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.4); display: none; align-items: center; justify-content: center; z-index: 5000; }
        .modal-container { background: #fff; width: 460px; padding: 20px; border-radius: 4px; border: 1px solid #cbd5e0; }
        @media print {
            .ingest-card, .filter-utilities-bar, .action-flex-links, .th-actions, .td-actions { display: none !important; }
            .registry-card { border: none; padding: 0; }
        }
    </style>
</head>
<body>

<div class="ingest-card">
    <div class="card-title">Log New Entry Pipeline (Bulk Ingestion Active)</div>
    <form method="POST" action="tracker.php" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="action" value="add_job">
        
        <div class="form-row-three">
            <div class="field-group">
                <label>Technical Assistant Profile</label>
                <select id="ingest_ta" name="ta_name" onchange="handleProfileSelection(this.value, 'ingest_gp', 'ingest_block')" required>
                    <option value="">-- Choose TA Profile --</option>
                    <?php foreach ($ta_list as $t): ?>
                        <option value="<?php echo htmlspecialchars($t['name']); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field-group">
                <label>Assigned District Block</label>
                <input type="text" id="ingest_block" placeholder="Auto-resolved block..." readonly>
            </div>
            <div class="field-group">
                <label>Gram Panchayat (GP) Selection Dropdown</label>
                <select id="ingest_gp" name="gp_name" required>
                    <option value="">-- Choose Filtered GP --</option>
                </select>
            </div>
        </div>

        <div class="form-row-two">
            <div class="field-group">
                <label>Job Name Detail / Project Work Title</label>
                <input type="text" name="work_name" placeholder="Enter complete project text name details..." required>
            </div>
            <div class="field-group">
                <label>Upload Directly Merged Estimate PDF File</label>
                <input type="file" name="estimate_pdf" accept="application/pdf" style="font-size:11px; padding:5px;">
            </div>
            <div class="field-group">
                <label>Date of Creation</label>
                <input type="date" name="created_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
        </div>

        <button type="submit" class="btn-initialize">⚙️ Initialize Job</button>
    </form>
</div>

<div class="registry-card">
    <div class="card-title">Job Operations Pipeline Registry</div>
    
    <form method="GET" action="tracker.php" class="filter-utilities-bar">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search description keyword, Gram Panchayat territory, or estimate code...">
        
        <select name="filter_ta">
            <option value="">-- All Personnel --</option>
            <?php foreach ($ta_list as $t): ?>
                <option value="<?php echo htmlspecialchars($t['name']); ?>" <?php echo ($filter_ta === $t['name']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['name']); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="filter_block">
            <option value="">-- All Blocks --</option>
            <?php foreach ($all_blocks as $b): ?>
                <option value="<?php echo $b; ?>" <?php echo ($filter_block === $b) ? 'selected' : ''; ?>><?php echo $b; ?></option>
            <?php endforeach; ?>
        </select>
        
        <button type="submit" class="btn-utility btn-query">Filter Records</button>
        <?php if (!empty($search_query) || !empty($filter_ta) || !empty($filter_block)): ?>
            <a href="tracker.php" class="btn-utility">Clear</a>
        <?php endif; ?>
        
        <div style="flex-grow:1;"></div>
        
        <button type="button" class="btn-utility btn-excel" onclick="exportDataMatrixToExcel()">📊 Export List to Excel</button>
        <button type="button" class="btn-utility btn-print" onclick="window.print()">🖨️ Print List</button>
    </form>

    <div style="overflow-x: auto;">
        <table class="ledger-table" id="exportableLedgerTable">
            <thead>
                <tr>
                    <th style="width:50px; text-align:center;">Job ID</th>
                    <th style="width:85px; text-align:left;">Est. Number</th>
                    <th style="text-align:left;">KHI Name Description</th>
                    <th style="width:140px; text-align:left;">Technical Assistant</th>
                    <th style="width:130px; text-align:left;">Gram Panchayat (GP)</th>
                    <th style="width:75px; text-align:center;">Status</th>
                    <th style="width:80px; text-align:left;">Date of Creation</th>
                    <th style="width:70px; text-align:right;">Billed (₹)</th>
                    <th style="width:70px; text-align:right;">Paid (₹)</th>
                    <th class="th-actions" style="width:240px; text-align:center;">Action Access Links</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($jobs)): ?>
                    <?php foreach ($jobs as $j): ?>
                        <tr>
                            <td class="txt-job-id">#<?php echo $j['id']; ?></td>
                            <td><span class="badge-est"><?php echo htmlspecialchars($j['estimate_number']); ?></span></td>
                            <td><div class="txt-work-description"><?php echo htmlspecialchars($j['work_name']); ?></div></td>
                            <td><div style="font-weight:600; color:#1a2a4d;"><?php echo htmlspecialchars($j['ta_name']); ?></div></td>
                            <td><strong><?php echo htmlspecialchars($j['gp_name']); ?></strong></td>
                            <td style="text-align:center;">
                                <?php $cls = (strtolower($j['status']) === 'completed') ? 'status-completed' : 'status-pending'; ?>
                                <span class="badge-status <?php echo $cls; ?>"><?php echo htmlspecialchars($j['status']); ?></span>
                            </td>
                            <td><?php echo date('d-m-Y', strtotime($j['received_date'])); ?></td>
                            <td style="text-align:right; font-weight:600; color:#2c3e50;">₹<?php echo number_format($j['bill_amount'], 2); ?></td>
                            <td style="text-align:right; font-weight:600; color:#27ae60;">₹<?php echo number_format($j['paid_amount'], 2); ?></td>
                            <td class="td-actions" style="text-align:center;">
                                <div class="action-flex-links">
                                    <button class="btn-row-action btn-row-edit" onclick='triggerModificationModal(<?php echo json_encode($j, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>📝 Edit</button>
                                    <?php if (!empty($j['pdf_filename'])): ?>
                                        <a href="uploads/estimates/<?php echo $j['pdf_filename']; ?>" target="_blank" class="btn-row-action btn-row-pdf">📄 PDF</a>
                                    <?php else: ?>
                                        <button class="btn-row-action btn-row-pdf" style="opacity:0.4; cursor:not-allowed;" disabled>📄 Empty</button>
                                    <?php endif; ?>
                                    <button class="btn-row-action btn-row-info" onclick='sendWhatsAppNotification(<?php echo json_encode($j, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>💬 Info</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="text-align:center; padding:30px; color:#95a5a6; font-style:italic;">No records matched your selection configuration.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modificationModal" class="modal-mask">
    <div class="modal-container">
        <div class="card-title" style="color:#b7791f;">Modify Progress Row Instance</div>
        <form method="POST" action="tracker.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_job">
            <input type="hidden" id="modal_id" name="job_id">
            
            <div class="field-group" style="margin-bottom:12px;">
                <label>Job Description Context</label>
                <input type="text" id="modal_work" name="work_name" required>
            </div>
            <div class="field-group" style="margin-bottom:12px;">
                <label>Date Registry Link</label>
                <input type="date" id="modal_date" name="created_date" required>
            </div>
            <div class="field-group" style="margin-bottom:12px;">
                <label>Assigned Technical Assistant Profile</label>
                <select id="modal_ta" name="ta_name" onchange="handleProfileSelection(this.value, 'modal_gp', 'modal_block')" required>
                    <?php foreach ($ta_list as $t): ?>
                        <option value="<?php echo htmlspecialchars($t['name']); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field-group" style="margin-bottom:12px;">
                <label>Assigned Block Reference</label>
                <input type="text" id="modal_block" readonly style="background:#f1f2f6;">
            </div>
            <div class="field-group" style="margin-bottom:12px;">
                <label>Target Gram Panchayat Territory</label>
                <select id="modal_gp" name="gp_name" required></select>
            </div>
            <div class="field-group" style="margin-bottom:12px;">
                <label>Operational Processing Status</label>
                <select id="modal_status" name="status" required>
                    <option value="Pending">Pending</option>
                    <option value="Completed">Completed</option>
                </select>
            </div>
            <div class="field-group" style="margin-bottom:15px;">
                <label>Upload / Change Estimate PDF File</label>
                <input type="file" name="edit_estimate_pdf" accept="application/pdf" style="font-size:11px; padding:5px;">
            </div>
            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn-utility" onclick="dismissModificationModal()">Cancel</button>
                <button type="submit" class="btn-utility btn-query" style="background:#b7791f; border-color:#b7791f;">Commit Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    const coreProfileReferenceLedger = <?php echo json_encode($ta_list); ?>;

    function handleProfileSelection(taName, gpSelectId, blockInputId, preSelectedGp = '') {
        const gpDropdown = document.getElementById(gpSelectId);
        const blockInput = document.getElementById(blockInputId);
        if(!gpDropdown) return;
        gpDropdown.innerHTML = '';
        
        if (!taName) {
            if(blockInput) blockInput.value = '';
            const opt = document.createElement('option');
            opt.value = ''; opt.text = '-- Choose Filtered GP --';
            gpDropdown.appendChild(opt);
            return;
        }
        
        const targetObj = coreProfileReferenceLedger.find(item => item.name === taName);
        if (targetObj) {
            if(blockInput) blockInput.value = targetObj.block;
            let subVillages = [];
            try {
                if (targetObj.villages) { subVillages = JSON.parse(targetObj.villages); }
            } catch(e) { console.error(e); }
            
            if (Array.isArray(subVillages) && subVillages.length > 0) {
                const promptOpt = document.createElement('option');
                promptOpt.value = ''; promptOpt.text = '-- Choose Filtered GP --';
                gpDropdown.appendChild(promptOpt);
                
                subVillages.forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = v.name; opt.text = v.name;
                    if (preSelectedGp && v.name === preSelectedGp) { opt.selected = true; }
                    gpDropdown.appendChild(opt);
                });
            }
        }
    }

    function triggerModificationModal(data) {
        document.getElementById('modal_id').value = data.id;
        document.getElementById('modal_work').value = data.work_name;
        document.getElementById('modal_date').value = data.received_date;
        document.getElementById('modal_ta').value = data.ta_name;
        document.getElementById('modal_status').value = data.status;
        handleProfileSelection(data.ta_name, 'modal_gp', 'modal_block', data.gp_name);
        document.getElementById('modificationModal').style.display = 'flex';
    }

    function dismissModificationModal() {
        document.getElementById('modificationModal').style.display = 'none';
    }

    function sendWhatsAppNotification(job) {
        const cleanPhone = job.phone ? job.phone.replace(/[^0-9]/g, "") : "";
        const messageText = `Work Name: ${job.work_name}\n` +
                            `GP: ${job.gp_name}\n` +
                            `File No: ${job.estimate_number || 'N/A'}`;
        const encodedText = encodeURIComponent(messageText);
        
        let nativeUri = `whatsapp://send?text=${encodedText}`;
        if (cleanPhone) {
            nativeUri = `whatsapp://send?phone=${cleanPhone}&text=${encodedText}`;
        }
        window.location.href = nativeUri;
    }

    function exportDataMatrixToExcel() {
        const originalTable = document.getElementById("exportableLedgerTable");
        const clonedTable = originalTable.cloneNode(true);
        const rows = clonedTable.getElementsByTagName("tr");
        for (let i = 0; i < rows.length; i++) {
            if (rows[i].cells.length > 0) { rows[i].deleteCell(-1); }
        }
        const htmlContextString = clonedTable.outerHTML;
        const fileContentBlobUri = 'data:application/vnd.ms-excel,' + encodeURIComponent(htmlContextString);
        const downloadAnchorNode = document.createElement("a");
        document.body.appendChild(downloadAnchorNode);
        downloadAnchorNode.href = fileContentBlobUri;
        downloadAnchorNode.download = "Operational_Registry_Report_" + new Date().toISOString().slice(0,10) + ".xls";
        downloadAnchorNode.click();
        document.body.removeChild(downloadAnchorNode);
    }
</script>
</body>
</html>