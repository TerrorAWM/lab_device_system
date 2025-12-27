<?php
/**
 * 归还申请 API
 * 
 * POST /api/return.php - 申请归还设备
 */

declare(strict_types=1);

require_once __DIR__ . '/_init.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respError('请求方法不允许', 1, 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$borrowId = (int)($input['borrow_id'] ?? 0);
$remark = trim($input['remark'] ?? '');

if (!$borrowId) {
    respError('借用记录ID不能为空');
}

$pdo = getDB();

// 检查借用记录是否存在且属于当前用户
$stmt = $pdo->prepare('
    SELECT b.*, d.device_name 
    FROM t_borrow_record b
    LEFT JOIN t_device d ON b.device_id = d.device_id
    WHERE b.record_id = ? AND b.user_id = ?
');
$stmt->execute([$borrowId, $user['user_id']]);
$borrow = $stmt->fetch();

if (!$borrow) {
    respNotFound('借用记录不存在');
}

// 只有借用中状态才能归还
if ($borrow['status'] != 1) {
    respError('该记录不需要归还');
}

try {
    $pdo->beginTransaction();

    // 更新借用记录状态为已归还
    $stmt = $pdo->prepare('
        UPDATE t_borrow_record 
        SET status = 2, actual_return = NOW()
        WHERE record_id = ?
    ');
    $stmt->execute([$borrowId]);

    // 更新设备状态为可用
    $stmt = $pdo->prepare('UPDATE t_device SET status = 1 WHERE device_id = ?');
    $stmt->execute([$borrow['device_id']]);

    // 更新预约状态为已完成
    $stmt = $pdo->prepare('UPDATE t_reservation SET status = 4 WHERE reservation_id = ?');
    $stmt->execute([$borrow['reservation_id']]);

    $pdo->commit();

    respOK([
        'borrow_id' => $borrowId,
        'device_name' => $borrow['device_name'],
        'return_time' => date('Y-m-d H:i:s'),
        'status' => 'returned'
    ], '归还成功');

} catch (Exception $e) {
    $pdo->rollBack();
    respError('归还失败：' . $e->getMessage());
}
