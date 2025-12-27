<?php
/**
 * 管理员登录 API
 * 
 * POST /admin/api/login.php - 管理员登录
 * POST /admin/api/login.php?action=logout - 退出登录
 */

declare(strict_types=1);

require_once __DIR__ . '/_init.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'logout':
        handleLogout();
        break;
    default:
        handleLogin();
        break;
}

/**
 * 处理登录
 */
function handleLogin(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respError('请求方法不允许', 1, 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($username) || empty($password)) {
        respError('用户名和密码不能为空');
    }

    $pdo = getDB();
    
    // 查询管理员
    $stmt = $pdo->prepare('SELECT * FROM t_admin WHERE username = ? AND status = 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if (!$admin) {
        respError('用户名或密码错误');
    }

    // 验证密码（开发阶段使用明文）
    if ($password !== $admin['password']) {
        respError('用户名或密码错误');
    }

    // 生成 Token
    $token = generateToken();
    $expireTime = date('Y-m-d H:i:s', strtotime('+7 days'));

    // 存储 Token
    $stmt = $pdo->prepare('INSERT INTO t_admin_token (admin_id, token, expire_time) VALUES (?, ?, ?)');
    $stmt->execute([$admin['admin_id'], $token, $expireTime]);

    respOK([
        'token' => $token,
        'expires_at' => $expireTime,
        'admin' => [
            'id' => $admin['admin_id'],
            'username' => $admin['username'],
            'real_name' => $admin['real_name'],
            'role' => $admin['role']
        ]
    ], '登录成功');
}

/**
 * 处理退出登录
 */
function handleLogout(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respError('请求方法不允许', 1, 405);
    }

    $token = getBearerToken();
    
    if (!$token) {
        respUnauthorized('缺少认证令牌');
    }

    $pdo = getDB();
    $stmt = $pdo->prepare('DELETE FROM t_admin_token WHERE token = ?');
    $stmt->execute([$token]);

    respOK(null, '退出成功');
}
