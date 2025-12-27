<?php
/**
 * 收费管理 API
 * 
 * GET /admin/api/payment.php - 支付订单列表
 * GET /admin/api/payment.php?id=X - 订单详情
 * POST /admin/api/payment.php?action=create - 创建费用单
 * POST /admin/api/payment.php?action=mark_paid - 标记已支付
 */

declare(strict_types=1);

require_once __DIR__ . '/_init.php';
require_once __DIR__ . '/_util.php';

$admin = requireAdminAuth();
$action = $_GET['action'] ?? '';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['id'])) {
            getPaymentDetail((int)$_GET['id']);
        } else {
            getPaymentList();
        }
        break;
    case 'POST':
        switch ($action) {
            case 'create':
                createPayment($admin);
                break;
            case 'mark_paid':
                markPaid($admin);
                break;
            default:
                respError('未知操作');
        }
        break;
    default:
        respError('请求方法不允许', 1, 405);
}

/**
 * 获取支付订单列表
 */
function getPaymentList(): void
{
    $pdo = getDB();
    $pagination = getPagination();

    $status = $_GET['status'] ?? '';
    $userId = $_GET['user_id'] ?? '';

    $where = ['1=1'];
    $params = [];

    if ($status !== '') {
        $where[] = 'p.status = ?';
        $params[] = (int)$status;
    }

    if ($userId) {
        $where[] = 'p.user_id = ?';
        $params[] = (int)$userId;
    }

    $whereClause = implode(' AND ', $where);

    // 查询总数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM t_payment p WHERE $whereClause");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // 查询数据
    $sql = "
        SELECT p.*, 
               u.real_name as user_name, u.user_type,
               r.device_id, d.device_name
        FROM t_payment p
        LEFT JOIN t_user u ON p.user_id = u.user_id
        LEFT JOIN t_reservation r ON p.reservation_id = r.reservation_id
        LEFT JOIN t_device d ON r.device_id = d.device_id
        WHERE $whereClause
        ORDER BY p.created_at DESC
        LIMIT {$pagination['page_size']} OFFSET {$pagination['offset']}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();

    $statusMap = [0 => 'pending', 1 => 'paid', 2 => 'refunded'];

    $items = array_map(function($p) use ($statusMap) {
        return [
            'id' => $p['payment_id'],
            'order_no' => $p['order_no'],
            'user_id' => $p['user_id'],
            'user_name' => $p['user_name'],
            'user_type' => $p['user_type'],
            'reservation_id' => $p['reservation_id'],
            'device_name' => $p['device_name'],
            'amount' => (float)$p['amount'],
            'status' => $statusMap[$p['status']] ?? 'unknown',
            'status_code' => $p['status'],
            'description' => $p['description'],
            'pay_time' => $p['pay_time'],
            'created_at' => $p['created_at']
        ];
    }, $payments);

    respOK(buildPaginatedResponse($items, $total, $pagination));
}

/**
 * 获取订单详情
 */
function getPaymentDetail(int $paymentId): void
{
    $pdo = getDB();

    $stmt = $pdo->prepare('
        SELECT p.*, 
               u.real_name as user_name, u.user_type, u.phone as user_phone,
               r.device_id, r.reserve_date, r.time_slot, d.device_name
        FROM t_payment p
        LEFT JOIN t_user u ON p.user_id = u.user_id
        LEFT JOIN t_reservation r ON p.reservation_id = r.reservation_id
        LEFT JOIN t_device d ON r.device_id = d.device_id
        WHERE p.payment_id = ?
    ');
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();

    if (!$payment) {
        respNotFound('订单不存在');
    }

    $statusMap = [0 => 'pending', 1 => 'paid', 2 => 'refunded'];

    respOK([
        'id' => $payment['payment_id'],
        'order_no' => $payment['order_no'],
        'user' => [
            'id' => $payment['user_id'],
            'name' => $payment['user_name'],
            'type' => $payment['user_type'],
            'phone' => $payment['user_phone']
        ],
        'reservation_id' => $payment['reservation_id'],
        'device_name' => $payment['device_name'],
        'reserve_date' => $payment['reserve_date'],
        'time_slot' => $payment['time_slot'],
        'amount' => (float)$payment['amount'],
        'status' => $statusMap[$payment['status']] ?? 'unknown',
        'status_code' => $payment['status'],
        'description' => $payment['description'],
        'pay_time' => $payment['pay_time'],
        'created_at' => $payment['created_at']
    ]);
}

/**
 * 创建费用单
 */
function createPayment(array $admin): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $userId = (int)($input['user_id'] ?? 0);
    $reservationId = (int)($input['reservation_id'] ?? 0);
    $amount = (float)($input['amount'] ?? 0);
    $description = trim($input['description'] ?? '');

    if (!$userId) {
        respError('用户ID不能为空');
    }

    if ($amount <= 0) {
        respError('金额必须大于0');
    }

    $pdo = getDB();

    // 检查用户是否存在
    $stmt = $pdo->prepare('SELECT user_id FROM t_user WHERE user_id = ?');
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) {
        respNotFound('用户不存在');
    }

    // 生成订单号
    $orderNo = 'PAY-' . date('Ymd') . '-' . str_pad((string)rand(1, 9999), 4, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare('
        INSERT INTO t_payment (reservation_id, user_id, order_no, amount, status, description)
        VALUES (?, ?, ?, ?, 0, ?)
    ');
    $stmt->execute([
        $reservationId ?: null,
        $userId,
        $orderNo,
        $amount,
        $description ?: null
    ]);
    $paymentId = (int)$pdo->lastInsertId();

    respOK([
        'payment_id' => $paymentId,
        'order_no' => $orderNo
    ], '费用单创建成功');
}

/**
 * 标记已支付
 */
function markPaid(array $admin): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $paymentId = (int)($input['payment_id'] ?? 0);

    if (!$paymentId) {
        respError('订单ID不能为空');
    }

    $pdo = getDB();

    // 检查订单
    $stmt = $pdo->prepare('SELECT * FROM t_payment WHERE payment_id = ?');
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();

    if (!$payment) {
        respNotFound('订单不存在');
    }

    if ($payment['status'] != 0) {
        respError('订单已处理');
    }

    $payTime = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare('UPDATE t_payment SET status = 1, pay_time = ? WHERE payment_id = ?');
    $stmt->execute([$payTime, $paymentId]);

    respOK([
        'payment_id' => $paymentId,
        'order_no' => $payment['order_no'],
        'status' => 'paid',
        'pay_time' => $payTime
    ], '已标记为已支付');
}
