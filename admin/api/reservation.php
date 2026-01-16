<?php
/**
 * 预约审批管理 API (多级审批版本 - 支持并行审批)
 * 
 * GET /admin/api/reservation.php - 所有预约列表
 * GET /admin/api/reservation.php?id=X - 预约详情
 * GET /admin/api/reservation.php?action=pending - 获取当前管理员待审批列表
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
        } elseif ($action === 'pending') {
            getPendingForAdmin($admin);
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

    $items = array_map(function($r) use ($statusMap, $pdo) {
        // 获取完整审批流程状态
        $stmt = $pdo->prepare('
            SELECT * FROM t_approval_workflow 
            WHERE user_type = ? AND is_enabled = 1 
            ORDER BY step_order ASC
        ');
        $stmt->execute([$r['user_type']]);
        $workflow = $stmt->fetchAll();
        
        $approvals = $r['approvals'] ? json_decode($r['approvals'], true) : [];
        
        $workflowNodes = array_map(function($w) use ($r, $approvals) {
            $status = 'waiting';
            if (isset($approvals[$w['role_type']])) {
                $status = $approvals[$w['role_type']]['action'] === 'approve' ? 'approved' : 'rejected';
            } elseif ($r['status'] == 0 && $r['current_step'] == $w['step_order']) {
                $status = 'pending';
            } elseif ($r['status'] == 2) { // 已驳回
                $status = 'rejected';
            }
            
            return [
                'node_type' => $w['role_type'],
                'description' => $w['description'],
                'status' => $status
            ];
        }, $workflow);
        
        $userTypeTextMap = ['teacher' => '教师', 'student' => '学生', 'external' => '校外人员'];

        return [
            'id' => $r['reservation_id'],
            'user_id' => $r['user_id'],
            'real_name' => $r['user_name'],
            'username' => $r['username'] ?? '',
            'user_type' => $r['user_type'],
            'user_type_text' => $userTypeTextMap[$r['user_type']] ?? $r['user_type'],
            'device_id' => $r['device_id'],
            'device_name' => $r['device_name'],
            'reserve_date' => $r['reserve_date'],
            'time_slot' => $r['time_slot'],
            'purpose' => $r['purpose'],
            'status' => $statusMap[$r['status']] ?? 'unknown',
            'status_code' => $r['status'],
            'workflow_nodes' => $workflowNodes,
            'created_at' => $r['created_at']
        ];
    }, $reservations);

    respOK(buildPaginatedResponse($items, $total, $pagination));
}

/**
 * 获取当前管理员待审批的预约
 */
function getPendingForAdmin(array $admin): void
{
    $pdo = getDB();

    // 根据管理员角色确定可审批的 role_type
    $roleType = $admin['role']; // device, supervisor, finance

    // 查询当前需要该角色审批的预约（该角色还未审批过的）
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
            $filteredReservations[] = $r;
        }
    }

    $items = array_map(function($r) {
        // 解析审批记录
        $approvals = $r['approvals'] ? json_decode($r['approvals'], true) : [];
        
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
            'approvals' => $approvals, // 包含审批记录
            'created_at' => $r['created_at']
        ];
    }, $filteredReservations);

    respOK([
        'items' => $items,
        'count' => count($items),
        'admin_role' => $roleType
    ]);
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

    // 获取审批日志
    $stmt = $pdo->prepare('
        SELECT * FROM t_approval_log WHERE reservation_id = ? ORDER BY created_at ASC
    ');
    $stmt->execute([$reservationId]);
    $logs = $stmt->fetchAll();

    // 获取完整审批流程
    $stmt = $pdo->prepare('
        SELECT * FROM t_approval_workflow WHERE user_type = ? AND is_enabled = 1 ORDER BY step_order ASC
    ');
    $stmt->execute([$reservation['user_type']]);
    $workflow = $stmt->fetchAll();

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
        'current_step' => $reservation['current_step'],
        'reject_reason' => $reservation['reject_reason'],
        'approvals' => $reservation['approvals'] ? json_decode($reservation['approvals'], true) : null,
        'approval_logs' => $logs,
        'workflow' => $workflow,
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

    // 检查当前管理员是否有权审批
    $myStep = null;
    foreach ($currentSteps as $step) {
        if ($step['role_type'] === $admin['role']) {
            $myStep = $step;
            break;
        }
    }

    if (!$myStep) {
        respForbidden('当前步骤不需要您审批');
    }

    // 检查是否已审批过
    $approvals = $reservation['approvals'] ? json_decode($reservation['approvals'], true) : [];
    if (isset($approvals[$admin['role']])) {
        respError('您已审批过此预约');
    }

    // 校外人员财务审批需检查付款状态
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
            $admin['role'],
            $admin['admin_id'],
            'admin',
            $admin['real_name'],
            'approve',
            $remark
        ]);

        // 更新审批记录JSON
        $approvals[$admin['role']] = [
            'admin_id' => $admin['admin_id'],
            'real_name' => $admin['real_name'],
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

    // 验证当前步骤是否需要此管理员角色审批
    $stmt = $pdo->prepare('
        SELECT * FROM t_approval_workflow 
        WHERE user_type = ? AND step_order = ? AND role_type = ? AND is_enabled = 1
    ');
    $stmt->execute([$reservation['user_type'], $reservation['current_step'], $admin['role']]);
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
            $admin['role'],
            $admin['admin_id'],
            'admin',
            $admin['real_name'],
            'reject',
            $reason
        ]);

        // 更新审批记录JSON
        $approvals = $reservation['approvals'] ? json_decode($reservation['approvals'], true) : [];
        $approvals[$admin['role']] = [
            'admin_id' => $admin['admin_id'],
            'real_name' => $admin['real_name'],
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
