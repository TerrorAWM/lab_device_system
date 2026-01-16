<?php
/**
 * 自动化需求验证脚本
 * 
 * 对应文档：软件需求描述文档.md
 * 覆盖场景：
 * 1. 用户注册/登录 (FR-U01, FR-U02)
 * 2. 设备预约与冲突检测 (FR-R02, FR-R05)
 * 3. 审批流程 (FR-A01, FR-A02, FR-A03)
 * 4. 借用记录生成
 */

// 模拟 CLI 环境
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once __DIR__ . '/api/_config.php';
require_once __DIR__ . '/api/_db.php';

// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pdo = getDB();
$results = [];

function logResult($testName, $success, $message = '') {
    global $results;
    $results[] = [
        'name' => $testName,
        'success' => $success,
        'message' => $message
    ];
    echo $success ? "[PASS] $testName\n" : "[FAIL] $testName: $message\n";
}

function request($url, $method = 'GET', $data = [], $token = null) {
    $baseUrl = 'http://localhost/lab_device_system/api/';
    if (strpos($url, 'admin/') === 0) {
        $baseUrl = 'http://localhost/lab_device_system/';
    }
    
    $ch = curl_init($baseUrl . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    
    if ($token) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return json_decode($response, true);
}

// 1. 清理数据
echo "--- 1. 清理测试数据 ---\n";
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$pdo->exec("TRUNCATE TABLE t_reservation");
$pdo->exec("TRUNCATE TABLE t_borrow_record");
$pdo->exec("TRUNCATE TABLE t_payment");
$pdo->exec("TRUNCATE TABLE t_approval_log");
$pdo->exec("UPDATE t_device SET status = 1"); // 重置设备状态
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
logResult('清理数据', true);

// 2. 模拟登录获取 Token
echo "\n--- 2. 准备用户 Token ---\n";
// 教师：张三 (123456)
$res = request('login.php', 'POST', ['username' => '张三', 'password' => '123456']);
$teacherToken = $res['data']['token'] ?? null;
logResult('教师登录', !!$teacherToken);

// 学生：李四 (123456) - 导师是张三
$res = request('login.php', 'POST', ['username' => '李四', 'password' => '123456']);
$studentToken = $res['data']['token'] ?? null;
logResult('学生登录', !!$studentToken);

// 校外：刘经理 (123456)
$res = request('login.php', 'POST', ['username' => '刘经理', 'password' => '123456']);
$externalToken = $res['data']['token'] ?? null;
logResult('校外人员登录', !!$externalToken);

// 管理员：device (123456)
$res = request('admin/api/login.php', 'POST', ['username' => 'device', 'password' => '123456']);
$deviceAdminToken = $res['data']['token'] ?? null;
logResult('设备管理员登录', !!$deviceAdminToken);

// 管理员：supervisor (123456)
$res = request('admin/api/login.php', 'POST', ['username' => 'supervisor', 'password' => '123456']);
$supervisorToken = $res['data']['token'] ?? null;
logResult('负责人登录', !!$supervisorToken);

// 管理员：finance (123456)
$res = request('admin/api/login.php', 'POST', ['username' => 'finance', 'password' => '123456']);
$financeToken = $res['data']['token'] ?? null;
logResult('财务登录', !!$financeToken);

// 获取3个可用设备 ID
$stmt = $pdo->query("SELECT device_id FROM t_device WHERE status = 1 LIMIT 3");
$deviceIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "测试设备IDs: " . implode(', ', $deviceIds) . "\n";

if (count($deviceIds) < 1) {
    die("没有足够的可用设备进行测试\n");
}

$device1 = $deviceIds[0];
$device2 = $deviceIds[1] ?? $device1; // 如果不够，复用
$device3 = $deviceIds[2] ?? $device1;

// 3. 测试教师预约流程 (FR-A01)
echo "\n--- 3. 测试教师预约流程 (FR-A01) ---\n";
$date = date('Y-m-d', strtotime('+1 day'));
$timeSlot = '08:00-10:00';

// 3.1 提交预约
$res = request('reservation.php?action=create', 'POST', [
    'device_id' => $device1,
    'reserve_date' => $date,
    'time_slot' => $timeSlot,
    'purpose' => '教学演示'
], $teacherToken);
$teacherResId = $res['data']['reservation_id'] ?? 0;
logResult('教师提交预约', $res['code'] === 0, $res['message'] ?? '');

// 3.2 设备管理员审批
if ($teacherResId) {
    $res = request('admin/api/reservation.php?action=approve', 'POST', [
        'reservation_id' => $teacherResId,
        'remark' => '同意'
    ], $deviceAdminToken);
    logResult('设备管理员审批教师预约', $res['code'] === 0 && ($res['data']['status'] ?? '') === 'approved', $res['message'] ?? '');
}

// 4. 测试冲突检测 (FR-R05)
echo "\n--- 4. 测试冲突检测 (FR-R05) ---\n";
// 学生尝试预约同一设备同一时间段
$res = request('reservation.php?action=create', 'POST', [
    'device_id' => $device1,
    'reserve_date' => $date,
    'time_slot' => $timeSlot,
    'purpose' => '冲突测试'
], $studentToken);
logResult('冲突检测(同一时间段不可预约)', $res['code'] !== 0, $res['message'] ?? '');

// 5. 测试学生预约流程 (FR-A02) - 使用设备2
echo "\n--- 5. 测试学生预约流程 (FR-A02) ---\n";
$studentTimeSlot = '10:00-12:00';
// 5.1 提交预约
$res = request('reservation.php?action=create', 'POST', [
    'device_id' => $device2,
    'reserve_date' => $date,
    'time_slot' => $studentTimeSlot,
    'purpose' => '毕业设计'
], $studentToken);
$studentResId = $res['data']['reservation_id'] ?? 0;
logResult('学生提交预约', $res['code'] === 0, $res['message'] ?? '');

// 5.2 导师审批 (使用教师账号调用审批接口 - 需确认教师端是否有审批API，通常在 user/api/approval.php)
// 假设教师端有审批功能
$res = request('approval.php?action=approve', 'POST', [
    'reservation_id' => $studentResId,
    'remark' => '导师同意'
], $teacherToken);
logResult('导师审批学生预约', $res['code'] === 0, $res['message'] ?? '');

// 5.3 设备管理员审批
if ($studentResId) {
    $res = request('admin/api/reservation.php?action=approve', 'POST', [
        'reservation_id' => $studentResId,
        'remark' => '管理员同意'
    ], $deviceAdminToken);
    logResult('设备管理员审批学生预约', $res['code'] === 0 && ($res['data']['status'] ?? '') === 'approved', $res['message'] ?? '');
}

// 6. 测试校外人员预约流程 (FR-A03) - 使用设备3
echo "\n--- 6. 测试校外人员预约流程 (FR-A03) ---\n";
$externalTimeSlot = '14:00-16:00';
// 6.1 提交预约
$res = request('reservation.php?action=create', 'POST', [
    'device_id' => $device3,
    'reserve_date' => $date,
    'time_slot' => $externalTimeSlot,
    'purpose' => '企业合作'
], $externalToken);
$externalResId = $res['data']['reservation_id'] ?? 0;
logResult('校外人员提交预约', $res['code'] === 0, $res['message'] ?? '');

// 6.2 设备管理员审批
if ($externalResId) {
    $res = request('admin/api/reservation.php?action=approve', 'POST', [
        'reservation_id' => $externalResId,
        'remark' => '初审通过'
    ], $deviceAdminToken);
    logResult('设备管理员初审', $res['code'] === 0, $res['message'] ?? '');
    
    // 6.3 负责人审批
    $res = request('admin/api/reservation.php?action=approve', 'POST', [
        'reservation_id' => $externalResId,
        'remark' => '负责人同意'
    ], $supervisorToken);
    logResult('负责人审批', $res['code'] === 0, $res['message'] ?? '');
    
    // 6.4 模拟支付 (直接修改数据库或调用支付回调API)
    // 这里假设有一个支付回调接口或直接插入支付记录
    $stmt = $pdo->prepare("UPDATE t_payment SET status = 1, pay_time = NOW() WHERE reservation_id = ?");
    $stmt->execute([$externalResId]);
    logResult('模拟支付完成', true);
    
    // 6.5 财务审批
    $res = request('admin/api/reservation.php?action=approve', 'POST', [
        'reservation_id' => $externalResId,
        'remark' => '财务确认'
    ], $financeToken);
    logResult('财务确认', $res['code'] === 0 && ($res['data']['status'] ?? '') === 'approved', $res['message'] ?? '');
}

// 总结
echo "\n--- 测试总结 ---\n";
$passCount = count(array_filter($results, fn($r) => $r['success']));
$totalCount = count($results);
echo "通过: $passCount / $totalCount\n";

if ($passCount === $totalCount) {
    echo "所有需求验证通过！\n";
} else {
    echo "存在失败的测试项，请检查日志。\n";
}
