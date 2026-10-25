<?php
require_once __DIR__ . '/auth.php';
initUserTable();

$redirect = $_GET['redirect'] ?? 'xiaochengxu.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $action = $_POST['action'] ?? 'login';

    if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
        $error = '请输入正确的手机号';
    } elseif (strlen($password) < 6) {
        $error = '密码至少 6 位';
    } else {
        $conn = getDB();

        if ($action === 'register') {
            $check = $conn->query("SELECT id FROM users WHERE phone='$phone'");
            if ($check->num_rows > 0) {
                $error = '该手机号已注册，请直接登录';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (phone, password) VALUES (?, ?)");
                $stmt->bind_param("ss", $phone, $hashed);
                if ($stmt->execute()) {
                    setcookie('user_phone', $phone, time() + 86400 * 30, '/');
                    header('Location: ' . $redirect);
                    exit;
                } else {
                    $error = '注册失败，请重试';
                }
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("SELECT password FROM users WHERE phone=?");
            $stmt->bind_param("s", $phone);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if (password_verify($password, $row['password'])) {
                    setcookie('user_phone', $phone, time() + 86400 * 30, '/');
                    header('Location: ' . $redirect);
                    exit;
                } else {
                    $error = '密码错误';
                }
            } else {
                $error = '该手机号未注册，请先注册';
            }
            $stmt->close();
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 扫码点餐</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, sans-serif; background: #f5f5f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-box { background: #fff; padding: 30px 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); width: 100%; max-width: 400px; }
        .logo { text-align: center; font-size: 40px; margin-bottom: 10px; }
        h2 { text-align: center; color: #333; margin-bottom: 25px; font-size: 20px; }
        .tab { display: flex; margin-bottom: 20px; border-bottom: 2px solid #eee; }
        .tab div { flex: 1; text-align: center; padding: 10px; cursor: pointer; color: #999; font-size: 16px; }
        .tab div.active { color: #e74c3c; border-bottom: 2px solid #e74c3c; margin-bottom: -2px; font-weight: bold; }
        input { width: 100%; padding: 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; margin-bottom: 15px; }
        input:focus { outline: none; border-color: #e74c3c; }
        button { width: 100%; padding: 14px; background: #e74c3c; color: #fff; border: none; border-radius: 8px; font-size: 17px; cursor: pointer; }
        button:active { background: #c0392b; }
        .msg { padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; text-align: center; }
        .error { background: #fde8e8; color: #c0392b; }
        .footer { text-align: center; margin-top: 20px; color: #999; font-size: 13px; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="logo">🍽️</div>
        <h2>扫码点餐</h2>

        <div class="tab">
            <div id="tab-login" class="active" onclick="switchTab('login')">登录</div>
            <div id="tab-register" onclick="switchTab('register')">注册</div>
        </div>

        <?php if ($error): ?>
            <div class="msg error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" id="login-form">
            <input type="hidden" name="action" id="form-action" value="login">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
            <input type="tel" name="phone" placeholder="请输入手机号" maxlength="11" required>
            <input type="password" name="password" placeholder="请输入密码（至少6位）" required>
            <button type="submit" id="submit-btn">登 录</button>
        </form>

        <div class="footer">首次使用请先注册</div>
    </div>

    <script>
        function switchTab(type) {
            document.getElementById('tab-login').classList.toggle('active', type === 'login');
            document.getElementById('tab-register').classList.toggle('active', type === 'register');
            document.getElementById('form-action').value = type;
            document.getElementById('submit-btn').textContent = type === 'login' ? '登 录' : '注 册';
        }
    </script>
</body>
</html>