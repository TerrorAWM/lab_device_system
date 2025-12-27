<?php
/**
 * 用户注册 API
 * 
 * POST /api/register.php - 注册新用户
 */

declare(strict_types=1);

require_once __DIR__ . '/_init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respError('请求方法不允许', 1, 405);
}

$input = json_decode(file_get_contents('php://input'), true);

// 基础参数验证
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';
$realName = trim($input['real_name'] ?? '');
$userType = $input['user_type'] ?? '';
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

$validTypes = ['teacher', 'student', 'external'];
if (!in_array($userType, $validTypes)) {
    respError('无效的用户类型');
}

$pdo = getDB();

// 检查用户名是否已存在
$stmt = $pdo->prepare('SELECT user_id FROM t_user WHERE username = ?');
$stmt->execute([$username]);
if ($stmt->fetch()) {
    respError('用户名已存在');
}

try {
    $pdo->beginTransaction();

    // 插入用户主表
    $stmt = $pdo->prepare('
        INSERT INTO t_user (username, password, real_name, role, user_type, phone, status)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ');
    $stmt->execute([$username, $password, $realName, 'user', $userType, $phone ?: null]);
    $userId = (int)$pdo->lastInsertId();

    // 根据用户类型插入扩展表
    switch ($userType) {
        case 'teacher':
            $title = trim($input['title'] ?? '');
            $college = trim($input['college'] ?? '');
            $researchArea = trim($input['research_area'] ?? '');

            $stmt = $pdo->prepare('
                INSERT INTO t_user_teacher (user_id, title, college, research_area)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([$userId, $title ?: null, $college ?: null, $researchArea ?: null]);
            break;

        case 'student':
            $studentNo = trim($input['student_no'] ?? '');
            $major = trim($input['major'] ?? '');
            $college = trim($input['college'] ?? '');
            $advisorId = !empty($input['advisor_id']) ? (int)$input['advisor_id'] : null;

            if (empty($studentNo)) {
                throw new Exception('学号不能为空');
            }

            // 检查学号是否已存在
            $stmt = $pdo->prepare('SELECT user_id FROM t_user_student WHERE student_no = ?');
            $stmt->execute([$studentNo]);
            if ($stmt->fetch()) {
                throw new Exception('学号已存在');
            }

            $stmt = $pdo->prepare('
                INSERT INTO t_user_student (user_id, student_no, major, college, advisor_id)
                VALUES (?, ?, ?, ?, ?)
            ');
            $stmt->execute([$userId, $studentNo, $major ?: null, $college ?: null, $advisorId]);
            break;

        case 'external':
            $organization = trim($input['organization'] ?? '');
            $identityCard = trim($input['identity_card'] ?? '');

            $stmt = $pdo->prepare('
                INSERT INTO t_user_external (user_id, organization, identity_card)
                VALUES (?, ?, ?)
            ');
            $stmt->execute([$userId, $organization ?: null, $identityCard ?: null]);
            break;
    }

    $pdo->commit();

    respOK([
        'user_id' => $userId,
        'username' => $username
    ], '注册成功');

} catch (Exception $e) {
    $pdo->rollBack();
    respError($e->getMessage());
}
