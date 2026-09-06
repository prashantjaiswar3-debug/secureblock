<?php
require_once 'db.php';
check_session_guard();

// Handle Form Submission Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save') {
        $name = trim($_POST['name']);
        $post = 'Technical Assistant'; // Force fixed assignment definition status
        $block = trim($_POST['block']);
        $phone = trim($_POST['phone']);
        $pass = trim($_POST['pass']);
        
        // Parse the bulk copy-paste village text box
        $bulk_villages = isset($_POST['bulk_villages']) ? trim($_POST['bulk_villages']) : '';
        $villagesArr = [];
        
        if (!empty($bulk_villages)) {
            // Split text box by line breaks
            $lines = explode("\n", str_replace("\r", "", $bulk_villages));
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                
                // Try splitting by common dividers (tabs, spaces, hyphens, commas)
                $parts = preg_split('/[\t\s\-|,]+/', trim($line), 2);
                if (count($parts) >= 2) {
                    $villagesArr[] = [
                        "code" => trim($parts[0]),
                        "name" => trim($parts[1])
                    ];
                } else if (!empty($parts[0])) {
                    $villagesArr[] = [
                        "code" => trim($parts[0]),
                        "name" => "Unnamed Village"
                    ];
                }
            }
        }
        $villagesJson = json_encode($villagesArr);

        if (!empty($_POST['id'])) {
            $stmt = $pdo->prepare("UPDATE clients SET name=?, post=?, block=?, phone=?, pass=?, villages=? WHERE id=?");
            $stmt->execute([$name, $post, $block, $phone, $pass, $villagesJson, $_POST['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO clients (name, post, block, phone, pass, villages) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $post, $block, $phone, $pass, $villagesJson]);
        }
        header("Location: index.php"); exit;
    }
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: index.php"); exit;
}

$clients = $pdo->query("SELECT * FROM clients ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Profiles Directory Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f4f7f6; margin: 0; padding: 20px; display: flex; gap: 20px; height: 93vh; color: #2c3e50; }
        .sidebar-list { width: 320px; background: white; border-radius: 8px; border: 1px solid #dcdde1; display: flex; flex-direction: column; padding: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .search-box { width: 100%; padding: 10px; box-sizing: border-box; margin-bottom: 15px; border: 1px solid #dcdde1; border-radius: 6px; }
        .client-item { padding: 12px; border-bottom: 1px solid #f1f2f6; cursor: pointer; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; }
        .client-item:hover { background: #eef2f7; }
        .workspace-panel { flex: 1; background: white; border-radius: 8px; border: 1px solid #dcdde1; padding: 25px; overflow-y: auto; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .btn { padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; }
        .btn-add { background: #2f5597; color: white; width: 100%; margin-bottom: 10px; height: 40px; font-size: 14px; border-radius: 6px; }
        .btn-save { background: #27ae60; color: white; width: 100%; padding: 12px; font-size: 15px; border-radius: 6px; }
        .btn-del { background: #e74c3c; color: white; text-decoration: none; font-size: 12px; padding: 4px 8px; border-radius: 4px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; color:#5f6368; text-transform: uppercase; font-size: 12px; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #dcdde1; border-radius: 6px; font-family: inherit; font-size: 14px; background: #fff; height: 40px; }
        .form-group textarea { height: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table th, table td { border: 1px solid #dcdde1; padding: 10px; text-align: left; font-size: 14px; }
        table th { background: #1a2a4d; color: white; }
    </style>
</head>
<body>

<div class="sidebar-list">
    <button class="btn btn-add" onclick="openAddForm()">+ Add New Profile</button>
    <input type="text" id="search" class="search-box" placeholder="Filter profiles..." oninput="filterProfiles()">
    <div id="profileContainer" style="overflow-y:auto; flex:1;">
        <?php foreach($clients as $c): ?>
            <div class="client-item" onclick='showProfile(<?php echo json_encode($c); ?>)'>
                <div><strong><?php echo htmlspecialchars($c['name']); ?></strong><br><small style="color:#7f8c8d;"><?php echo htmlspecialchars($c['post']); ?> [<?php echo htmlspecialchars($c['block']); ?>]</small></div>
                <a href="index.php?delete=<?php echo $c['id']; ?>" class="btn-del" onclick="return confirm('Delete profile record?')">Del</a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="workspace-panel" id="displayPanel">
    <h2>Select a Profile</h2>
    <p>Select an administrative profile entry from the left navigation index to view details.</p>
</div>

<script>
    function filterProfiles() {
        let q = document.getElementById('search').value.toLowerCase();
        document.querySelectorAll('.client-item').forEach(el => {
            el.style.display = el.innerText.toLowerCase().includes(q) ? 'flex' : 'none';
        });
    }

    function showProfile(client) {
        let villages = JSON.parse(client.villages || '[]');
        let rows = villages.map(v => `<tr><td><strong>${v.code}</strong></td><td>${v.name}</td></tr>`).join('');
        
        document.getElementById('displayPanel').innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #eef2f7; padding-bottom:10px; margin-bottom:20px;">
                <h2 style="margin:0; color:#1a2a4d;">${client.name}</h2>
                <button class="btn" style="background:#f39c12; color:white;" onclick='openEditForm(${JSON.stringify(client)})'>Edit Profile Data</button>
            </div>
            <p><strong>Post Designation:</strong> ${client.post}</p>
            <p><strong>Assigned Mau Block:</strong> ${client.block}</p>
            <p><strong>Phone Number:</strong> ${client.phone}</p>
            <p><strong>System Password Key:</strong> <code style="background:#eef2f7; padding:2px 6px; border-radius:4px;">${client.pass}</code></p>
            <h3>Assigned Gram panchayats Mapping List (${villages.length})</h3>
            <table><thead><tr><th>Gram Panchayat Code</th><th>Gram Panchayat Name</th></tr></thead><tbody>${rows || '<tr><td colspan="2">No mapped indices.</td></tr>'}</tbody></table>
        `;
    }

    function openAddForm() {
        document.getElementById('displayPanel').innerHTML = getFormHTML({}, 'Add New Profile Entry Record');
    }

    function openEditForm(client) {
        document.getElementById('displayPanel').innerHTML = getFormHTML(client, 'Modify Profile Structural Data Schema');
    }

    function getFormHTML(c, title) {
        let id = c.id || ''; let name = c.name || ''; let block = c.block || ''; let phone = c.phone || ''; let pass = c.pass || '';
        let villages = JSON.parse(c.villages || '[]');
        
        let bulkText = villages.map(v => `${v.code}\t${v.name}`).join('\n');

        // Target lists for Mau District Blocks
        const blocks = ["BADRAON", "DOHRI GHAT", "FATEHPUR MANDAON", "GHOSI", "KOPAGANJ", "MUHAMMADABAD GOHANA", "PARDAHA", "RANIPUR", "RATANPURA"];
        
        let optionsHtml = blocks.map(b => `<option value="${b}" ${block.toUpperCase() === b ? 'selected' : ''}>${b}</option>`).join('');

        return `
            <h2>${title}</h2>
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="${id}">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                    <div class="form-group"><label>Employee Full Name</label><input type="text" name="name" value="${name}" placeholder="Enter name" required></div>
                    <div class="form-group"><label>Designation (Locked)</label><input type="text" value="Technical Assistant" readonly style="background:#e9ecef; color:#495057; font-weight:600;"></div>
                    <div class="form-group">
                        <label>Assigned Mau Block</label>
                        <select name="block" required>
                            <option value="">-- Select Block --</option>
                            ${optionsHtml}
                        </select>
                    </div>
                    <div class="form-group"><label>Phone Register Number</label><input type="text" name="phone" value="${phone}" placeholder="Enter 10 digit number" required></div>
                    <div class="form-group"><label>Portal Workspace Security Key</label><input type="text" name="pass" value="${pass}" placeholder="Set login password" required></div>
                </div>
                
                <div class="form-group" style="margin-top:15px;">
                    <label style="color:#2f5597; font-size:14px;">📋 Bulk Copy & Paste Villages Box (Code & Name)</label>
                    <p style="margin:0 0 8px 0; font-size:12px; color:#7f8c8d;">Paste list directly from Excel/Text. Format: "Code [space/tab] Name" (One per line)</p>
                    <textarea name="bulk_villages" rows="8" placeholder="Example:\n234001   Village Name A\n234002   Village Name B">${bulkText}</textarea>
                </div>
                
                <button type="submit" class="btn btn-save">Commit Save Update</button>
            </form>
        `;
    }
</script>
</body>
</html>