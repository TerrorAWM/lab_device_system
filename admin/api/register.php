<?php
/**
 * 管理员注册 API（开发期）
 * 
 * POST /admin/api/register.php - 注册管理员
 * 
 * ⚠️ 生产环境应关闭此接口
 */

declare(strict_types=1);

require_once __DIR__ . '/_init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respError('请求方法不允许', 1, 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';
$realName = trim($input['real_name'] ?? '');
$role = $input['role'] ?? 'device';
$phone = trim($input['phone'] ?? '');

if (empty($username)) {
    respError('用户名不能为空');
}

if (strlen($password) < 6) {
    respError('密码至少6位');
}

if (empty($realName)) {
    respError('真实姓名不能为空');
}

// 验证角色
$validRoles = ['supervisor', 'device', 'finance'];
if (!in_array($role, $validRoles)) {
    respError('无效的角色类型');
}

$pdo = getDB();

// 检查用户名是否已存在
$stmt = $pdo->prepare('SELECT admin_id FROM t_admin WHERE username = ?');
$stmt->execute([$username]);
if ($stmt->fetch()) {
    respError('用户名已存在');
}

// 插入管理员
$stmt = $pdo->prepare('
    INSERT INTO t_admin (username, password, real_name, role, phone, status)
    VALUES (?, ?, ?, ?, ?, 1)
');
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$stmt->execute([$username, $hashedPassword, $realName, $role, $phone ?: null]);
$adminId = (int)$pdo->lastInsertId();

respOK([
    'admin_id' => $adminId,
    'username' => $username,
    'role' => $role
], '注册成功');
