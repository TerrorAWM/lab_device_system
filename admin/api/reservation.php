<?php
/**
 * 预约审批管理 API
 * 
 * GET /admin/api/reservation.php - 所有预约列表
 * GET /admin/api/reservation.php?id=X - 预约详情
 * POST /admin/api/reservation.php?action=approve - 批准预约
 * POST /admin/api/reservation.php?action=reject - 驳回预约
 */

declare(strict_types=1);

require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/_util.php';

$admin = requireAdminAuth();
$action = $_GET['action'] ?? '';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['id'])) {
            getReservationDetail((int)$_GET['id']);
        } else {
            getReservationList();
        }
        break;
    case 'POST':
        switch ($action) {
            case 'approve':
                approveReservation($admin);
                break;
            case 'reject':
                rejectReservation($admin);
                break;
            default:
                respError('未知操作');
        }
        break;
    default:
        respError('请求方法不允许', 1, 405);
}

/**
 * 获取预约列表
 */
function getReservationList(): void
{
    $pdo = getDB();
    $pagination = getPagination();

    $status = $_GET['status'] ?? '';
    $userId = $_GET['user_id'] ?? '';
    $deviceId = $_GET['device_id'] ?? '';

    $where = ['1=1'];
    $params = [];

    if ($status !== '') {
        $where[] = 'r.status = ?';
        $params[] = (int)$status;
    }

    if ($userId) {
        $where[] = 'r.user_id = ?';
        $params[] = (int)$userId;
    }

    if ($deviceId) {
        $where[] = 'r.device_id = ?';
        $params[] = (int)$deviceId;
    }

    $whereClause = implode(' AND ', $where);

    // 查询总数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM t_reservation r WHERE $whereClause");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // 查询数据
    $sql = "
        SELECT r.*, 
               u.real_name as user_name, u.user_type, u.phone as user_phone,
               d.device_name, d.model, d.location
        FROM t_reservation r
        LEFT JOIN t_user u ON r.user_id = u.user_id
        LEFT JOIN t_device d ON r.device_id = d.device_id
        WHERE $whereClause
        ORDER BY r.created_at DESC
        LIMIT {$pagination['page_size']} OFFSET {$pagination['offset']}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll();

    $statusMap = [0 => 'pending', 1 => 'approved', 2 => 'rejected', 3 => 'cancelled', 4 => 'completed'];

    $items = array_map(function($r) use ($statusMap) {
        return [
            'id' => $r['reservation_id'],
            'user_id' => $r['user_id'],
            'user_name' => $r['user_name'],
            'user_type' => $r['user_type'],
            'user_phone' => $r['user_phone'],
            'device_id' => $r['device_id'],
            'device_name' => $r['device_name'],
            'model' => $r['model'],
            'location' => $r['location'],
            'reserve_date' => $r['reserve_date'],
            'time_slot' => $r['time_slot'],
            'purpose' => $r['purpose'],
            'status' => $statusMap[$r['status']] ?? 'unknown',
            'status_code' => $r['status'],
            'reject_reason' => $r['reject_reason'],
            'created_at' => $r['created_at']
        ];
    }, $reservations);

    respOK(buildPaginatedResponse($items, $total, $pagination));
}

/**
 * 获取预约详情
 */
function getReservationDetail(int $reservationId): void
{
    $pdo = getDB();

    $stmt = $pdo->prepare('
        SELECT r.*, 
               u.real_name as user_name, u.user_type, u.phone as user_phone, u.username,
               d.device_name, d.model, d.location, d.rent_price
        FROM t_reservation r
        LEFT JOIN t_user u ON r.user_id = u.user_id
        LEFT JOIN t_device d ON r.device_id = d.device_id
        WHERE r.reservation_id = ?
    ');
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        respNotFound('预约记录不存在');
    }

    $statusMap = [0 => 'pending', 1 => 'approved', 2 => 'rejected', 3 => 'cancelled', 4 => 'completed'];

    respOK([
        'id' => $reservation['reservation_id'],
        'user' => [
            'id' => $reservation['user_id'],
            'username' => $reservation['username'],
            'name' => $reservation['user_name'],
            'type' => $reservation['user_type'],
            'phone' => $reservation['user_phone']
        ],
        'device' => [
            'id' => $reservation['device_id'],
            'name' => $reservation['device_name'],
            'model' => $reservation['model'],
            'location' => $reservation['location'],
            'rent_price' => (float)$reservation['rent_price']
        ],
        'reserve_date' => $reservation['reserve_date'],
        'time_slot' => $reservation['time_slot'],
        'purpose' => $reservation['purpose'],
        'status' => $statusMap[$reservation['status']] ?? 'unknown',
        'status_code' => $reservation['status'],
        'reject_reason' => $reservation['reject_reason'],
        'approvals' => $reservation['approvals'] ? json_decode($reservation['approvals'], true) : null,
        'created_at' => $reservation['created_at']
    ]);
}

/**
 * 批准预约
 */
function approveReservation(array $admin): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $reservationId = (int)($input['reservation_id'] ?? 0);
    $remark = trim($input['remark'] ?? '');

    if (!$reservationId) {
        respError('预约ID不能为空');
    }

    $pdo = getDB();

    // 检查预约
    $stmt = $pdo->prepare('SELECT * FROM t_reservation WHERE reservation_id = ?');
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        respNotFound('预约记录不存在');
    }

    if ($reservation['status'] != 0) {
        respError('该预约已处理');
    }

    try {
        $pdo->beginTransaction();

        // 更新预约状态
        $approvals = json_encode([
            'admin' => [
                'admin_id' => $admin['admin_id'],
                'real_name' => $admin['real_name'],
                'action' => 'approve',
                'remark' => $remark,
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

        // 更新设备状态为借出
        $stmt = $pdo->prepare('UPDATE t_device SET status = 2 WHERE device_id = ?');
        $stmt->execute([$reservation['device_id']]);

        $pdo->commit();

        respOK([
            'reservation_id' => $reservationId,
            'status' => 'approved'
        ], '预约已批准');

    } catch (Exception $e) {
        $pdo->rollBack();
        respError('审批失败：' . $e->getMessage());
    }
}

/**
 * 驳回预约
 */
function rejectReservation(array $admin): void
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

    // 检查预约
    $stmt = $pdo->prepare('SELECT * FROM t_reservation WHERE reservation_id = ?');
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        respNotFound('预约记录不存在');
    }

    if ($reservation['status'] != 0) {
        respError('该预约已处理');
    }

    // 更新预约状态
    $approvals = json_encode([
        'admin' => [
            'admin_id' => $admin['admin_id'],
            'real_name' => $admin['real_name'],
            'action' => 'reject',
            'reason' => $reason,
            'time' => date('Y-m-d H:i:s')
        ]
    ]);

    $stmt = $pdo->prepare('UPDATE t_reservation SET status = 2, reject_reason = ?, approvals = ? WHERE reservation_id = ?');
    $stmt->execute([$reason, $approvals, $reservationId]);

    respOK([
        'reservation_id' => $reservationId,
        'status' => 'rejected'
    ], '预约已驳回');
}
