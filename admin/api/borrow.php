<?php
/**
 * 借用管理 API
 * 
 * GET /admin/api/borrow.php - 所有借用记录
 * GET /admin/api/borrow.php?id=X - 借用详情
 * POST /admin/api/borrow.php?action=dispatch - 发放设备
 * POST /admin/api/borrow.php?action=confirm_return - 确认归还
 */

declare(strict_types=1);

require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/_util.php';

$admin = requireAdminAuth();
$action = $_GET['action'] ?? '';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['id'])) {
            getBorrowDetail((int)$_GET['id']);
        } else {
            getBorrowList();
        }
        break;
    case 'POST':
        switch ($action) {
            case 'dispatch':
                dispatchDevice($admin);
                break;
            case 'confirm_return':
                confirmReturn($admin);
                break;
            default:
                respError('未知操作');
        }
        break;
    default:
        respError('请求方法不允许', 1, 405);
}

/**
 * 获取借用记录列表
 */
function getBorrowList(): void
{
    $pdo = getDB();
    $pagination = getPagination();

    $status = $_GET['status'] ?? '';
    $userId = $_GET['user_id'] ?? '';
    $deviceId = $_GET['device_id'] ?? '';

    $where = ['1=1'];
    $params = [];

    if ($status !== '') {
        $where[] = 'b.status = ?';
        $params[] = (int)$status;
    }

    if ($userId) {
        $where[] = 'b.user_id = ?';
        $params[] = (int)$userId;
    }

    if ($deviceId) {
        $where[] = 'b.device_id = ?';
        $params[] = (int)$deviceId;
    }

    $whereClause = implode(' AND ', $where);

    // 查询总数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM t_borrow_record b WHERE $whereClause");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // 查询数据
    $sql = "
        SELECT b.*, 
               u.real_name as user_name, u.username, u.user_type, u.phone as user_phone,
               d.device_name, d.model, d.location
        FROM t_borrow_record b
        LEFT JOIN t_user u ON b.user_id = u.user_id
        LEFT JOIN t_device d ON b.device_id = d.device_id
        WHERE $whereClause
        ORDER BY b.record_id DESC
        LIMIT {$pagination['page_size']} OFFSET {$pagination['offset']}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $borrows = $stmt->fetchAll();

    $statusMap = [1 => 'borrowing', 2 => 'returned', 3 => 'overdue'];

    $items = array_map(function($b) use ($statusMap) {
        return [
            'id' => $b['record_id'],
            'reservation_id' => $b['reservation_id'],
            'user_id' => $b['user_id'],
            'user_name' => $b['user_name'] ?? '',
            'username' => $b['username'] ?? '',
            'real_name' => $b['user_name'] ?? '', // 兼容前端使用real_name
            'user_type' => $b['user_type'] ?? '',
            'user_phone' => $b['user_phone'] ?? '',
            'device_id' => $b['device_id'],
            'device_name' => $b['device_name'] ?? '',
            'model' => $b['model'] ?? '',
            'location' => $b['location'] ?? '',
            'borrow_date' => $b['borrow_date'] ?? '',
            'time_slot' => $b['time_slot'] ?? '',
            'actual_return_time' => $b['actual_return'] ?? null,
            'actual_return' => $b['actual_return'] ?? null,
            'status' => $statusMap[$b['status']] ?? 'unknown',
            'status_code' => $b['status']
        ];
    }, $borrows);

    respOK(buildPaginatedResponse($items, $total, $pagination));
}

/**
 * 获取借用详情
 */
function getBorrowDetail(int $recordId): void
{
    $pdo = getDB();

    $stmt = $pdo->prepare('
        SELECT b.*, 
               u.real_name as user_name, u.user_type, u.phone as user_phone, u.username,
               d.device_name, d.model, d.location, d.rent_price,
               r.purpose
        FROM t_borrow_record b
        LEFT JOIN t_user u ON b.user_id = u.user_id
        LEFT JOIN t_device d ON b.device_id = d.device_id
        LEFT JOIN t_reservation r ON b.reservation_id = r.reservation_id
        WHERE b.record_id = ?
    ');
    $stmt->execute([$recordId]);
    $borrow = $stmt->fetch();

    if (!$borrow) {
        respNotFound('借用记录不存在');
    }

    $statusMap = [1 => 'borrowing', 2 => 'returned', 3 => 'overdue'];

    respOK([
        'id' => $borrow['record_id'],
        'reservation_id' => $borrow['reservation_id'],
        'user' => [
            'id' => $borrow['user_id'],
            'username' => $borrow['username'],
            'name' => $borrow['user_name'],
            'type' => $borrow['user_type'],
            'phone' => $borrow['user_phone']
        ],
        'device' => [
            'id' => $borrow['device_id'],
            'name' => $borrow['device_name'],
            'model' => $borrow['model'],
            'location' => $borrow['location'],
            'rent_price' => (float)$borrow['rent_price']
        ],
        'borrow_date' => $borrow['borrow_date'],
        'time_slot' => $borrow['time_slot'],
        'purpose' => $borrow['purpose'],
        'actual_return' => $borrow['actual_return'],
        'status' => $statusMap[$borrow['status']] ?? 'unknown',
        'status_code' => $borrow['status']
    ]);
}

/**
 * 发放设备（确认借用）
 */
function dispatchDevice(array $admin): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $reservationId = (int)($input['reservation_id'] ?? 0);

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

    if ($reservation['status'] != 1) {
        respError('该预约未批准或已处理');
    }

    // 检查是否已有借用记录
    $stmt = $pdo->prepare('SELECT record_id FROM t_borrow_record WHERE reservation_id = ?');
    $stmt->execute([$reservationId]);
    if ($stmt->fetch()) {
        respError('该预约已发放');
    }

    try {
        $pdo->beginTransaction();

        // 创建借用记录
        $stmt = $pdo->prepare('
            INSERT INTO t_borrow_record (reservation_id, user_id, device_id, borrow_date, time_slot, status, operator_out_id)
            VALUES (?, ?, ?, ?, ?, 1, ?)
        ');
        $stmt->execute([
            $reservationId,
            $reservation['user_id'],
            $reservation['device_id'],
            $reservation['reserve_date'],
            $reservation['time_slot'],
            $admin['admin_id']
        ]);
        $borrowId = (int)$pdo->lastInsertId();

        // 更新设备状态
        $stmt = $pdo->prepare('UPDATE t_device SET status = 2 WHERE device_id = ?');
        $stmt->execute([$reservation['device_id']]);

        $pdo->commit();

        respOK([
            'borrow_id' => $borrowId,
            'reservation_id' => $reservationId
        ], '设备已发放');

    } catch (Exception $e) {
        $pdo->rollBack();
        respError('发放失败：' . $e->getMessage());
    }
}

/**
 * 确认归还
 */
function confirmReturn(array $admin): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $borrowId = (int)($input['borrow_id'] ?? 0);
    $deviceCondition = trim($input['device_condition'] ?? 'good');

    if (!$borrowId) {
        respError('借用记录ID不能为空');
    }

    $pdo = getDB();

    // 检查借用记录
    $stmt = $pdo->prepare('SELECT * FROM t_borrow_record WHERE record_id = ?');
    $stmt->execute([$borrowId]);
    $borrow = $stmt->fetch();

    if (!$borrow) {
        respNotFound('借用记录不存在');
    }

    if ($borrow['status'] != 1) {
        respError('该记录不需要归还');
    }

    try {
        $pdo->beginTransaction();

        // 更新借用记录
        $stmt = $pdo->prepare('
            UPDATE t_borrow_record 
            SET status = 2, actual_return = NOW(), operator_in_id = ?
            WHERE record_id = ?
        ');
        $stmt->execute([$admin['admin_id'], $borrowId]);

        // 更新设备状态
        $newStatus = $deviceCondition === 'good' ? 1 : 3; // 损坏则维护
        $stmt = $pdo->prepare('UPDATE t_device SET status = ? WHERE device_id = ?');
        $stmt->execute([$newStatus, $borrow['device_id']]);

        // 更新预约状态为已完成
        $stmt = $pdo->prepare('UPDATE t_reservation SET status = 4 WHERE reservation_id = ?');
        $stmt->execute([$borrow['reservation_id']]);

        $pdo->commit();

        respOK([
            'borrow_id' => $borrowId,
            'status' => 'returned',
            'device_condition' => $deviceCondition
        ], '归还确认成功');

    } catch (Exception $e) {
        $pdo->rollBack();
        respError('确认归还失败：' . $e->getMessage());
    }
}
