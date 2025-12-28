<?php
/**
 * 预约管理 API
 * 
 * POST /api/reservation.php - 提交预约申请
 * GET /api/reservation.php - 获取我的预约列表
 * GET /api/reservation.php?id=X - 获取预约详情
 * POST /api/reservation.php?action=update - 修改预约
 * POST /api/reservation.php?action=cancel - 取消预约
 */

declare(strict_types=1);

require_once __DIR__ . '/_init.php';

$user = requireAuth();
$action = $_GET['action'] ?? '';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['id'])) {
            getReservationDetail($user, (int)$_GET['id']);
        } else {
            getReservationList($user);
        }
        break;
    case 'POST':
        switch ($action) {
            case 'update':
                updateReservation($user);
                break;
            case 'cancel':
                cancelReservation($user);
                break;
            default:
                createReservation($user);
                break;
        }
        break;
    default:
        respError('请求方法不允许', 1, 405);
}

/**
 * 创建预约
 */
function createReservation(array $user): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $deviceId = (int)($input['device_id'] ?? 0);
    $reserveDate = trim($input['reserve_date'] ?? '');
    $timeSlot = trim($input['time_slot'] ?? '');
    $purpose = trim($input['purpose'] ?? '');

    if (!$deviceId) {
        respError('请选择设备');
    }

    if (empty($reserveDate)) {
        respError('请选择预约日期');
    }

    // 验证时段
    $validSlots = ['08:00-10:00', '10:00-12:00', '14:00-16:00', '16:00-18:00', '19:00-21:00'];
    if (!in_array($timeSlot, $validSlots)) {
        respError('无效的时段');
    }

    if (empty($purpose)) {
        respError('请填写借用原因');
    }

    // 验证日期
    $today = date('Y-m-d');
    if ($reserveDate < $today) {
        respError('预约日期不能早于今天');
    }

    $pdo = getDB();

    // 检查设备是否存在且可用
    $stmt = $pdo->prepare('SELECT device_id, device_name, status, rent_price FROM t_device WHERE device_id = ?');
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch();

    if (!$device) {
        respError('设备不存在');
    }

    if ($device['status'] != 1) {
        respError('设备当前不可预约');
    }

    // 检查时段是否已被占用
    $stmt = $pdo->prepare('
        SELECT reservation_id FROM t_reservation 
        WHERE device_id = ? AND reserve_date = ? AND time_slot = ? AND status IN (0, 1)
    ');
    $stmt->execute([$deviceId, $reserveDate, $timeSlot]);
    if ($stmt->fetch()) {
        respError('该时段已被预约');
    }

    try {
        $pdo->beginTransaction();

        // 创建预约
        $stmt = $pdo->prepare('
            INSERT INTO t_reservation (user_id, device_id, reserve_date, time_slot, purpose, status, current_step, created_at)
            VALUES (?, ?, ?, ?, ?, 0, 1, NOW())
        ');
        $stmt->execute([$user['user_id'], $deviceId, $reserveDate, $timeSlot, $purpose]);
        $reservationId = (int)$pdo->lastInsertId();

        // 创建支付订单
        // 校外人员需要支付租金，校内人员(学生/教师)金额为0且自动标记已支付
        $isExternal = ($user['user_type'] === 'external');
        $amount = $isExternal ? (float)$device['rent_price'] : 0.00;
        $paymentStatus = $isExternal ? 0 : 1;  // 校内自动已支付
        $orderNo = 'PAY' . date('YmdHis') . str_pad((string)$reservationId, 6, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare('
            INSERT INTO t_payment (reservation_id, user_id, order_no, amount, status, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ');
        $desc = $isExternal ? '设备租借费用' : '校内免费使用';
        $stmt->execute([$reservationId, $user['user_id'], $orderNo, $amount, $paymentStatus, $desc]);
        $paymentId = (int)$pdo->lastInsertId();

        // 校内用户自动设置支付时间
        if (!$isExternal) {
            $stmt = $pdo->prepare('UPDATE t_payment SET pay_time = NOW() WHERE payment_id = ?');
            $stmt->execute([$paymentId]);
        }

        $pdo->commit();

        $response = [
            'reservation_id' => $reservationId,
            'device_name' => $device['device_name'],
            'reserve_date' => $reserveDate,
            'time_slot' => $timeSlot,
            'status' => 'pending',
            'payment' => [
                'payment_id' => $paymentId,
                'order_no' => $orderNo,
                'amount' => $amount,
                'status' => $paymentStatus == 1 ? 'paid' : 'pending'
            ]
        ];

        $msg = '预约申请已提交';
        if ($isExternal && $amount > 0) {
            $msg .= '，请完成支付（¥' . number_format($amount, 2) . '）';
        }

        respOK($response, $msg);

    } catch (Exception $e) {
        $pdo->rollBack();
        respError('预约创建失败：' . $e->getMessage());
    }
}

/**
 * 获取预约列表
 */
function getReservationList(array $user): void
{
    $pdo = getDB();

    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int)($_GET['page_size'] ?? 20)));
    $offset = ($page - 1) * $pageSize;

    $status = $_GET['status'] ?? '';

    $where = ['r.user_id = ?'];
    $params = [$user['user_id']];

    if ($status !== '') {
        $where[] = 'r.status = ?';
        $params[] = (int)$status;
    }

    $whereClause = implode(' AND ', $where);

    // 查询总数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM t_reservation r WHERE $whereClause");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // 查询数据
    $stmt = $pdo->prepare("
        SELECT r.*, d.device_name, d.model, d.location
        FROM t_reservation r
        LEFT JOIN t_device d ON r.device_id = d.device_id
        WHERE $whereClause
        ORDER BY r.created_at DESC
        LIMIT $pageSize OFFSET $offset
    ");
    $stmt->execute($params);
    $reservations = $stmt->fetchAll();

    $statusMap = [0 => 'pending', 1 => 'approved', 2 => 'rejected', 3 => 'cancelled', 4 => 'completed'];

    $items = array_map(function($r) use ($statusMap) {
        return [
            'id' => $r['reservation_id'],
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
 * 获取预约详情
 */
function getReservationDetail(array $user, int $reservationId): void
{
    $pdo = getDB();

    $stmt = $pdo->prepare('
        SELECT r.*, d.device_name, d.model, d.location, d.rent_price
        FROM t_reservation r
        LEFT JOIN t_device d ON r.device_id = d.device_id
        WHERE r.reservation_id = ? AND r.user_id = ?
    ');
    $stmt->execute([$reservationId, $user['user_id']]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        respNotFound('预约记录不存在');
    }

    $statusMap = [0 => 'pending', 1 => 'approved', 2 => 'rejected', 3 => 'cancelled', 4 => 'completed'];

    respOK([
        'id' => $reservation['reservation_id'],
        'device_id' => $reservation['device_id'],
        'device_name' => $reservation['device_name'],
        'model' => $reservation['model'],
        'location' => $reservation['location'],
        'reserve_date' => $reservation['reserve_date'],
        'time_slot' => $reservation['time_slot'],
        'purpose' => $reservation['purpose'],
        'status' => $statusMap[$reservation['status']] ?? 'unknown',
        'status_code' => $reservation['status'],
        'reject_reason' => $reservation['reject_reason'],
        'rent_price' => (float)$reservation['rent_price'],
        'created_at' => $reservation['created_at']
    ]);
}

/**
 * 修改预约
 */
function updateReservation(array $user): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $reservationId = (int)($input['reservation_id'] ?? 0);
    $reserveDate = trim($input['reserve_date'] ?? '');
    $timeSlot = trim($input['time_slot'] ?? '');
    $purpose = trim($input['purpose'] ?? '');

    if (!$reservationId) {
        respError('预约ID不能为空');
    }

    $pdo = getDB();

    // 检查预约是否存在且属于当前用户
    $stmt = $pdo->prepare('SELECT * FROM t_reservation WHERE reservation_id = ? AND user_id = ?');
    $stmt->execute([$reservationId, $user['user_id']]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        respNotFound('预约记录不存在');
    }

    // 只有待审核状态才能修改
    if ($reservation['status'] != 0) {
        respError('只能修改待审核的预约');
    }

    // 验证时段
    $validSlots = ['08:00-10:00', '10:00-12:00', '14:00-16:00', '16:00-18:00', '19:00-21:00'];
    if ($timeSlot && !in_array($timeSlot, $validSlots)) {
        respError('无效的时段');
    }

    // 验证日期
    if ($reserveDate) {
        $today = date('Y-m-d');
        if ($reserveDate < $today) {
            respError('预约日期不能早于今天');
        }
    }

    // 如果修改了日期或时段，检查是否冲突
    $newDate = $reserveDate ?: $reservation['reserve_date'];
    $newSlot = $timeSlot ?: $reservation['time_slot'];

    if ($newDate !== $reservation['reserve_date'] || $newSlot !== $reservation['time_slot']) {
        $stmt = $pdo->prepare('
            SELECT reservation_id FROM t_reservation 
            WHERE device_id = ? AND reserve_date = ? AND time_slot = ? 
            AND status IN (0, 1) AND reservation_id != ?
        ');
        $stmt->execute([$reservation['device_id'], $newDate, $newSlot, $reservationId]);
        if ($stmt->fetch()) {
            respError('该时段已被预约');
        }
    }

    // 更新预约
    $stmt = $pdo->prepare('
        UPDATE t_reservation 
        SET reserve_date = ?, time_slot = ?, purpose = ?
        WHERE reservation_id = ?
    ');
    $stmt->execute([
        $newDate,
        $newSlot,
        $purpose ?: $reservation['purpose'],
        $reservationId
    ]);

    respOK(null, '预约修改成功');
}

/**
 * 取消预约
 */
function cancelReservation(array $user): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $reservationId = (int)($input['reservation_id'] ?? 0);
    $reason = trim($input['reason'] ?? '');

    if (!$reservationId) {
        respError('预约ID不能为空');
    }

    $pdo = getDB();

    // 检查预约是否存在且属于当前用户
    $stmt = $pdo->prepare('SELECT * FROM t_reservation WHERE reservation_id = ? AND user_id = ?');
    $stmt->execute([$reservationId, $user['user_id']]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        respNotFound('预约记录不存在');
    }

    // 只有待审核或已批准状态才能取消
    if (!in_array($reservation['status'], [0, 1])) {
        respError('该预约不能取消');
    }

    try {
        $pdo->beginTransaction();

        // 更新预约状态为已取消
        $stmt = $pdo->prepare('UPDATE t_reservation SET status = 3 WHERE reservation_id = ?');
        $stmt->execute([$reservationId]);

        // 同时取消支付订单 (status=2 表示已取消)
        $stmt = $pdo->prepare('UPDATE t_payment SET status = 2 WHERE reservation_id = ? AND status = 0');
        $stmt->execute([$reservationId]);

        $pdo->commit();

        respOK(null, '预约已取消');

    } catch (Exception $e) {
        $pdo->rollBack();
        respError('取消失败：' . $e->getMessage());
    }
}
