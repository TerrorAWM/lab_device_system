<?php
/**
 * 用户登录 API
 * 
 * POST /api/login.php - 用户登录
 * POST /api/login.php?action=logout - 退出登录
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
    
    // 查询用户
    $stmt = $pdo->prepare('SELECT * FROM t_user WHERE username = ? AND status = 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        respError('用户名或密码错误');
    }

    // 验证密码（开发阶段使用明文，生产环境应使用 password_verify）
    if ($password !== $user['password']) {
        respError('用户名或密码错误');
    }

    // 生成 Token
    $token = generateToken();
    $expireTime = date('Y-m-d H:i:s', strtotime('+7 days'));

    // 存储 Token
    $stmt = $pdo->prepare('INSERT INTO t_user_token (user_id, token, expire_time) VALUES (?, ?, ?)');
    $stmt->execute([$user['user_id'], $token, $expireTime]);

    // 获取用户扩展信息
    $userInfo = getUserExtendedInfo($pdo, $user);

    // 移除敏感信息
    unset($userInfo['password']);

    respOK([
        'token' => $token,
        'expires_at' => $expireTime,
        'user' => $userInfo
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
    $stmt = $pdo->prepare('DELETE FROM t_user_token WHERE token = ?');
    $stmt->execute([$token]);

    respOK(null, '退出成功');
}

/**
 * 获取用户扩展信息
 */
function getUserExtendedInfo(PDO $pdo, array $user): array
{
    $userInfo = [
        'user_id' => $user['user_id'],
        'username' => $user['username'],
        'real_name' => $user['real_name'],
        'user_type' => $user['user_type'],
        'phone' => $user['phone'],
        'created_at' => $user['created_at']
    ];

    switch ($user['user_type']) {
        case 'teacher':
            $stmt = $pdo->prepare('SELECT title, college, research_area FROM t_user_teacher WHERE user_id = ?');
            $stmt->execute([$user['user_id']]);
            $ext = $stmt->fetch();
            if ($ext) {
                $userInfo = array_merge($userInfo, $ext);
            }
            break;

        case 'student':
            $stmt = $pdo->prepare('
                SELECT s.student_no, s.major, s.college, s.advisor_id, u.real_name as advisor_name
                FROM t_user_student s
                LEFT JOIN t_user u ON s.advisor_id = u.user_id
                WHERE s.user_id = ?
            ');
            $stmt->execute([$user['user_id']]);
            $ext = $stmt->fetch();
            if ($ext) {
                $userInfo = array_merge($userInfo, $ext);
            }
            break;

        case 'external':
            $stmt = $pdo->prepare('SELECT organization, identity_card FROM t_user_external WHERE user_id = ?');
            $stmt->execute([$user['user_id']]);
            $ext = $stmt->fetch();
            if ($ext) {
                $userInfo = array_merge($userInfo, $ext);
            }
            break;
    }

    return $userInfo;
}
