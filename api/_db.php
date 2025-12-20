<?php
/**
 * 数据库连接工具
 */

declare(strict_types=1);

/**
 * 获取 PDO 数据库连接实例（单例模式）
 */
function getDB(): PDO
{
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => '数据库连接失败']);
            exit;
        }
    }
    
    return $pdo;
}
