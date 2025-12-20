<?php
/**
 * 统一 JSON 响应工具
 */

declare(strict_types=1);

/**
 * 发送成功响应
 */
function respOK(mixed $data = null, string $message = 'success'): void
{
    echo json_encode([
        'code' => 0,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 发送错误响应
 */
function respError(string $message, int $code = 1, int $httpCode = 400): void
{
    http_response_code($httpCode);
    echo json_encode([
        'code' => $code,
        'message' => $message,
        'data' => null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 发送 401 未授权响应
 */
function respUnauthorized(string $message = '未授权访问'): void
{
    respError($message, 401, 401);
}

/**
 * 发送 403 禁止访问响应
 */
function respForbidden(string $message = '禁止访问'): void
{
    respError($message, 403, 403);
}

/**
 * 发送 404 未找到响应
 */
function respNotFound(string $message = '资源不存在'): void
{
    respError($message, 404, 404);
}
