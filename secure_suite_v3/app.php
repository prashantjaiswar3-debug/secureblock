<?php
require_once 'db.php';
check_session_guard();

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION['admin_session'] = '';
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Secure V3 - Integrated PHP Workspace Platform</title>
    <style>
        :root { 
            --primary: #2f5597;
            --secondary: #4472c4;
            --accent: #eef2f7;
            --header-bg: #1a2a4d;
            --sidebar-bg: #2c3e50;
            --text-dark: #2c3e50;
            --border: #dcdde1;
        }
        body { 
            font-family: 'Segoe UI', system-ui, sans-serif; 
            background-color: #f4f7f6;
            color: var(--text-dark);
            margin: 0;
            padding: 0;
            display: grid;
            grid-template-rows: 60px 1fr 30px;
            grid-template-columns: 260px 1fr;
            grid-template-areas: "header header" "sidebar main" "footer footer";
            height: 100vh;
            overflow: hidden;
        }
        header { 
            grid-area: header; 
            background-color: var(--header-bg); 
            color: white; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 0 20px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 10;
        }
        .sidebar { 
            grid-area: sidebar; 
            background-color: var(--sidebar-bg); 
            padding-top: 15px; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between;
            border-right: 1px solid var(--border);
        }
        .menu-group { display: flex; flex-direction: column; gap: 2px; }
        .menu-item { 
            padding: 14px 20px; 
            color: #bdc3c7; 
            text-decoration: none; 
            font-size: 14px; 
            font-weight: 600; 
            display: flex; 
            align-items: center; 
            gap: 10px;
            cursor: pointer;
            border-left: 4px solid transparent;
            transition: all 0.15s ease;
        }
        .menu-item:hover, .menu-item.active { 
            background-color: #34495e; 
            color: white; 
            border-left-color: var(--secondary);
        }
        .logout-btn { 
            background-color: #922b21; 
            color: white; 
            margin: 15px; 
            padding: 10px; 
            border-radius: 4px; 
            text-align: center; 
            font-size: 12px; 
            text-transform: uppercase; 
            font-weight: bold;
            border-left: none;
        }
        .logout-btn:hover { background-color: #b03a2e; }
        main { grid-area: main; background-color: #f4f7f6; position: relative; }
        iframe { width: 100%; height: 100%; border: none; }
        footer { 
            grid-area: footer; 
            background-color: #eef2f7; 
            border-top: 1px solid var(--border); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 0 20px; 
            font-size: 12px; 
            color: #7f8c8d; 
            font-weight: 600; 
        }
    </style>
</head>
<body>

<header>
    <div style="font-weight: 800; font-size: 18px; letter-spacing: 0.5px;">
        SECURE V3 <span style="font-weight: 400; color: #bdc3c7; font-size: 13px;">(ISOLATED METRIC NODE)</span>
    </div>
    <div id="topHeaderClock" style="font-weight: 600; font-size: 14px; color: #aed6f1;"></div>
</header>

<div class="sidebar">
    <div class="menu-group">
        <div class="menu-item active" onclick="routeFrame('dashboard.php', this)">📊 System Dashboard</div>
        <div class="menu-item" onclick="routeFrame('index.php', this)">👥 Profile Directory</div>
        <div class="menu-item" onclick="routeFrame('tracker.php', this)">📈 Progress Tracking</div>
        <div class="menu-item" onclick="routeFrame('accounts.php', this)">💰 Ledger Accounts</div>
        <div class="menu-item" onclick="routeFrame('report.php', this)">📝 Estimate Generator</div>
    </div>
    
    <a href="app.php?action=logout" class="menu-item logout-btn" onclick="return confirm('Exit active system session?')">🔑 Close Session</a>
</div>

<main>
    <iframe id="mainIframeCanvas" src="dashboard.php"></iframe>
</main>

<footer>
    <div>Infrastructure: Local Integrated Cluster System V3 Sandbox Deployment</div>
    <div>Engine State: Operational / Active</div>
</footer>

<script>
function refreshHeaderClock() {
    const clockOptions = { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
    document.getElementById('topHeaderClock').innerText = "📅 " + new Date().toLocaleString('en-US', clockOptions);
}
setInterval(refreshHeaderClock, 1000);
refreshHeaderClock();

function routeFrame(targetUrl, element) {
    document.getElementById('mainIframeCanvas').src = targetUrl;
    document.querySelectorAll('.menu-item').forEach(item => item.classList.remove('active'));
    if(element) {
        element.classList.add('active');
    }
}
</script>
</body>
</html>