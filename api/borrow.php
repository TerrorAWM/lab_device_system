<?php
/**
 * 借用记录 API
 * 
 * GET /api/borrow.php - 获取我的借用记录
 * GET /api/borrow.php?id=X - 获取借用详情
 */

declare(strict_types=1);

require_once __DIR__ . '/_init.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respError('请求方法不允许', 1, 405);
}

if (isset($_GET['id'])) {
    getBorrowDetail($user, (int)$_GET['id']);
} else {
    getBorrowList($user);
}

/**
 * 获取借用记录列表
 */
function getBorrowList(array $user): void
{
    $pdo = getDB();

    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int)($_GET['page_size'] ?? 20)));
    $offset = ($page - 1) * $pageSize;

    $status = $_GET['status'] ?? '';

    $where = ['b.user_id = ?'];
    $params = [$user['user_id']];

    if ($status !== '') {
        $where[] = 'b.status = ?';
        $params[] = (int)$status;
    }

    $whereClause = implode(' AND ', $where);

    // 查询总数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM t_borrow_record b WHERE $whereClause");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // 查询数据
    $stmt = $pdo->prepare("
        SELECT b.*, d.device_name, d.model, d.location, r.reserve_date, r.time_slot
        FROM t_borrow_record b
        LEFT JOIN t_device d ON b.device_id = d.device_id
        LEFT JOIN t_reservation r ON b.reservation_id = r.reservation_id
        WHERE $whereClause
        ORDER BY b.record_id DESC
        LIMIT $pageSize OFFSET $offset
    ");
    $stmt->execute($params);
    $borrows = $stmt->fetchAll();

    $statusMap = [1 => 'borrowing', 2 => 'returned', 3 => 'overdue'];

    $items = array_map(function($b) use ($statusMap) {
        return [
            'id' => $b['record_id'],
            'reservation_id' => $b['reservation_id'],
            'device_id' => $b['device_id'],
            'device_name' => $b['device_name'],
            'model' => $b['model'],
            'location' => $b['location'],
            'borrow_date' => $b['borrow_date'],
            'time_slot' => $b['time_slot'],
            'actual_return' => $b['actual_return'],
            'status' => $statusMap[$b['status']] ?? 'unknown',
            'status_code' => $b['status']
        ];
    }, $borrows);

    respOK([
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
            'total_pages' => ceil($total / $pageSize)
        ]
    ]);
}

/**
 * 获取借用详情
 */
function getBorrowDetail(array $user, int $recordId): void
{
    $pdo = getDB();

    $stmt = $pdo->prepare('
        SELECT b.*, d.device_name, d.model, d.location, d.rent_price,
               r.reserve_date, r.time_slot, r.purpose
        FROM t_borrow_record b
        LEFT JOIN t_device d ON b.device_id = d.device_id
        LEFT JOIN t_reservation r ON b.reservation_id = r.reservation_id
        WHERE b.record_id = ? AND b.user_id = ?
    ');
    $stmt->execute([$recordId, $user['user_id']]);
    $borrow = $stmt->fetch();

    if (!$borrow) {
        respNotFound('借用记录不存在');
    }

    $statusMap = [1 => 'borrowing', 2 => 'returned', 3 => 'overdue'];

    respOK([
        'id' => $borrow['record_id'],
        'reservation_id' => $borrow['reservation_id'],
        'device' => [
            'id' => $borrow['device_id'],
            'name' => $borrow['device_name'],
            'model' => $borrow['model'],
            'location' => $borrow['location']
        ],
        'borrow_date' => $borrow['borrow_date'],
        'time_slot' => $borrow['time_slot'],
        'purpose' => $borrow['purpose'],
        'actual_return' => $borrow['actual_return'],
        'status' => $statusMap[$borrow['status']] ?? 'unknown',
        'status_code' => $borrow['status'],
        'rent_price' => (float)$borrow['rent_price']
    ]);
}
