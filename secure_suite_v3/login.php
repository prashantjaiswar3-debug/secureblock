<?php
require_once 'db.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$error_msg = '';

if (isset($_SESSION['v3_admin_session']) && $_SESSION['v3_admin_session'] === 'authenticated') {
    header("Location: app.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($username === 'admin' && $password === 'secure123') {
        $_SESSION['v3_admin_session'] = 'authenticated';
        try {
            $stmt = $pdo->prepare("
                INSERT INTO application_registry (registry_key, registry_value) 
                VALUES ('v3_admin_session', '\"authenticated\"') 
                ON DUPLICATE KEY UPDATE registry_value = '\"authenticated\"'
            ");
            $stmt->execute();
        } catch(\Exception $e){}
        header("Location: app.php");
        exit;
    } else {
        $error_msg = 'Invalid administrative master security credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SECURE V3 - Management Login Terminal</title>
    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #2c3e50; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); width: 100%; max-width: 400px; box-sizing: border-box; }
        h2 { margin: 0 0 5px 0; color: #1a2a4d; font-size: 22px; text-align: center; letter-spacing: 0.5px; }
        p { margin: 0 0 25px 0; font-size: 13px; color: #7f8c8d; text-align: center; }
        .form-group { display: flex; flex-direction: column; margin-bottom: 15px; }
        label { font-size: 11px; font-weight: 700; color: #2c3e50; text-transform: uppercase; margin-bottom: 5px; }
        input { padding: 12px; border: 1px solid #dcdde1; border-radius: 5px; font-size: 14px; background: #fafafa; }
        input:focus { border-color: #2f5597; outline: none; background: white; }
        .btn-login { background: #2f5597; color: white; border: none; padding: 12px; border-radius: 5px; width: 100%; font-weight: 600; cursor: pointer; text-transform: uppercase; margin-top: 10px; }
        .btn-login:hover { background: #1a2a4d; }
        .error { color: #c0392b; background: #fdf2f2; border: 1px solid #f9acd0; text-align: center; font-size: 13px; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="login-card">
    <h2>SECURE V3 MANAGEMENT ENGINE</h2>
    <p>Isolated Client Workspace Gateway</p>
    <?php if(!empty($error_msg)): ?>
        <div class="error"><?php echo $error_msg; ?></div>
    <?php endif; ?>
    <form method="POST" action="login.php">
        <div class="form-group">
            <label>Admin User Account ID</label>
            <input type="text" name="username" required placeholder="User ID">
        </div>
        <div class="form-group">
            <label>Security Access Token Password</label>
            <input type="password" name="password" required placeholder="Security Password">
        </div>
        <button type="submit" class="btn-login">Open Session</button>
    </form>
</div>
</body>
</html>