<?php
/**
 * 教师审批学生预约 API
 * 
 * GET /api/approval.php - 获取待审批列表
 * POST /api/approval.php?action=approve - 批准申请
 * POST /api/approval.php?action=reject - 驳回申请
 */

declare(strict_types=1);

require_once __DIR__ . '/_init.php';

$user = requireAuth();

// 验证是否为教师
if ($user['user_type'] !== 'teacher') {
    respForbidden('仅限教师访问此接口');
}

$action = $_GET['action'] ?? '';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        getPendingApprovals($user);
        break;
    case 'POST':
        switch ($action) {
            case 'approve':
                approveReservation($user);
                break;
            case 'reject':
                rejectReservation($user);
                break;
            default:
                respError('未知操作');
        }
        break;
    default:
        respError('请求方法不允许', 1, 405);
}

/**
 * 获取待审批的学生预约
 */
function getPendingApprovals(array $user): void
{
    $pdo = getDB();

    // 查询该教师指导的学生的待审批预约
    $stmt = $pdo->prepare('
        SELECT r.*, 
               u.real_name as student_name,
               s.student_no,
               d.device_name, d.model, d.location
        FROM t_reservation r
        JOIN t_user u ON r.user_id = u.user_id
        JOIN t_user_student s ON u.user_id = s.user_id
        JOIN t_device d ON r.device_id = d.device_id
        WHERE s.advisor_id = ? AND r.status = 0
        ORDER BY r.created_at DESC
    ');
    $stmt->execute([$user['user_id']]);
    $reservations = $stmt->fetchAll();

    $items = array_map(function($r) {
        return [
            'id' => $r['reservation_id'],
            'student_name' => $r['student_name'],
            'student_no' => $r['student_no'],
            'device_id' => $r['device_id'],
            'device_name' => $r['device_name'],
            'model' => $r['model'],
            'location' => $r['location'],
            'reserve_date' => $r['reserve_date'],
            'time_slot' => $r['time_slot'],
            'purpose' => $r['purpose'],
            'created_at' => $r['created_at']
        ];
    }, $reservations);

    // 统计数据
    $stmt = $pdo->prepare('
        SELECT 
            SUM(CASE WHEN r.status = 0 THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN r.status = 1 THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN r.status = 2 THEN 1 ELSE 0 END) as rejected
        FROM t_reservation r
        JOIN t_user_student s ON r.user_id = s.user_id
        WHERE s.advisor_id = ?
    ');
    $stmt->execute([$user['user_id']]);
    $stats = $stmt->fetch();

    respOK([
        'items' => $items,
        'stats' => [
            'pending' => (int)($stats['pending'] ?? 0),
            'approved' => (int)($stats['approved'] ?? 0),
            'rejected' => (int)($stats['rejected'] ?? 0)
        ]
    ]);
}

/**
 * 批准预约申请
 */
function approveReservation(array $user): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $reservationId = (int)($input['reservation_id'] ?? 0);

    if (!$reservationId) {
        respError('预约ID不能为空');
    }

    $pdo = getDB();

    // 验证预约是否存在且属于该教师的学生
    $stmt = $pdo->prepare('
        SELECT r.*, s.advisor_id
        FROM t_reservation r
        JOIN t_user_student s ON r.user_id = s.user_id
        WHERE r.reservation_id = ?
    ');
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        respNotFound('预约记录不存在');
    }

    if ($reservation['advisor_id'] != $user['user_id']) {
        respForbidden('无权审批此预约');
    }

    if ($reservation['status'] != 0) {
        respError('该预约已处理');
    }

    try {
        $pdo->beginTransaction();

        // 更新预约状态为已批准
        $approvals = json_encode([
            'advisor' => [
                'user_id' => $user['user_id'],
                'real_name' => $user['real_name'],
                'action' => 'approve',
                'time' => date('Y-m-d H:i:s')
            ]
        ]);

        $stmt = $pdo->prepare('UPDATE t_reservation SET status = 1, approvals = ? WHERE reservation_id = ?');
        $stmt->execute([$approvals, $reservationId]);

        // 创建借用记录
        $stmt = $pdo->prepare('
            INSERT INTO t_borrow_record (reservation_id, user_id, device_id, borrow_date, time_slot, status)
            VALUES (?, ?, ?, ?, ?, 1)
        ');
        $stmt->execute([
            $reservationId,
            $reservation['user_id'],
            $reservation['device_id'],
            $reservation['reserve_date'],
            $reservation['time_slot']
        ]);

        $pdo->commit();

        respOK(null, '已批准该预约');

    } catch (Exception $e) {
        $pdo->rollBack();
        respError('审批失败：' . $e->getMessage());
    }
}

/**
 * 驳回预约申请
 */
function rejectReservation(array $user): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $reservationId = (int)($input['reservation_id'] ?? 0);
    $reason = trim($input['reason'] ?? '');

    if (!$reservationId) {
        respError('预约ID不能为空');
    }

    if (empty($reason)) {
        respError('请填写驳回原因');
    }

    $pdo = getDB();

    // 验证预约是否存在且属于该教师的学生
    $stmt = $pdo->prepare('
        SELECT r.*, s.advisor_id
        FROM t_reservation r
        JOIN t_user_student s ON r.user_id = s.user_id
        WHERE r.reservation_id = ?
    ');
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        respNotFound('预约记录不存在');
    }

    if ($reservation['advisor_id'] != $user['user_id']) {
        respForbidden('无权审批此预约');
    }

    if ($reservation['status'] != 0) {
        respError('该预约已处理');
    }

    // 更新预约状态为已驳回
    $approvals = json_encode([
        'advisor' => [
            'user_id' => $user['user_id'],
            'real_name' => $user['real_name'],
            'action' => 'reject',
            'reason' => $reason,
            'time' => date('Y-m-d H:i:s')
        ]
    ]);

    $stmt = $pdo->prepare('UPDATE t_reservation SET status = 2, reject_reason = ?, approvals = ? WHERE reservation_id = ?');
    $stmt->execute([$reason, $approvals, $reservationId]);

    respOK(null, '已驳回该预约');
}
