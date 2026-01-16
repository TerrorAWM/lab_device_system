<?php
/**
 * 预约管理 API
 * 
 * POST /api/reservation.php - 提交预约申请
 * GET /api/reservation.php - 获取我的预约列表
 * GET /api/reservation.php?id=X - 获取预约详情
 * POST /api/reservation.php?action=update - 修改预约
 * POST /api/reservation.php?action=cancel - 取消预约
 * 
 * Device管理员专用：
 * GET /api/reservation.php?action=pending - 获取待审批列表
 * POST /api/reservation.php?action=approve - 批准预约
 * POST /api/reservation.php?action=reject - 驳回预约
 */

declare(strict_types=1);

require_once __DIR__ . '/_init.php';

$user = requireAuth();
$action = $_GET['action'] ?? '';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if ($action === 'pending') {
            // Device管理员获取待审批列表
            if ($user['user_type'] !== 'device') {
                respForbidden('只有设备管理员可以访问此功能');
            }
            getPendingForDevice($user);
        } elseif (isset($_GET['id'])) {
            getReservationDetail($user, (int)$_GET['id']);
        } else {
            getReservationList($user);
        }
        break;
    case 'POST':
        switch ($action) {
            case 'approve':
                // Device管理员批准预约
                if ($user['user_type'] !== 'device') {
                    respForbidden('只有设备管理员可以审批预约');
                }
                approveReservation($user);
                break;
            case 'reject':
                // Device管理员驳回预约
                if ($user['user_type'] !== 'device') {
                    respForbidden('只有设备管理员可以驳回预约');
                }
                rejectReservation($user);
                break;
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

    // 检查是否至少提前1天取消（BR-04）
    $reserveDate = new DateTime($reservation['reserve_date']);
    $today = new DateTime();
    $daysDiff = $today->diff($reserveDate)->days;
    
    if ($reserveDate > $today && $daysDiff < 1) {
        respError('取消预约必须至少提前1天');
    }

    try {
        $pdo->beginTransaction();

        // 更新预约状态为已取消
        $stmt = $pdo->prepare('UPDATE t_reservation SET status = 3 WHERE reservation_id = ?');
        $stmt->execute([$reservationId]);

        // 检查是否有已支付的订单，需要退款（FR-F03: 付费预约退款95%）
        $stmt = $pdo->prepare('SELECT * FROM t_payment WHERE reservation_id = ? AND status = 1');
        $stmt->execute([$reservationId]);
        $payment = $stmt->fetch();

        $refundAmount = 0;
        if ($payment) {
            // 计算95%退款金额
            $refundAmount = round((float)$payment['amount'] * 0.95, 2);
            $refundDesc = '退款金额：¥' . number_format($refundAmount, 2) . '（原金额：¥' . number_format((float)$payment['amount'], 2) . '，退款比例95%）';
            if ($reason) {
                $refundDesc .= '，取消原因：' . $reason;
            }
            
            // 更新支付状态为已退款（status=2表示refunded）
            $stmt = $pdo->prepare('UPDATE t_payment SET status = 2, description = CONCAT(COALESCE(description, ""), " | ", ?) WHERE payment_id = ?');
            $stmt->execute([$refundDesc, $payment['payment_id']]);
        } else {
            // 未支付的订单直接取消
            $stmt = $pdo->prepare('UPDATE t_payment SET status = 2 WHERE reservation_id = ? AND status = 0');
            $stmt->execute([$reservationId]);
        }

        $pdo->commit();

        $msg = '预约已取消';
        if ($refundAmount > 0) {
            $msg .= '，退款金额：¥' . number_format($refundAmount, 2) . '（95%）';
        }

        respOK([
            'reservation_id' => $reservationId,
            'refund_amount' => $refundAmount
        ], $msg);

    } catch (Exception $e) {
        $pdo->rollBack();
        respError('取消失败：' . $e->getMessage());
    }
}

/**
 * 获取Device管理员待审批的预约列表
 */
function getPendingForDevice(array $user): void
{
    $pdo = getDB();
    $roleType = 'device'; // Device管理员的角色类型

    // 查询当前需要设备管理员审批的预约（该角色还未审批过的）
    $stmt = $pdo->prepare('
        SELECT r.*, 
               u.real_name as user_name, u.user_type, u.phone as user_phone,
               d.device_name, d.model, d.location, d.rent_price,
               w.description as step_description, w.is_payment_required, w.is_parallel
        FROM t_reservation r
        JOIN t_user u ON r.user_id = u.user_id
        JOIN t_device d ON r.device_id = d.device_id
        JOIN t_approval_workflow w ON r.current_step = w.step_order AND u.user_type = w.user_type
        WHERE r.status = 0 AND w.role_type = ? AND w.is_enabled = 1
        ORDER BY r.created_at ASC
    ');
    $stmt->execute([$roleType]);
    $reservations = $stmt->fetchAll();

    // 过滤掉已经审批过的（针对并行审批情况）
    $filteredReservations = [];
    foreach ($reservations as $r) {
        $approvals = $r['approvals'] ? json_decode($r['approvals'], true) : [];
        // 检查该角色是否已审批
        if (!isset($approvals[$roleType])) {
            // 对于学生，需要检查导师是否已批准
            if ($r['user_type'] === 'student') {
                if (isset($approvals['advisor']) && $approvals['advisor']['action'] === 'approve') {
                    $filteredReservations[] = $r;
                }
            } else {
                // 教师和校外人员可以直接审批
                $filteredReservations[] = $r;
            }
        }
    }

    $items = array_map(function($r) {
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
            'rent_price' => (float)$r['rent_price'],
            'reserve_date' => $r['reserve_date'],
            'time_slot' => $r['time_slot'],
            'purpose' => $r['purpose'],
            'current_step' => $r['current_step'],
            'step_description' => $r['step_description'],
            'is_parallel' => (bool)$r['is_parallel'],
            'is_payment_required' => (bool)$r['is_payment_required'],
            'created_at' => $r['created_at']
        ];
    }, $filteredReservations);

    respOK([
        'items' => $items,
        'count' => count($items)
    ]);
}

/**
 * Device管理员批准预约
 */
function approveReservation(array $user): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $reservationId = (int)($input['reservation_id'] ?? 0);
    $remark = trim($input['remark'] ?? '');

    if (!$reservationId) {
        respError('预约ID不能为空');
    }

    $pdo = getDB();

    // 检查预约及当前审批步骤
    $stmt = $pdo->prepare('
        SELECT r.*, u.user_type
        FROM t_reservation r
        JOIN t_user u ON r.user_id = u.user_id
        WHERE r.reservation_id = ?
    ');
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        respNotFound('预约记录不存在');
    }

    if ($reservation['status'] != 0) {
        respError('该预约已处理');
    }

    // 获取当前步骤的所有审批角色
    $stmt = $pdo->prepare('
        SELECT * FROM t_approval_workflow 
        WHERE user_type = ? AND step_order = ? AND is_enabled = 1
    ');
    $stmt->execute([$reservation['user_type'], $reservation['current_step']]);
    $currentSteps = $stmt->fetchAll();

    if (empty($currentSteps)) {
        respError('审批流程配置错误');
    }

    // 检查当前用户是否有权审批（必须是device角色）
    $myStep = null;
    foreach ($currentSteps as $step) {
        if ($step['role_type'] === 'device') {
            $myStep = $step;
            break;
        }
    }

    if (!$myStep) {
        respForbidden('当前步骤不需要设备管理员审批');
    }

    // 检查是否已审批过
    $approvals = $reservation['approvals'] ? json_decode($reservation['approvals'], true) : [];
    if (isset($approvals['device'])) {
        respError('您已审批过此预约');
    }

    // 对于学生，需要检查导师是否已批准
    if ($reservation['user_type'] === 'student') {
        if (!isset($approvals['advisor']) || $approvals['advisor']['action'] !== 'approve') {
            respError('学生预约需要导师先批准');
        }
    }

    // 校外人员财务审批需检查付款状态（但设备管理员审批时不需要）
    if ($myStep['is_payment_required']) {
        $stmt = $pdo->prepare('SELECT * FROM t_payment WHERE reservation_id = ? AND status = 1');
        $stmt->execute([$reservationId]);
        if (!$stmt->fetch()) {
            respError('校外人员需先完成付款才能审批');
        }
    }

    try {
        $pdo->beginTransaction();

        // 记录审批日志
        $stmt = $pdo->prepare('
            INSERT INTO t_approval_log (reservation_id, step_order, role_type, approver_id, approver_type, approver_name, action, reason)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $reservationId,
            $reservation['current_step'],
            'device',
            $user['user_id'],
            'user',
            $user['real_name'],
            'approve',
            $remark
        ]);

        // 更新审批记录JSON
        $approvals['device'] = [
            'user_id' => $user['user_id'],
            'real_name' => $user['real_name'],
            'action' => 'approve',
            'remark' => $remark,
            'time' => date('Y-m-d H:i:s')
        ];

        // 检查并行审批是否全部完成
        $isParallel = (bool)$myStep['is_parallel'];
        $allParallelApproved = true;
        
        if ($isParallel) {
            foreach ($currentSteps as $step) {
                if ($step['is_parallel'] && !isset($approvals[$step['role_type']])) {
                    $allParallelApproved = false;
                    break;
                }
            }
        }

        if (!$isParallel || $allParallelApproved) {
            // 非并行 或 并行审批全部完成，检查是否有下一步
            $stmt = $pdo->prepare('
                SELECT * FROM t_approval_workflow 
                WHERE user_type = ? AND step_order > ? AND is_enabled = 1
                ORDER BY step_order ASC
                LIMIT 1
            ');
            $stmt->execute([$reservation['user_type'], $reservation['current_step']]);
            $nextStep = $stmt->fetch();

            if ($nextStep) {
                // 进入下一步
                $stmt = $pdo->prepare('UPDATE t_reservation SET current_step = ?, approvals = ? WHERE reservation_id = ?');
                $stmt->execute([$nextStep['step_order'], json_encode($approvals), $reservationId]);

                $pdo->commit();

                // 如果下一步需要付款，提示用户进入支付流程
                $msg = '已批准，等待' . $nextStep['description'];
                if ($nextStep['is_payment_required']) {
                    $msg = '已批准，请用户完成支付后等待' . $nextStep['description'];
                }

                respOK([
                    'reservation_id' => $reservationId,
                    'next_step' => $nextStep['step_order'],
                    'next_role' => $nextStep['role_type'],
                    'next_description' => $nextStep['description'],
                    'payment_required' => (bool)$nextStep['is_payment_required']
                ], $msg);
            } else {
                // 审批完成
                $stmt = $pdo->prepare('UPDATE t_reservation SET status = 1, approvals = ? WHERE reservation_id = ?');
                $stmt->execute([json_encode($approvals), $reservationId]);

                // 创建借用记录（operator_out_id使用user_id）
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
                    $user['user_id']
                ]);

                // 更新设备状态为借出
                $stmt = $pdo->prepare('UPDATE t_device SET status = 2 WHERE device_id = ?');
                $stmt->execute([$reservation['device_id']]);

                $pdo->commit();

                respOK([
                    'reservation_id' => $reservationId,
                    'status' => 'approved'
                ], '预约已批准，借用记录已创建');
            }
        } else {
            // 并行审批，还有其他角色未审批
            $stmt = $pdo->prepare('UPDATE t_reservation SET approvals = ? WHERE reservation_id = ?');
            $stmt->execute([json_encode($approvals), $reservationId]);

            $pdo->commit();

            // 找出还未审批的角色
            $pendingRoles = [];
            foreach ($currentSteps as $step) {
                if ($step['is_parallel'] && !isset($approvals[$step['role_type']])) {
                    $pendingRoles[] = $step['description'];
                }
            }

            respOK([
                'reservation_id' => $reservationId,
                'status' => 'partial_approved',
                'pending_approvals' => $pendingRoles
            ], '已批准，等待' . implode('、', $pendingRoles));
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        respError('审批失败：' . $e->getMessage());
    }
}

/**
 * Device管理员驳回预约
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

    // 检查预约
    $stmt = $pdo->prepare('
        SELECT r.*, u.user_type
        FROM t_reservation r
        JOIN t_user u ON r.user_id = u.user_id
        WHERE r.reservation_id = ?
    ');
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        respNotFound('预约记录不存在');
    }

    if ($reservation['status'] != 0) {
        respError('该预约已处理');
    }

    // 验证当前步骤是否需要设备管理员审批
    $stmt = $pdo->prepare('
        SELECT * FROM t_approval_workflow 
        WHERE user_type = ? AND step_order = ? AND role_type = ? AND is_enabled = 1
    ');
    $stmt->execute([$reservation['user_type'], $reservation['current_step'], 'device']);
    $currentStep = $stmt->fetch();

    if (!$currentStep) {
        respForbidden('您没有审批权限');
    }

    try {
        $pdo->beginTransaction();

        // 记录审批日志
        $stmt = $pdo->prepare('
            INSERT INTO t_approval_log (reservation_id, step_order, role_type, approver_id, approver_type, approver_name, action, reason)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $reservationId,
            $reservation['current_step'],
            'device',
            $user['user_id'],
            'user',
            $user['real_name'],
            'reject',
            $reason
        ]);

        // 更新审批记录JSON
        $approvals = $reservation['approvals'] ? json_decode($reservation['approvals'], true) : [];
        $approvals['device'] = [
            'user_id' => $user['user_id'],
            'real_name' => $user['real_name'],
            'action' => 'reject',
            'reason' => $reason,
            'time' => date('Y-m-d H:i:s')
        ];

        $stmt = $pdo->prepare('UPDATE t_reservation SET status = 2, reject_reason = ?, approvals = ? WHERE reservation_id = ?');
        $stmt->execute([$reason, json_encode($approvals), $reservationId]);

        $pdo->commit();

        respOK([
            'reservation_id' => $reservationId,
            'status' => 'rejected'
        ], '预约已驳回');

    } catch (Exception $e) {
        $pdo->rollBack();
        respError('驳回失败：' . $e->getMessage());
    }
}
