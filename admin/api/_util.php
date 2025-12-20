<?php
/**
 * 管理端工具函数
 */

declare(strict_types=1);

/**
 * 记录管理员操作日志
 */
function logAdminAction(int $adminId, string $action, ?string $details = null): void
{
    $pdo = getDB();
    $stmt = $pdo->prepare('
        INSERT INTO admin_logs (admin_id, action, details, ip_address, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ');
    $stmt->execute([
        $adminId,
        $action,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
}

/**
 * 分页参数处理
 */
function getPagination(): array
{
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = min(100, max(1, (int)($_GET['page_size'] ?? 20)));
    $offset = ($page - 1) * $pageSize;
    
    return [
        'page' => $page,
        'page_size' => $pageSize,
        'offset' => $offset,
    ];
}

/**
 * 构建分页响应
 */
function buildPaginatedResponse(array $items, int $total, array $pagination): array
{
    return [
        'items' => $items,
        'pagination' => [
            'page' => $pagination['page'],
            'page_size' => $pagination['page_size'],
            'total' => $total,
            'total_pages' => ceil($total / $pagination['page_size']),
        ],
    ];
}
