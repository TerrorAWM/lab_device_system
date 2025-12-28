<?php
/**
 * 审批流程配置管理 API
 * 
 * GET /admin/api/workflow.php - 获取所有审批流程配置
 * POST /admin/api/workflow.php - 更新审批流程配置
 * POST /admin/api/workflow.php?action=toggle - 切换步骤启用状态
 */

declare(strict_types=1);

require_once __DIR__ . '/_init.php';

$admin = requireAdminAuth();

// 只有实验室负责人可以修改审批流程
if ($admin['role'] !== 'supervisor' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    respForbidden('仅限实验室负责人可修改审批流程配置');
}

$action = $_GET['action'] ?? '';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        getWorkflowConfig();
        break;
    case 'POST':
        switch ($action) {
            case 'toggle':
                toggleWorkflowStep();
                break;
            default:
                updateWorkflowConfig();
                break;
        }
        break;
    default:
        respError('请求方法不允许', 1, 405);
}

/**
 * 获取所有审批流程配置
 */
function getWorkflowConfig(): void
{
    $pdo = getDB();

    $stmt = $pdo->query('SELECT * FROM t_approval_workflow ORDER BY user_type, step_order');
    $workflows = $stmt->fetchAll();

    // 按用户类型分组
    $grouped = [
        'student' => [],
        'teacher' => [],
        'external' => []
    ];

    foreach ($workflows as $w) {
        $grouped[$w['user_type']][] = [
            'workflow_id' => $w['workflow_id'],
            'step_order' => $w['step_order'],
            'role_type' => $w['role_type'],
            'is_payment_required' => (bool)$w['is_payment_required'],
            'is_enabled' => (bool)$w['is_enabled'],
            'description' => $w['description']
        ];
    }

    respOK([
        'workflows' => $grouped,
        'role_types' => [
            'advisor' => '导师',
            'device' => '设备管理员',
            'supervisor' => '实验室负责人',
            'finance' => '财务'
        ],
        'user_types' => [
            'student' => '学生',
            'teacher' => '教师',
            'external' => '校外人员'
        ]
    ]);
}

/**
 * 更新审批流程配置
 */
function updateWorkflowConfig(): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $workflowId = (int)($input['workflow_id'] ?? 0);

    if (!$workflowId) {
        respError('workflow_id 不能为空');
    }

    $pdo = getDB();

    // 检查是否存在
    $stmt = $pdo->prepare('SELECT * FROM t_approval_workflow WHERE workflow_id = ?');
    $stmt->execute([$workflowId]);
    $workflow = $stmt->fetch();

    if (!$workflow) {
        respNotFound('审批步骤不存在');
    }

    // 更新字段
    $updates = [];
    $params = [];

    if (isset($input['is_payment_required'])) {
        $updates[] = 'is_payment_required = ?';
        $params[] = $input['is_payment_required'] ? 1 : 0;
    }

    if (isset($input['is_enabled'])) {
        $updates[] = 'is_enabled = ?';
        $params[] = $input['is_enabled'] ? 1 : 0;
    }

    if (isset($input['description'])) {
        $updates[] = 'description = ?';
        $params[] = trim($input['description']);
    }

    if (empty($updates)) {
        respError('没有可更新的字段');
    }

    $params[] = $workflowId;
    $sql = 'UPDATE t_approval_workflow SET ' . implode(', ', $updates) . ' WHERE workflow_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    respOK(null, '审批流程配置已更新');
}

/**
 * 切换步骤启用状态
 */
function toggleWorkflowStep(): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    $workflowId = (int)($input['workflow_id'] ?? 0);

    if (!$workflowId) {
        respError('workflow_id 不能为空');
    }

    $pdo = getDB();

    // 检查是否存在
    $stmt = $pdo->prepare('SELECT * FROM t_approval_workflow WHERE workflow_id = ?');
    $stmt->execute([$workflowId]);
    $workflow = $stmt->fetch();

    if (!$workflow) {
        respNotFound('审批步骤不存在');
    }

    // 切换状态
    $newStatus = $workflow['is_enabled'] ? 0 : 1;
    $stmt = $pdo->prepare('UPDATE t_approval_workflow SET is_enabled = ? WHERE workflow_id = ?');
    $stmt->execute([$newStatus, $workflowId]);

    respOK([
        'workflow_id' => $workflowId,
        'is_enabled' => (bool)$newStatus
    ], $newStatus ? '已启用该审批步骤' : '已禁用该审批步骤');
}
