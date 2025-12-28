<?php
/**
 * 缴费管理 API
 * 
 * GET /api/payment.php - 获取支付记录
 * GET /api/payment.php?action=pending - 获取待支付订单
 * POST /api/payment.php?action=pay - 发起支付
 * POST /api/payment.php?action=confirm - 确认支付（模拟回调）
 */

declare(strict_types=1);

require_once __DIR__ . '/_init.php';

$action = $_GET['action'] ?? '';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $user = requireAuth();
        if ($action === 'pending') {
            getPendingPayments($user);
        } else {
            getPaymentList($user);
        }
        break;
    case 'POST':
        if ($action === 'confirm') {
            confirmPayment(); // 公开接口，模拟支付回调
        } else {
            $user = requireAuth();
            initiatePayment($user);
        }
        break;
    default:
        respError('请求方法不允许', 1, 405);
}

/**
 * 获取支付记录
 */
function getPaymentList(array $user): void
{
    $pdo = getDB();

    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int)($_GET['page_size'] ?? 20)));
    $offset = ($page - 1) * $pageSize;

    $status = $_GET['status'] ?? '';

    $where = ['p.user_id = ?'];
    $params = [$user['user_id']];

    if ($status !== '') {
        $where[] = 'p.status = ?';
        $params[] = (int)$status;
    }

    $whereClause = implode(' AND ', $where);

    // 查询总数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM t_payment p WHERE $whereClause");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // 查询数据
    $stmt = $pdo->prepare("
        SELECT p.*, r.device_id, d.device_name
        FROM t_payment p
        LEFT JOIN t_reservation r ON p.reservation_id = r.reservation_id
        LEFT JOIN t_device d ON r.device_id = d.device_id
        WHERE $whereClause
        ORDER BY p.created_at DESC
        LIMIT $pageSize OFFSET $offset
    ");
    $stmt->execute($params);
    $payments = $stmt->fetchAll();

    $statusMap = [0 => 'pending', 1 => 'paid', 2 => 'cancelled'];

    $items = array_map(function($p) use ($statusMap) {
        return [
            'payment_id' => $p['payment_id'],
            'order_no' => $p['order_no'],
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
 * 获取待支付订单
 */
function getPendingPayments(array $user): void
{
    $pdo = getDB();

    $stmt = $pdo->prepare('
        SELECT p.*, r.device_id, d.device_name
        FROM t_payment p
        LEFT JOIN t_reservation r ON p.reservation_id = r.reservation_id
        LEFT JOIN t_device d ON r.device_id = d.device_id
        WHERE p.user_id = ? AND p.status = 0
        ORDER BY p.created_at DESC
    ');
    $stmt->execute([$user['user_id']]);
    $payments = $stmt->fetchAll();

    $items = array_map(function($p) {
        return [
            'payment_id' => $p['payment_id'],
            'order_no' => $p['order_no'],
            'reservation_id' => $p['reservation_id'],
            'device_name' => $p['device_name'],
            'amount' => (float)$p['amount'],
            'status' => 'pending',
            'description' => $p['description'],
            'created_at' => $p['created_at']
        ];
    }, $payments);

    respOK($items);
}

/**
 * 发起支付（模拟）
 */
function initiatePayment(array $user): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $paymentId = (int)($input['payment_id'] ?? 0);
    $payMethod = trim($input['pay_method'] ?? 'wechat');

    if (!$paymentId) {
        respError('订单ID不能为空');
    }

    $pdo = getDB();

    // 检查订单是否存在且属于当前用户
    $stmt = $pdo->prepare('SELECT * FROM t_payment WHERE payment_id = ? AND user_id = ?');
    $stmt->execute([$paymentId, $user['user_id']]);
    $payment = $stmt->fetch();

    if (!$payment) {
        respNotFound('订单不存在');
    }

    if ($payment['status'] != 0) {
        respError('该订单不需要支付');
    }

    // 模拟生成支付链接
    $payUrl = "http://localhost/pay/mock/{$payment['order_no']}";

    respOK([
        'order_no' => $payment['order_no'],
        'amount' => (float)$payment['amount'],
        'pay_method' => $payMethod,
        'pay_url' => $payUrl,
        'message' => '请使用支付链接完成支付，或调用 confirm 接口模拟支付完成'
    ], '支付链接已生成');
}

/**
 * 确认支付（模拟回调）
 */
function confirmPayment(): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $orderNo = trim($input['order_no'] ?? '');

    if (empty($orderNo)) {
        respError('订单号不能为空');
    }

    $pdo = getDB();

    // 查找订单
    $stmt = $pdo->prepare('SELECT * FROM t_payment WHERE order_no = ?');
    $stmt->execute([$orderNo]);
    $payment = $stmt->fetch();

    if (!$payment) {
        respNotFound('订单不存在');
    }

    if ($payment['status'] != 0) {
        respError('订单已处理');
    }

    // 更新支付状态
    $payTime = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare('UPDATE t_payment SET status = 1, pay_time = ? WHERE payment_id = ?');
    $stmt->execute([$payTime, $payment['payment_id']]);

    respOK([
        'order_no' => $orderNo,
        'status' => 'paid',
        'pay_time' => $payTime
    ], '支付成功');
}
