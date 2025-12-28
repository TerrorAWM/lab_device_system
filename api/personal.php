<?php
/**
 * 个人信息 API
 * 
 * GET /api/personal.php - 获取个人信息
 * POST /api/personal.php?action=update - 更新个人信息
 * POST /api/personal.php?action=change_password - 修改密码
 */

declare(strict_types=1);

require_once __DIR__ . '/_init.php';

$user = requireAuth();
$action = $_GET['action'] ?? '';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        getPersonalInfo($user);
        break;
    case 'POST':
        switch ($action) {
            case 'update':
                updatePersonalInfo($user);
                break;
            case 'change_password':
                changePassword($user);
                break;
            default:
                respError('未知操作');
        }
        break;
    default:
        respError('请求方法不允许', 1, 405);
}

/**
 * 获取个人信息
 */
function getPersonalInfo(array $user): void
{
    $pdo = getDB();

    // 获取基本信息
    $stmt = $pdo->prepare('SELECT * FROM t_user WHERE user_id = ?');
    $stmt->execute([$user['user_id']]);
    $userData = $stmt->fetch();

    if (!$userData) {
        respNotFound('用户不存在');
    }

    $result = [
        'user_id' => $userData['user_id'],
        'username' => $userData['username'],
        'real_name' => $userData['real_name'],
        'user_type' => $userData['user_type'],
        'phone' => $userData['phone'],
        'created_at' => $userData['created_at']
    ];

    // 获取扩展信息
    switch ($userData['user_type']) {
        case 'teacher':
            $stmt = $pdo->prepare('SELECT title, college, research_area FROM t_user_teacher WHERE user_id = ?');
            $stmt->execute([$userData['user_id']]);
            $ext = $stmt->fetch();
            if ($ext) {
                $result['title'] = $ext['title'];
                $result['college'] = $ext['college'];
                $result['research_area'] = $ext['research_area'];
            }
            break;

        case 'student':
            $stmt = $pdo->prepare('
                SELECT s.student_no, s.major, s.college, s.advisor_id, 
                       u.real_name as advisor_name, u.phone as advisor_phone
                FROM t_user_student s
                LEFT JOIN t_user u ON s.advisor_id = u.user_id
                WHERE s.user_id = ?
            ');
            $stmt->execute([$userData['user_id']]);
            $ext = $stmt->fetch();
            if ($ext) {
                $result['student_no'] = $ext['student_no'];
                $result['major'] = $ext['major'];
                $result['college'] = $ext['college'];
                $result['advisor_id'] = $ext['advisor_id'];
                $result['advisor_name'] = $ext['advisor_name'];
                $result['advisor_phone'] = $ext['advisor_phone'];
            }
            break;

        case 'external':
            $stmt = $pdo->prepare('SELECT organization, identity_card FROM t_user_external WHERE user_id = ?');
            $stmt->execute([$userData['user_id']]);
            $ext = $stmt->fetch();
            if ($ext) {
                $result['organization'] = $ext['organization'];
                $result['identity_card'] = $ext['identity_card'];
            }
            break;
    }

    // 获取统计信息
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM t_reservation WHERE user_id = ?');
    $stmt->execute([$userData['user_id']]);
    $result['reservation_count'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM t_borrow_record WHERE user_id = ?');
    $stmt->execute([$userData['user_id']]);
    $result['borrow_count'] = (int)$stmt->fetchColumn();

    respOK($result);
}

/**
 * 更新个人信息
 */
function updatePersonalInfo(array $user): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $pdo = getDB();

    try {
        $pdo->beginTransaction();

        // 更新基本信息
        $phone = isset($input['phone']) ? trim($input['phone']) : null;
        if ($phone !== null) {
            $stmt = $pdo->prepare('UPDATE t_user SET phone = ? WHERE user_id = ?');
            $stmt->execute([$phone ?: null, $user['user_id']]);
        }

        // 根据用户类型更新扩展信息
        switch ($user['user_type']) {
            case 'teacher':
                $updates = [];
                $params = [];

                if (isset($input['title'])) {
                    $updates[] = 'title = ?';
                    $params[] = trim($input['title']) ?: null;
                }
                if (isset($input['college'])) {
                    $updates[] = 'college = ?';
                    $params[] = trim($input['college']) ?: null;
                }
                if (isset($input['research_area'])) {
                    $updates[] = 'research_area = ?';
                    $params[] = trim($input['research_area']) ?: null;
                }

                if (!empty($updates)) {
                    $params[] = $user['user_id'];
                    $sql = 'UPDATE t_user_teacher SET ' . implode(', ', $updates) . ' WHERE user_id = ?';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                }
                break;

            case 'student':
                $updates = [];
                $params = [];

                if (isset($input['major'])) {
                    $updates[] = 'major = ?';
                    $params[] = trim($input['major']) ?: null;
                }
                if (isset($input['college'])) {
                    $updates[] = 'college = ?';
                    $params[] = trim($input['college']) ?: null;
                }

                if (!empty($updates)) {
                    $params[] = $user['user_id'];
                    $sql = 'UPDATE t_user_student SET ' . implode(', ', $updates) . ' WHERE user_id = ?';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                }
                break;

            case 'external':
                $updates = [];
                $params = [];

                if (isset($input['organization'])) {
                    $updates[] = 'organization = ?';
                    $params[] = trim($input['organization']) ?: null;
                }
                if (isset($input['identity_card'])) {
                    $updates[] = 'identity_card = ?';
                    $params[] = trim($input['identity_card']) ?: null;
                }

                if (!empty($updates)) {
                    $params[] = $user['user_id'];
                    $sql = 'UPDATE t_user_external SET ' . implode(', ', $updates) . ' WHERE user_id = ?';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                }
                break;
        }

        $pdo->commit();

        respOK(null, '个人信息更新成功');

    } catch (Exception $e) {
        $pdo->rollBack();
        respError('更新失败：' . $e->getMessage());
    }
}

/**
 * 修改密码
 */
function changePassword(array $user): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $oldPassword = $input['old_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';

    if (empty($oldPassword) || empty($newPassword)) {
        respError('请填写完整密码信息');
    }

    if (strlen($newPassword) < 6) {
        respError('新密码至少6位');
    }

    $pdo = getDB();

    // 验证旧密码
    $stmt = $pdo->prepare('SELECT password FROM t_user WHERE user_id = ?');
    $stmt->execute([$user['user_id']]);
    $userData = $stmt->fetch();

    // 使用 bcrypt 验证旧密码
    if (!password_verify($oldPassword, $userData['password'])) {
        respError('原密码错误');
    }

    // 更新密码（使用 bcrypt 加密）
    $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE t_user SET password = ? WHERE user_id = ?');
    $stmt->execute([$hashedNewPassword, $user['user_id']]);

    // 删除所有 token，强制重新登录
    $stmt = $pdo->prepare('DELETE FROM t_user_token WHERE user_id = ?');
    $stmt->execute([$user['user_id']]);

    respOK(null, '密码修改成功，请重新登录');
}
