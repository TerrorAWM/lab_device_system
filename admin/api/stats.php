<?php
/**
 * 统计报表 API
 * 
 * GET /admin/api/stats.php?action=dashboard - 仪表盘数据
 * GET /admin/api/stats.php?action=device_usage - 设备使用统计
 * GET /admin/api/stats.php?action=revenue - 收入统计
 */

declare(strict_types=1);

require_once __DIR__ . '/_init.php';

$admin = requireAdminAuth();
$action = $_GET['action'] ?? 'dashboard';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respError('请求方法不允许', 1, 405);
}

switch ($action) {
    case 'dashboard':
        getDashboard();
        break;
    case 'device_usage':
        getDeviceUsage();
        break;
    case 'revenue':
        getRevenue();
        break;
    default:
        respError('未知操作');
}

/**
 * 仪表盘数据
 */
function getDashboard(): void
{
    $pdo = getDB();

    // 设备统计
    $stmt = $pdo->query('SELECT COUNT(*) FROM t_device');
    $totalDevices = (int)$stmt->fetchColumn();

    $stmt = $pdo->query('SELECT COUNT(*) FROM t_device WHERE status = 1');
    $availableDevices = (int)$stmt->fetchColumn();

    $stmt = $pdo->query('SELECT COUNT(*) FROM t_device WHERE status = 2');
    $borrowedDevices = (int)$stmt->fetchColumn();

    $stmt = $pdo->query('SELECT COUNT(*) FROM t_device WHERE status = 3');
    $maintenanceDevices = (int)$stmt->fetchColumn();

    // 用户统计
    $stmt = $pdo->query("SELECT COUNT(*) FROM t_user WHERE role = 'user'");
    $totalUsers = (int)$stmt->fetchColumn();

    // 借用统计
    $stmt = $pdo->query('SELECT COUNT(*) FROM t_borrow_record WHERE status = 1');
    $activeBorrows = (int)$stmt->fetchColumn();

    // 待审批预约
    $stmt = $pdo->query('SELECT COUNT(*) FROM t_reservation WHERE status = 0');
    $pendingReservations = (int)$stmt->fetchColumn();

    // 今日统计
    $today = date('Y-m-d');

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM t_reservation WHERE DATE(created_at) = ?');
    $stmt->execute([$today]);
    $todayReservations = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM t_borrow_record WHERE DATE(actual_return) = ?');
    $stmt->execute([$today]);
    $todayReturns = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM t_payment WHERE status = 1 AND DATE(pay_time) = ?');
    $stmt->execute([$today]);
    $todayRevenue = (float)$stmt->fetchColumn();

    // 最近借用记录
    $stmt = $pdo->query('
        SELECT b.*, u.real_name, u.username, d.device_name
        FROM t_borrow_record b
        JOIN t_user u ON b.user_id = u.user_id
        JOIN t_device d ON b.device_id = d.device_id
        ORDER BY b.borrow_date DESC
        LIMIT 5
    ');
    $recentBorrows = $stmt->fetchAll();

    respOK([
        'summary' => [
            'total_devices' => $totalDevices,
            'available_devices' => $availableDevices,
            'borrowed_devices' => $borrowedDevices,
            'maintenance_devices' => $maintenanceDevices,
            'total_users' => $totalUsers,
            'active_borrows' => $activeBorrows,
            'pending_reservations' => $pendingReservations
        ],
        'today' => [
            'new_reservations' => $todayReservations,
            'completed_returns' => $todayReturns,
            'revenue' => $todayRevenue
        ],
        'recent_borrows' => array_map(function($b) {
            return [
                'id' => $b['record_id'],
                'real_name' => $b['real_name'],
                'username' => $b['username'],
                'device_name' => $b['device_name'],
                'borrow_date' => $b['borrow_date'],
                'status' => $b['status'] == 1 ? 'borrowing' : ($b['status'] == 2 ? 'returned' : 'overdue')
            ];
        }, $recentBorrows)
    ]);
}

/**
 * 设备使用统计
 */
function getDeviceUsage(): void
{
    $pdo = getDB();

    $period = $_GET['period'] ?? 'month';
    
    // 计算日期范围
    switch ($period) {
        case 'week':
            $startDate = date('Y-m-d', strtotime('-7 days'));
            break;
        case 'year':
            $startDate = date('Y-m-d', strtotime('-1 year'));
            break;
        default:
            $startDate = date('Y-m-d', strtotime('-30 days'));
    }
    $endDate = date('Y-m-d');

    // 借用次数最多的设备
    $stmt = $pdo->prepare('
        SELECT d.device_id, d.device_name, COUNT(b.record_id) as borrow_count
        FROM t_device d
        LEFT JOIN t_borrow_record b ON d.device_id = b.device_id 
            AND b.borrow_date >= ?
        GROUP BY d.device_id
        ORDER BY borrow_count DESC
        LIMIT 10
    ');
    $stmt->execute([$startDate]);
    $topDevices = $stmt->fetchAll();

    // 按类别统计
    $stmt = $pdo->prepare('
        SELECT d.category, COUNT(b.record_id) as borrow_count
        FROM t_device d
        LEFT JOIN t_borrow_record b ON d.device_id = b.device_id 
            AND b.borrow_date >= ?
        WHERE d.category IS NOT NULL
        GROUP BY d.category
        ORDER BY borrow_count DESC
    ');
    $stmt->execute([$startDate]);
    $byCategory = $stmt->fetchAll();

    // 日借用趋势
    $stmt = $pdo->prepare('
        SELECT borrow_date, COUNT(*) as count
        FROM t_borrow_record
        WHERE borrow_date >= ?
        GROUP BY borrow_date
        ORDER BY borrow_date
    ');
    $stmt->execute([$startDate]);
    $dailyStats = $stmt->fetchAll();

    respOK([
        'period' => $period,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'top_devices' => array_map(function($d) {
            return [
                'device_id' => $d['device_id'],
                'name' => $d['device_name'],
                'borrow_count' => (int)$d['borrow_count']
            ];
        }, $topDevices),
        'by_category' => array_map(function($c) {
            return [
                'category' => $c['category'],
                'borrow_count' => (int)$c['borrow_count']
            ];
        }, $byCategory),
        'daily_stats' => array_map(function($d) {
            return [
                'date' => $d['borrow_date'],
                'borrows' => (int)$d['count']
            ];
        }, $dailyStats)
    ]);
}

/**
 * 收入统计
 */
function getRevenue(): void
{
    $pdo = getDB();

    $period = $_GET['period'] ?? 'month';
    
    // 计算日期范围
    switch ($period) {
        case 'week':
            $startDate = date('Y-m-d', strtotime('-7 days'));
            break;
        case 'year':
            $startDate = date('Y-m-d', strtotime('-1 year'));
            break;
        default:
            $startDate = date('Y-m-d', strtotime('-30 days'));
    }
    $endDate = date('Y-m-d');

    // 总收入
    $stmt = $pdo->prepare('
        SELECT COALESCE(SUM(amount), 0) 
        FROM t_payment 
        WHERE status = 1 AND DATE(pay_time) >= ?
    ');
    $stmt->execute([$startDate]);
    $totalRevenue = (float)$stmt->fetchColumn();

    // 待支付金额
    $stmt = $pdo->query('SELECT COALESCE(SUM(amount), 0) FROM t_payment WHERE status = 0');
    $pendingAmount = (float)$stmt->fetchColumn();

    // 日收入趋势
    $stmt = $pdo->prepare('
        SELECT DATE(pay_time) as pay_date, SUM(amount) as amount
        FROM t_payment
        WHERE status = 1 AND DATE(pay_time) >= ?
        GROUP BY DATE(pay_time)
        ORDER BY pay_date
    ');
    $stmt->execute([$startDate]);
    $dailyRevenue = $stmt->fetchAll();

    // 按用户类型统计
    $stmt = $pdo->prepare('
        SELECT u.user_type, SUM(p.amount) as amount
        FROM t_payment p
        JOIN t_user u ON p.user_id = u.user_id
        WHERE p.status = 1 AND DATE(p.pay_time) >= ?
        GROUP BY u.user_type
    ');
    $stmt->execute([$startDate]);
    $byUserType = $stmt->fetchAll();

    respOK([
        'period' => $period,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'total_revenue' => $totalRevenue,
        'pending_amount' => $pendingAmount,
        'by_user_type' => array_map(function($t) {
            return [
                'user_type' => $t['user_type'],
                'amount' => (float)$t['amount']
            ];
        }, $byUserType),
        'daily_revenue' => array_map(function($d) {
            return [
                'date' => $d['pay_date'],
                'amount' => (float)$d['amount']
            ];
        }, $dailyRevenue)
    ]);
}
