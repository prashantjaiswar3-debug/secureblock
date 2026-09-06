<?php
require_once 'db.php';
check_session_guard();

// 1. Fetch all available profiles from the actual 'clients' table using PDO
try {
    $stmt_all = $pdo->query("SELECT id, name, post, block, phone FROM clients ORDER BY name ASC");
    $profiles = $stmt_all->fetchAll();
} catch (PDOException $e) {
    die("Data compilation error: " . $e->getMessage());
}

// 2. Resolve selected profile ID
$selected_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($selected_id === 0 && !empty($profiles)) {
    $selected_id = $profiles[0]['id'];
}

// 3. Handle profile deletion if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_profile'])) {
    $delete_id = intval($_POST['profile_id']);
    try {
        $stmt_del = $pdo->prepare("DELETE FROM clients WHERE id = ?");
        $stmt_del->execute([$delete_id]);
    } catch (PDOException $e) {
        // Silent catch or display error if foreign constraints prevent deletion
    }
    header("Location: profiles.php");
    exit;
}

// 4. Fetch specific details for the active profile
$active_profile = null;
if ($selected_id > 0) {
    $stmt_active = $pdo->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
    $stmt_active->execute([$selected_id]);
    $active_profile = $stmt_active->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SECURE V2 - Profile Directory</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f0f2f5; display: flex; height: 100vh; overflow: hidden; }
        
        .sidebar-panel { width: 320px; background-color: #ffffff; border-right: 1px solid #dcdde1; display: flex; flex-direction: column; }
        .search-container { padding: 15px; border-bottom: 1px solid #f1f2f6; }
        .search-box { width: 100%; padding: 10px; border: 1px solid #dcdde1; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
        
        .profile-list { flex: 1; overflow-y: auto; padding: 10px; }
        .profile-card-item { display: flex; align-items: center; padding: 12px; margin-bottom: 8px; background: #fff; border: 1px solid #e6e8eb; border-radius: 8px; cursor: pointer; transition: all 0.2s; text-decoration: none; color: inherit; }
        .profile-card-item:hover { background: #f8f9fa; border-color: #bccee7; }
        .profile-card-item.active { background: #eef2f7; border-color: #2f5597; }
        
        .avatar-circle { width: 42px; height: 42px; background: #2f5597; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 16px; margin-right: 12px; flex-shrink: 0; }
        .profile-info-meta { flex: 1; min-width: 0; }
        .profile-title-name { font-weight: 600; font-size: 14px; color: #2c3e50; margin: 0 0 3px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .profile-subtitle-tag { font-size: 12px; color: #7f8c8d; margin: 0; }

        .main-display-panel { flex: 1; display: flex; flex-direction: column; background: #f8f9fa; }
        .workspace-canvas { flex: 1; overflow-y: auto; padding: 30px; box-sizing: border-box; }
        
        .detail-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 25px; border: 1px solid #e6e8eb; max-width: 800px; margin: 0 auto; }
        .detail-header { display: flex; align-items: center; border-bottom: 2px solid #f1f2f6; padding-bottom: 20px; margin-bottom: 20px; }
        .detail-avatar { width: 70px; height: 70px; background: #4472c4; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: bold; margin-right: 20px; }
        .detail-header-text h2 { margin: 0 0 5px 0; color: #1a2a4d; font-size: 22px; }
        .detail-header-text p { margin: 0; color: #7f8c8d; font-size: 14px; }
        
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        .info-group { background: #fdfdfd; padding: 12px 15px; border: 1px solid #f1f2f6; border-radius: 6px; }
        .info-label { font-size: 11px; text-transform: uppercase; color: #95a5a6; font-weight: bold; margin-bottom: 4px; }
        .info-value { font-size: 15px; color: #2c3e50; font-weight: 500; }
        
        .table-section { margin-top: 25px; }
        .table-section h3 { font-size: 16px; color: #2f5597; margin-bottom: 12px; border-left: 4px solid #2f5597; padding-left: 8px; }
        .village-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 6px; overflow: hidden; border: 1px solid #e6e8eb; }
        .village-table th { background: #1a2a4d; color: #ffffff; text-align: left; padding: 10px 12px; font-size: 13px; font-weight: 500; }
        .village-table td { padding: 10px 12px; font-size: 13px; border-bottom: 1px solid #f1f2f6; color: #34495e; }
        .village-table tr:last-child td { border-bottom: none; }
        .village-table tr:nth-child(even) { background: #f8f9fa; }
        
        .action-tray { margin-top: 30px; display: flex; justify-content: flex-end; border-top: 1px solid #f1f2f6; padding-top: 20px; }
        .btn-delete { background: #e74c3c; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px; transition: background 0.2s; }
        .btn-delete:hover { background: #c0392b; }

        .footer-bar { height: 30px; background: #1a2a4d; color: #a4b0be; display: flex; align-items: center; justify-content: space-between; padding: 0 15px; font-size: 11px; }
    </style>
</head>
<body>

    <div class="sidebar-panel">
        <div class="search-container">
            <input type="text" id="profileSearch" class="search-box" onkeyup="filterProfiles()" placeholder="Search staff by name or block...">
        </div>
        
        <div class="profile-list" id="profileListContainer">
            <?php if (!empty($profiles)): ?>
                <?php foreach ($profiles as $p): ?>
                    <?php 
                        $initials = '';
                        $parts = explode(' ', $p['name']);
                        foreach ($parts as $part) {
                            $initials .= strtoupper(substr($part, 0, 1));
                        }
                        $initials = substr($initials, 0, 2);
                    ?>
                    <a href="profiles.php?id=<?php echo $p['id']; ?>" class="profile-card-item <?php echo ($p['id'] == $selected_id) ? 'active' : ''; ?>">
                        <div class="avatar-circle"><?php echo htmlspecialchars($initials); ?></div>
                        <div class="profile-info-meta">
                            <div class="profile-title-name"><?php echo htmlspecialchars($p['name']); ?></div>
                            <div class="profile-subtitle-tag"><?php echo htmlspecialchars($p['block']); ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center; padding:20px; color:#95a5a6; font-style:italic; font-size:13px;">No personnel records found.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="main-display-panel">
        <div class="workspace-canvas">
            <?php if ($active_profile): ?>
                <div class="detail-card">
                    <div class="detail-header">
                        <div class="detail-avatar">
                            <?php 
                                $main_initial = strtoupper(substr($active_profile['name'], 0, 1));
                                echo htmlspecialchars($main_initial);
                            ?>
                        </div>
                        <div class="detail-header-text">
                            <h2><?php echo htmlspecialchars($active_profile['name']); ?></h2>
                            <p><?php echo htmlspecialchars($active_profile['post']); ?></p>
                        </div>
                    </div>

                    <div class="info-grid">
                        <div class="info-group">
                            <div class="info-label">Assigned Block Jurisdiction</div>
                            <div class="info-value"><?php echo htmlspecialchars($active_profile['block']); ?></div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Mobile Registration Number</div>
                            <div class="info-value">📞 <?php echo htmlspecialchars($active_profile['phone']); ?></div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Portal Workspace Security Token</div>
                            <div class="info-value" style="font-family: monospace; letter-spacing: 0.5px;"><?php echo htmlspecialchars($active_profile['pass']); ?></div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Record Index ID</div>
                            <div class="info-value">#00<?php echo htmlspecialchars($active_profile['id']); ?></div>
                        </div>
                    </div>

                    <div class="table-section">
                        <h3>Mapped Gram Panchayat (GP) Territories</h3>
                        <table class="village-table">
                            <thead>
                                <tr>
                                    <th style="width: 30%;">Territory Census Code</th>
                                    <th>Gram Panchayat Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $village_list = [];
                                if (!empty($active_profile['villages'])) {
                                    $village_list = json_decode($active_profile['villages'], true);
                                }
                                
                                if (is_array($village_list) && !empty($village_list)): 
                                    foreach ($village_list as $v):
                                ?>
                                    <tr>
                                        <td style="font-family: monospace; font-weight: 600; color: #7f8c8d;"><?php echo htmlspecialchars($v['code'] ?? 'N/A'); ?></td>
                                        <td><strong><?php echo htmlspecialchars($v['name'] ?? 'Unnamed Location'); ?></strong></td>
                                    </tr>
                                <?php 
                                    endforeach; 
                                else: 
                                ?>
                                    <tr>
                                        <td colspan="2" style="text-align:center; color:#95a5a6; font-style:italic; padding:15px;">No village assignments declared for this profile.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="action-tray">
                        <form method="POST" onsubmit="return confirm('Are you sure you want to completely erase this user account profile directory item? This cannot be undone.');">
                            <input type="hidden" name="profile_id" value="<?php echo $active_profile['id']; ?>">
                            <button type="submit" name="delete_profile" class="btn-delete">Revoke & Delete Profile Record</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div style="text-align:center; padding-top:100px; color:#5f6368; font-size: 14px;">Select an employee assistant profile item view from the left panel list.</div>
            <?php endif; ?>
        </div>

        <div class="footer-bar">
            <div>Infrastructure Engine: PHP 8.2 / Native PDO Pipeline</div>
            <div>System Status: Secured / Active</div>
        </div>
    </div>

    <script>
        function filterProfiles() {
            const input = document.getElementById('profileSearch');
            const filter = input.value.toUpperCase();
            const container = document.getElementById("profileListContainer");
            const items = container.getElementsByClassName('profile-card-item');

            for (let i = 0; i < items.length; i++) {
                const textValue = items[i].textContent || items[i].innerText;
                if (textValue.toUpperCase().indexOf(filter) > -1) {
                    items[i].style.display = "";
                } else {
                    items[i].style.display = "none";
                }
            }
        }
    </script>
</body>
</html>