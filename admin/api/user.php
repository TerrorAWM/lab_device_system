<?php
/**
 * 用户管理 API
 * 
 * GET /admin/api/user.php - 用户列表
 * GET /admin/api/user.php?id=X - 用户详情
 * POST /admin/api/user.php?action=toggle_status - 禁用/启用用户
 */

declare(strict_types=1);

require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/_util.php';

$admin = requireAdminAuth();
$action = $_GET['action'] ?? '';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['id'])) {
            getUserDetail((int)$_GET['id']);
        } else {
            getUserList();
        }
        break;
    case 'POST':
        switch ($action) {
            case 'toggle_status':
                toggleUserStatus($admin);
                break;
            default:
                respError('未知操作');
        }
        break;
    default:
        respError('请求方法不允许', 1, 405);
}

/**
 * 获取用户列表
 */
function getUserList(): void
{
    $pdo = getDB();
    $pagination = getPagination();

    $keyword = trim($_GET['keyword'] ?? '');
    $userType = $_GET['user_type'] ?? '';
    $status = $_GET['status'] ?? '';

    $where = ["u.role = 'user'"]; // 只查询普通用户
    $params = [];

    if ($keyword) {
        $where[] = '(u.username LIKE ? OR u.real_name LIKE ?)';
        $params[] = "%$keyword%";
        $params[] = "%$keyword%";
    }

    if ($userType) {
        $where[] = 'u.user_type = ?';
        $params[] = $userType;
    }

    if ($status !== '') {
        $where[] = 'u.status = ?';
        $params[] = (int)$status;
    }

    $whereClause = implode(' AND ', $where);

    // 查询总数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM t_user u WHERE $whereClause");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // 查询数据
    $sql = "
        SELECT u.*
        FROM t_user u
        WHERE $whereClause
        ORDER BY u.created_at DESC
        LIMIT {$pagination['page_size']} OFFSET {$pagination['offset']}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    $items = array_map(function($u) {
        return [
            'id' => $u['user_id'],
            'username' => $u['username'],
            'real_name' => $u['real_name'],
            'user_type' => $u['user_type'],
            'phone' => $u['phone'],
            'status' => $u['status'] == 1 ? 'active' : 'disabled',
            'status_code' => $u['status'],
            'created_at' => $u['created_at']
        ];
    }, $users);

    respOK(buildPaginatedResponse($items, $total, $pagination));
}

/**
 * 获取用户详情
 */
function getUserDetail(int $userId): void
{
    $pdo = getDB();

    $stmt = $pdo->prepare('SELECT * FROM t_user WHERE user_id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        respNotFound('用户不存在');
    }

    $result = [
        'id' => $user['user_id'],
        'username' => $user['username'],
        'real_name' => $user['real_name'],
        'user_type' => $user['user_type'],
        'phone' => $user['phone'],
        'status' => $user['status'] == 1 ? 'active' : 'disabled',
        'status_code' => $user['status'],
        'created_at' => $user['created_at']
    ];

    // 获取扩展信息
    switch ($user['user_type']) {
        case 'teacher':
            $stmt = $pdo->prepare('SELECT * FROM t_user_teacher WHERE user_id = ?');
            $stmt->execute([$userId]);
            $ext = $stmt->fetch();
            if ($ext) {
                $result['title'] = $ext['title'];
                $result['college'] = $ext['college'];
                $result['research_area'] = $ext['research_area'];
            }
            break;

        case 'student':
            $stmt = $pdo->prepare('
                SELECT s.*, u.real_name as advisor_name
                FROM t_user_student s
                LEFT JOIN t_user u ON s.advisor_id = u.user_id
                WHERE s.user_id = ?
            ');
            $stmt->execute([$userId]);
            $ext = $stmt->fetch();
            if ($ext) {
                $result['student_no'] = $ext['student_no'];
                $result['major'] = $ext['major'];
                $result['college'] = $ext['college'];
                $result['advisor_id'] = $ext['advisor_id'];
                $result['advisor_name'] = $ext['advisor_name'];
            }
            break;

        case 'external':
            $stmt = $pdo->prepare('SELECT * FROM t_user_external WHERE user_id = ?');
            $stmt->execute([$userId]);
            $ext = $stmt->fetch();
            if ($ext) {
                $result['organization'] = $ext['organization'];
                $result['identity_card'] = $ext['identity_card'];
            }
            break;
    }

    // 获取统计
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM t_reservation WHERE user_id = ?');
    $stmt->execute([$userId]);
    $result['reservation_count'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM t_borrow_record WHERE user_id = ?');
    $stmt->execute([$userId]);
    $result['borrow_count'] = (int)$stmt->fetchColumn();

    respOK($result);
}

/**
 * 禁用/启用用户
 */
function toggleUserStatus(array $admin): void
{
    // 只有 supervisor 可以禁用用户
    if ($admin['role'] !== 'supervisor') {
        respForbidden('权限不足');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $userId = (int)($input['user_id'] ?? 0);
    $status = (int)($input['status'] ?? 1);

    if (!$userId) {
        respError('用户ID不能为空');
    }

    if (!in_array($status, [0, 1])) {
        respError('无效的状态值');
    }

    $pdo = getDB();

    // 检查用户
    $stmt = $pdo->prepare('SELECT user_id FROM t_user WHERE user_id = ?');
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) {
        respNotFound('用户不存在');
    }

    $stmt = $pdo->prepare('UPDATE t_user SET status = ? WHERE user_id = ?');
    $stmt->execute([$status, $userId]);

    // 如果禁用，清除该用户所有 token
    if ($status == 0) {
        $stmt = $pdo->prepare('DELETE FROM t_user_token WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    respOK([
        'user_id' => $userId,
        'status' => $status == 1 ? 'active' : 'disabled'
    ], $status == 1 ? '用户已启用' : '用户已禁用');
}
