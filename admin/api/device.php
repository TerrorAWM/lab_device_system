<?php
/**
 * 设备台账管理 API
 * 
 * GET /admin/api/device.php - 设备列表
 * GET /admin/api/device.php?id=X - 设备详情
 * POST /admin/api/device.php?action=create - 新增设备
 * POST /admin/api/device.php?action=update - 更新设备
 * POST /admin/api/device.php?action=update_status - 更新状态
 * POST /admin/api/device.php?action=delete - 删除设备
 */

declare(strict_types=1);

require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/_util.php';

$admin = requireAdminAuth();
$action = $_GET['action'] ?? '';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['id'])) {
            getDeviceDetail((int)$_GET['id']);
        } else {
            getDeviceList();
        }
        break;
    case 'POST':
        switch ($action) {
            case 'create':
                createDevice($admin);
                break;
            case 'update':
                updateDevice($admin);
                break;
            case 'update_status':
                updateDeviceStatus($admin);
                break;
            case 'delete':
                deleteDevice($admin);
                break;
            default:
                respError('未知操作');
        }
        break;
    default:
        respError('请求方法不允许', 1, 405);
}

/**
 * 获取设备列表
 */
function getDeviceList(): void
{
    $pdo = getDB();
    $pagination = getPagination();

    $keyword = trim($_GET['keyword'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $status = $_GET['status'] ?? '';

    $where = ['1=1'];
    $params = [];

    if ($keyword) {
        $where[] = '(d.device_name LIKE ? OR d.model LIKE ?)';
        $params[] = "%$keyword%";
        $params[] = "%$keyword%";
    }

    if ($category) {
        $where[] = 'd.category = ?';
        $params[] = $category;
    }

    if ($status !== '') {
        $where[] = 'd.status = ?';
        $params[] = (int)$status;
    }

    $whereClause = implode(' AND ', $where);

    // 查询总数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM t_device d WHERE $whereClause");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // 查询数据（含当前借用人信息）
    $sql = "
        SELECT d.*, 
               b.user_id as current_borrower_id,
               u.real_name as current_borrower_name
        FROM t_device d
        LEFT JOIN t_borrow_record b ON d.device_id = b.device_id AND b.status = 1
        LEFT JOIN t_user u ON b.user_id = u.user_id
        WHERE $whereClause
        ORDER BY d.device_id DESC
        LIMIT {$pagination['page_size']} OFFSET {$pagination['offset']}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $devices = $stmt->fetchAll();

    $statusMap = [1 => 'available', 2 => 'borrowed', 3 => 'maintenance', 4 => 'scrapped'];

    $items = array_map(function($d) use ($statusMap) {
        return [
            'id' => $d['device_id'],
            'name' => $d['device_name'],
            'model' => $d['model'],
            'manufacturer' => $d['manufacturer'],
            'category' => $d['category'],
            'status' => $statusMap[$d['status']] ?? 'unknown',
            'status_code' => $d['status'],
            'location' => $d['location'],
            'price' => (float)$d['price'],
            'rent_price' => (float)$d['rent_price'],
            'purchase_date' => $d['purchase_date'],
            'current_borrower' => $d['current_borrower_id'] ? [
                'user_id' => $d['current_borrower_id'],
                'name' => $d['current_borrower_name']
            ] : null,
            'created_at' => $d['created_at']
        ];
    }, $devices);

    respOK(buildPaginatedResponse($items, $total, $pagination));
}

/**
 * 获取设备详情
 */
function getDeviceDetail(int $deviceId): void
{
    $pdo = getDB();

    $stmt = $pdo->prepare('SELECT * FROM t_device WHERE device_id = ?');
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch();

    if (!$device) {
        respNotFound('设备不存在');
    }

    // 获取借用统计
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM t_borrow_record WHERE device_id = ?');
    $stmt->execute([$deviceId]);
    $totalBorrows = (int)$stmt->fetchColumn();

    // 获取当前借用人
    $stmt = $pdo->prepare('
        SELECT b.*, u.real_name, u.phone
        FROM t_borrow_record b
        JOIN t_user u ON b.user_id = u.user_id
        WHERE b.device_id = ? AND b.status = 1
    ');
    $stmt->execute([$deviceId]);
    $currentBorrow = $stmt->fetch();

    $statusMap = [1 => 'available', 2 => 'borrowed', 3 => 'maintenance', 4 => 'scrapped'];

    respOK([
        'id' => $device['device_id'],
        'name' => $device['device_name'],
        'model' => $device['model'],
        'manufacturer' => $device['manufacturer'],
        'category' => $device['category'],
        'status' => $statusMap[$device['status']] ?? 'unknown',
        'status_code' => $device['status'],
        'location' => $device['location'],
        'price' => (float)$device['price'],
        'rent_price' => (float)$device['rent_price'],
        'image_url' => $device['image_url'],
        'purpose' => $device['purpose'],
        'purchase_date' => $device['purchase_date'],
        'created_at' => $device['created_at'],
        'total_borrows' => $totalBorrows,
        'current_borrower' => $currentBorrow ? [
            'user_id' => $currentBorrow['user_id'],
            'name' => $currentBorrow['real_name'],
            'phone' => $currentBorrow['phone'],
            'borrow_date' => $currentBorrow['borrow_date'],
            'time_slot' => $currentBorrow['time_slot']
        ] : null
    ]);
}

/**
 * 新增设备
 */
function createDevice(array $admin): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $deviceName = trim($input['device_name'] ?? '');
    $model = trim($input['model'] ?? '');
    $manufacturer = trim($input['manufacturer'] ?? '');
    $category = trim($input['category'] ?? '');
    $location = trim($input['location'] ?? '');
    $price = (float)($input['price'] ?? 0);
    $rentPrice = (float)($input['rent_price'] ?? 0);
    $purpose = trim($input['purpose'] ?? '');
    $purchaseDate = $input['purchase_date'] ?? null;

    if (empty($deviceName)) {
        respError('设备名称不能为空');
    }

    if (empty($model)) {
        respError('型号不能为空');
    }

    $pdo = getDB();

    $stmt = $pdo->prepare('
        INSERT INTO t_device (device_name, model, manufacturer, category, location, 
                              price, rent_price, purpose, purchase_date, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ');
    $stmt->execute([
        $deviceName, $model, $manufacturer ?: null, $category ?: null,
        $location ?: null, $price, $rentPrice, $purpose ?: null, $purchaseDate
    ]);
    $deviceId = (int)$pdo->lastInsertId();

    respOK([
        'device_id' => $deviceId,
        'device_name' => $deviceName
    ], '设备添加成功');
}

/**
 * 更新设备
 */
function updateDevice(array $admin): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $deviceId = (int)($input['device_id'] ?? 0);
    if (!$deviceId) {
        respError('设备ID不能为空');
    }

    $pdo = getDB();

    // 检查设备是否存在
    $stmt = $pdo->prepare('SELECT device_id FROM t_device WHERE device_id = ?');
    $stmt->execute([$deviceId]);
    if (!$stmt->fetch()) {
        respNotFound('设备不存在');
    }

    // 构建更新字段
    $updates = [];
    $params = [];

    $fields = ['device_name', 'model', 'manufacturer', 'category', 'location', 
               'price', 'rent_price', 'purpose', 'purchase_date', 'image_url'];

    foreach ($fields as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = ?";
            $params[] = $input[$field] === '' ? null : $input[$field];
        }
    }

    if (empty($updates)) {
        respError('没有要更新的字段');
    }

    $params[] = $deviceId;
    $sql = 'UPDATE t_device SET ' . implode(', ', $updates) . ' WHERE device_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    respOK(null, '设备更新成功');
}

/**
 * 更新设备状态
 */
function updateDeviceStatus(array $admin): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $deviceId = (int)($input['device_id'] ?? 0);
    $status = (int)($input['status'] ?? 0);

    if (!$deviceId) {
        respError('设备ID不能为空');
    }

    if (!in_array($status, [1, 2, 3, 4])) {
        respError('无效的状态值');
    }

    $pdo = getDB();

    // 检查设备是否存在
    $stmt = $pdo->prepare('SELECT device_id, status FROM t_device WHERE device_id = ?');
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch();

    if (!$device) {
        respNotFound('设备不存在');
    }

    // 如果当前是借出状态且有未归还记录，不允许直接修改
    if ($device['status'] == 2 && $status != 2) {
        $stmt = $pdo->prepare('SELECT record_id FROM t_borrow_record WHERE device_id = ? AND status = 1');
        $stmt->execute([$deviceId]);
        if ($stmt->fetch()) {
            respError('设备当前正在借用中，请先归还');
        }
    }

    $stmt = $pdo->prepare('UPDATE t_device SET status = ? WHERE device_id = ?');
    $stmt->execute([$status, $deviceId]);

    respOK(null, '状态更新成功');
}

/**
 * 删除设备
 */
function deleteDevice(array $admin): void
{
    // 只有 supervisor 可以删除
    if ($admin['role'] !== 'supervisor') {
        respForbidden('权限不足');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $deviceId = (int)($input['device_id'] ?? 0);

    if (!$deviceId) {
        respError('设备ID不能为空');
    }

    $pdo = getDB();

    // 检查设备是否存在
    $stmt = $pdo->prepare('SELECT device_id, status FROM t_device WHERE device_id = ?');
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch();

    if (!$device) {
        respNotFound('设备不存在');
    }

    // 只能删除报废状态的设备
    if ($device['status'] != 4) {
        respError('只能删除报废状态的设备');
    }

    // 检查是否有未完成的借用
    $stmt = $pdo->prepare('SELECT record_id FROM t_borrow_record WHERE device_id = ? AND status = 1');
    $stmt->execute([$deviceId]);
    if ($stmt->fetch()) {
        respError('设备有未完成的借用，无法删除');
    }

    $stmt = $pdo->prepare('DELETE FROM t_device WHERE device_id = ?');
    $stmt->execute([$deviceId]);

    respOK(null, '设备删除成功');
}
