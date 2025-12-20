<?php
/**
 * 用户鉴权工具
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
 * 验证用户 Token 并返回用户信息
 * 
 * @return array 用户信息
 * @throws 401 如果 Token 无效
 */
function requireAuth(): array
{
    $token = getBearerToken();
    
    if (!$token) {
        respUnauthorized('缺少认证令牌');
    }
    
    $pdo = getDB();
    $stmt = $pdo->prepare('
        SELECT u.* FROM users u
        JOIN user_tokens ut ON u.id = ut.user_id
        WHERE ut.token = ? AND ut.expires_at > NOW()
    ');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        respUnauthorized('认证令牌无效或已过期');
    }
    
    // 移除敏感信息
    unset($user['password']);
    
    return $user;
}

/**
 * 可选鉴权，返回用户信息或 null
 */
function optionalAuth(): ?array
{
    $token = getBearerToken();
    
    if (!$token) {
        return null;
    }
    
    $pdo = getDB();
    $stmt = $pdo->prepare('
        SELECT u.* FROM users u
        JOIN user_tokens ut ON u.id = ut.user_id
        WHERE ut.token = ? AND ut.expires_at > NOW()
    ');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        unset($user['password']);
    }
    
    return $user ?: null;
}

/**
 * 生成随机 Token
 */
function generateToken(int $length = 64): string
{
    return bin2hex(random_bytes($length / 2));
}
