<?php
/**
 * 管理员鉴权工具
 */

declare(strict_types=1);

/**
 * 从请求头获取 Token
 */
function getBearerToken(): ?string
{
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
        return $matches[1];
    }
    
    return null;
}

/**
 * 验证管理员 Token 并返回管理员信息
 * 
 * @return array 管理员信息
 * @throws 401 如果 Token 无效
 */
function requireAdminAuth(): array
{
    $token = getBearerToken();
    
    if (!$token) {
        respUnauthorized('缺少认证令牌');
    }
    
    $pdo = getDB();
    $stmt = $pdo->prepare('
        SELECT a.* FROM t_admin a
        JOIN t_admin_token at ON a.admin_id = at.admin_id
        WHERE at.token = ? AND at.expire_time > NOW()
    ');
    $stmt->execute([$token]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        respUnauthorized('认证令牌无效或已过期');
    }
    
    // 移除敏感信息
    unset($admin['password']);
    
    return $admin;
}

/**
 * 检查管理员是否有特定角色
 */
function requireRole(array $admin, string $role): void
{
    if ($admin['role'] !== $role && $admin['role'] !== 'super_admin') {
        respForbidden('权限不足');
    }
}

/**
 * 生成随机 Token
 */
function generateToken(int $length = 64): string
{
    return bin2hex(random_bytes($length / 2));
}
