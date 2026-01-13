<?php
/**
 * 设备检修记录 API
 * 
 * GET /admin/api/maintenance.php - 检修记录列表
 * GET /admin/api/maintenance.php?id=X - 检修记录详情
 * GET /admin/api/maintenance.php?device_id=X - 按设备ID查询检修记录
 * POST /admin/api/maintenance.php?action=create - 新增检修记录
 * POST /admin/api/maintenance.php?action=update - 更新检修记录
 * POST /admin/api/maintenance.php?action=delete - 删除检修记录
 */

declare(strict_types=1);

require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/_util.php';

$admin = requireAdminAuth();
$action = $_GET['action'] ?? '';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['id'])) {
            getMaintenanceDetail((int)$_GET['id']);
        } elseif (isset($_GET['device_id'])) {
            getMaintenanceByDevice((int)$_GET['device_id']);
        } else {
            getMaintenanceList();
        }
        break;
    case 'POST':
        switch ($action) {
            case 'create':
                createMaintenance($admin);
                break;
            case 'update':
                updateMaintenance($admin);
                break;
            case 'delete':
                deleteMaintenance($admin);
                break;
            default:
                respError('未知操作');
        }
        break;
    default:
        respError('请求方法不允许', 1, 405);
}

/**
 * 获取检修记录列表
 */
function getMaintenanceList(): void
{
    $pdo = getDB();
    $pagination = getPagination();

    $keyword = trim($_GET['keyword'] ?? '');
    $deviceId = isset($_GET['device_id']) ? (int)$_GET['device_id'] : null;
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';

    $where = ['1=1'];
    $params = [];

    if ($keyword) {
        $where[] = '(d.device_name LIKE ? OR m.reason LIKE ?)';
        $params[] = "%$keyword%";
        $params[] = "%$keyword%";
    }

    if ($deviceId) {
        $where[] = 'm.device_id = ?';
        $params[] = $deviceId;
    }

    if ($startDate) {
        $where[] = 'm.start_time >= ?';
        $params[] = $startDate;
    }

    if ($endDate) {
        $where[] = 'm.end_time <= ?';
        $params[] = $endDate . ' 23:59:59';
    }

    $whereClause = implode(' AND ', $where);

    // 查询总数
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM t_device_maintenance m 
        JOIN t_device d ON m.device_id = d.device_id 
        WHERE $whereClause
    ");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // 查询数据
    $sql = "
        SELECT m.*, 
               d.device_name, 
               d.model,
               a.real_name as operator_name
        FROM t_device_maintenance m
        JOIN t_device d ON m.device_id = d.device_id
        LEFT JOIN t_admin a ON m.operator_id = a.admin_id
        WHERE $whereClause
        ORDER BY m.id DESC
        LIMIT {$pagination['page_size']} OFFSET {$pagination['offset']}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    $items = array_map(function($r) {
        return [
            'id' => $r['id'],
            'device_id' => $r['device_id'],
            'device_name' => $r['device_name'],
            'model' => $r['model'],
            'start_time' => $r['start_time'],
            'end_time' => $r['end_time'],
            'reason' => $r['reason'],
            'operator_id' => $r['operator_id'],
            'operator_name' => $r['operator_name'],
            'created_at' => $r['created_at']
        ];
    }, $records);

    respOK(buildPaginatedResponse($items, $total, $pagination));
}

/**
 * 获取检修记录详情
 */
function getMaintenanceDetail(int $id): void
{
    $pdo = getDB();

    $stmt = $pdo->prepare('
        SELECT m.*, 
               d.device_name, 
               d.model,
               d.location,
               a.real_name as operator_name
        FROM t_device_maintenance m
        JOIN t_device d ON m.device_id = d.device_id
        LEFT JOIN t_admin a ON m.operator_id = a.admin_id
        WHERE m.id = ?
    ');
    $stmt->execute([$id]);
    $record = $stmt->fetch();

    if (!$record) {
        respNotFound('检修记录不存在');
    }

    respOK([
        'id' => $record['id'],
        'device_id' => $record['device_id'],
        'device_name' => $record['device_name'],
        'model' => $record['model'],
        'location' => $record['location'],
        'start_time' => $record['start_time'],
        'end_time' => $record['end_time'],
        'reason' => $record['reason'],
        'operator_id' => $record['operator_id'],
        'operator_name' => $record['operator_name'],
        'created_at' => $record['created_at']
    ]);
}

/**
 * 按设备ID获取检修记录
 */
function getMaintenanceByDevice(int $deviceId): void
{
    $pdo = getDB();
    $pagination = getPagination();

    // 检查设备是否存在
    $stmt = $pdo->prepare('SELECT device_id, device_name FROM t_device WHERE device_id = ?');
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch();

    if (!$device) {
        respNotFound('设备不存在');
    }

    // 查询总数
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM t_device_maintenance WHERE device_id = ?');
    $stmt->execute([$deviceId]);
    $total = (int)$stmt->fetchColumn();

    // 查询数据
    $stmt = $pdo->prepare("
        SELECT m.*, a.real_name as operator_name
        FROM t_device_maintenance m
        LEFT JOIN t_admin a ON m.operator_id = a.admin_id
        WHERE m.device_id = ?
        ORDER BY m.start_time DESC
        LIMIT {$pagination['page_size']} OFFSET {$pagination['offset']}
    ");
    $stmt->execute([$deviceId]);
    $records = $stmt->fetchAll();

    $items = array_map(function($r) {
        return [
            'id' => $r['id'],
            'start_time' => $r['start_time'],
            'end_time' => $r['end_time'],
            'reason' => $r['reason'],
            'operator_id' => $r['operator_id'],
            'operator_name' => $r['operator_name'],
            'created_at' => $r['created_at']
        ];
    }, $records);

    respOK([
        'device' => [
            'device_id' => $device['device_id'],
            'device_name' => $device['device_name']
        ],
        'items' => $items,
        'pagination' => [
            'page' => $pagination['page'],
            'page_size' => $pagination['page_size'],
            'total' => $total,
            'total_pages' => ceil($total / $pagination['page_size']),
        ]
    ]);
}

/**
 * 新增检修记录
 */
function createMaintenance(array $admin): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $deviceId = (int)($input['device_id'] ?? 0);
    $startTime = trim($input['start_time'] ?? '');
    $endTime = trim($input['end_time'] ?? '');
    $reason = trim($input['reason'] ?? '');

    if (!$deviceId) {
        respError('设备ID不能为空');
    }

    if (empty($startTime)) {
        respError('检修开始时间不能为空');
    }

    if (empty($endTime)) {
        respError('检修结束时间不能为空');
    }

    // 验证时间格式和顺序
    $startTs = strtotime($startTime);
    $endTs = strtotime($endTime);

    if (!$startTs || !$endTs) {
        respError('时间格式无效');
    }

    if ($endTs < $startTs) {
        respError('结束时间不能早于开始时间');
    }

    $pdo = getDB();

    // 检查设备是否存在
    $stmt = $pdo->prepare('SELECT device_id, status FROM t_device WHERE device_id = ?');
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch();

    if (!$device) {
        respNotFound('设备不存在');
    }

    // 检查时间段是否与已有检修记录冲突
    $stmt = $pdo->prepare('
        SELECT id FROM t_device_maintenance 
        WHERE device_id = ? 
        AND ((start_time <= ? AND end_time >= ?) OR (start_time <= ? AND end_time >= ?) OR (start_time >= ? AND end_time <= ?))
    ');
    $stmt->execute([$deviceId, $startTime, $startTime, $endTime, $endTime, $startTime, $endTime]);
    if ($stmt->fetch()) {
        respError('该时间段与已有检修记录冲突');
    }

    // 开始事务
    $pdo->beginTransaction();
    try {
        // 插入检修记录
        $stmt = $pdo->prepare('
            INSERT INTO t_device_maintenance (device_id, start_time, end_time, reason, operator_id)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $deviceId,
            $startTime,
            $endTime,
            $reason ?: null,
            $admin['admin_id']
        ]);
        $maintenanceId = (int)$pdo->lastInsertId();

        // 如果设备当前可用且检修时间包含当前时间，将设备状态改为检修中
        $now = time();
        if ($device['status'] == 1 && $startTs <= $now && $endTs >= $now) {
            $stmt = $pdo->prepare('UPDATE t_device SET status = 3 WHERE device_id = ?');
            $stmt->execute([$deviceId]);
        }

        $pdo->commit();

        respOK([
            'id' => $maintenanceId,
            'device_id' => $deviceId
        ], '检修记录添加成功');
    } catch (Exception $e) {
        $pdo->rollBack();
        respError('操作失败：' . $e->getMessage());
    }
}

/**
 * 更新检修记录
 */
function updateMaintenance(array $admin): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $id = (int)($input['id'] ?? 0);
    if (!$id) {
        respError('检修记录ID不能为空');
    }

    $pdo = getDB();

    // 检查记录是否存在
    $stmt = $pdo->prepare('SELECT * FROM t_device_maintenance WHERE id = ?');
    $stmt->execute([$id]);
    $record = $stmt->fetch();

    if (!$record) {
        respNotFound('检修记录不存在');
    }

    // 构建更新字段
    $updates = [];
    $params = [];

    if (isset($input['start_time'])) {
        $startTime = trim($input['start_time']);
        if (empty($startTime)) {
            respError('检修开始时间不能为空');
        }
        $updates[] = 'start_time = ?';
        $params[] = $startTime;
    }

    if (isset($input['end_time'])) {
        $endTime = trim($input['end_time']);
        if (empty($endTime)) {
            respError('检修结束时间不能为空');
        }
        $updates[] = 'end_time = ?';
        $params[] = $endTime;
    }

    if (isset($input['reason'])) {
        $updates[] = 'reason = ?';
        $params[] = $input['reason'] === '' ? null : $input['reason'];
    }

    if (empty($updates)) {
        respError('没有要更新的字段');
    }

    // 验证时间顺序
    $newStartTime = $input['start_time'] ?? $record['start_time'];
    $newEndTime = $input['end_time'] ?? $record['end_time'];
    $startTs = strtotime($newStartTime);
    $endTs = strtotime($newEndTime);

    if ($endTs < $startTs) {
        respError('结束时间不能早于开始时间');
    }

    // 检查时间段是否与其他检修记录冲突
    $stmt = $pdo->prepare('
        SELECT id FROM t_device_maintenance 
        WHERE device_id = ? AND id != ?
        AND ((start_time <= ? AND end_time >= ?) OR (start_time <= ? AND end_time >= ?) OR (start_time >= ? AND end_time <= ?))
    ');
    $stmt->execute([$record['device_id'], $id, $newStartTime, $newStartTime, $newEndTime, $newEndTime, $newStartTime, $newEndTime]);
    if ($stmt->fetch()) {
        respError('该时间段与其他检修记录冲突');
    }

    $params[] = $id;
    $sql = 'UPDATE t_device_maintenance SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    respOK(null, '检修记录更新成功');
}

/**
 * 删除检修记录
 */
function deleteMaintenance(array $admin): void
{
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);

    if (!$id) {
        respError('检修记录ID不能为空');
    }

    $pdo = getDB();

    // 检查记录是否存在
    $stmt = $pdo->prepare('SELECT * FROM t_device_maintenance WHERE id = ?');
    $stmt->execute([$id]);
    $record = $stmt->fetch();

    if (!$record) {
        respNotFound('检修记录不存在');
    }

    // 删除记录
    $stmt = $pdo->prepare('DELETE FROM t_device_maintenance WHERE id = ?');
    $stmt->execute([$id]);

    // 检查设备是否还有其他正在进行的检修
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare('
        SELECT id FROM t_device_maintenance 
        WHERE device_id = ? AND start_time <= ? AND end_time >= ?
    ');
    $stmt->execute([$record['device_id'], $now, $now]);
    
    if (!$stmt->fetch()) {
        // 没有正在进行的检修，检查设备当前状态是否为检修中
        $stmt = $pdo->prepare('SELECT status FROM t_device WHERE device_id = ?');
        $stmt->execute([$record['device_id']]);
        $device = $stmt->fetch();
        
        if ($device && $device['status'] == 3) {
            // 恢复设备为可用状态
            $stmt = $pdo->prepare('UPDATE t_device SET status = 1 WHERE device_id = ?');
            $stmt->execute([$record['device_id']]);
        }
    }

    respOK(null, '检修记录删除成功');
}
