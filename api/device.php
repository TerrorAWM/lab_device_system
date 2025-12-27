<?php
/**
 * 设备管理 API
 * 
 * GET /api/device.php - 获取设备列表
 * GET /api/device.php?id=X - 获取设备详情
 * GET /api/device.php?action=categories - 获取设备类别
 */

declare(strict_types=1);

require_once __DIR__ . '/_init.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'categories':
        getCategories();
        break;
    default:
        if (isset($_GET['id'])) {
            getDeviceDetail((int)$_GET['id']);
        } else {
            getDeviceList();
        }
        break;
}

/**
 * 获取设备列表
 */
function getDeviceList(): void
{
    $user = optionalAuth();
    $pdo = getDB();

    // 分页参数
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int)($_GET['page_size'] ?? 20)));
    $offset = ($page - 1) * $pageSize;

    // 筛选条件
    $keyword = trim($_GET['keyword'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $status = $_GET['status'] ?? '';

    $where = ['1=1'];
    $params = [];

    if ($keyword) {
        $where[] = '(device_name LIKE ? OR model LIKE ?)';
        $params[] = "%$keyword%";
        $params[] = "%$keyword%";
    }

    if ($category && $category !== '全部设备') {
        $where[] = 'category = ?';
        $params[] = $category;
    }

    if ($status !== '') {
        $where[] = 'status = ?';
        $params[] = (int)$status;
    }

    $whereClause = implode(' AND ', $where);

    // 查询总数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM t_device WHERE $whereClause");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // 查询数据
    $stmt = $pdo->prepare("
        SELECT device_id, device_name, model, manufacturer, category, 
               status, location, rent_price, image_url, purpose
        FROM t_device 
        WHERE $whereClause
        ORDER BY device_id DESC
        LIMIT $pageSize OFFSET $offset
    ");
    $stmt->execute($params);
    $devices = $stmt->fetchAll();

    // 格式化数据
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
            'rent_price' => (float)$d['rent_price'],
            'image_url' => $d['image_url'],
            'purpose' => $d['purpose']
        ];
    }, $devices);

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
 * 获取设备详情
 */
function getDeviceDetail(int $deviceId): void
{
    $user = optionalAuth();
    $pdo = getDB();

    $stmt = $pdo->prepare('SELECT * FROM t_device WHERE device_id = ?');
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch();

    if (!$device) {
        respNotFound('设备不存在');
    }

    $statusMap = [1 => 'available', 2 => 'borrowed', 3 => 'maintenance', 4 => 'scrapped'];

    $result = [
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
        'created_at' => $device['created_at']
    ];

    // 已登录用户可查看设备被占用的时段
    if ($user) {
        $stmt = $pdo->prepare('
            SELECT reserve_date, time_slot 
            FROM t_reservation 
            WHERE device_id = ? AND status IN (0, 1) AND reserve_date >= CURDATE()
            ORDER BY reserve_date, time_slot
        ');
        $stmt->execute([$deviceId]);
        $result['occupied_slots'] = $stmt->fetchAll();
    }

    respOK($result);
}

/**
 * 获取设备类别列表
 */
function getCategories(): void
{
    $pdo = getDB();

    $stmt = $pdo->query('
        SELECT category, COUNT(*) as device_count 
        FROM t_device 
        WHERE category IS NOT NULL AND category != ""
        GROUP BY category 
        ORDER BY device_count DESC
    ');
    $categories = $stmt->fetchAll();

    // 添加"全部设备"选项
    $totalStmt = $pdo->query('SELECT COUNT(*) FROM t_device');
    $total = (int)$totalStmt->fetchColumn();

    array_unshift($categories, [
        'category' => '全部设备',
        'device_count' => $total
    ]);

    respOK($categories);
}
