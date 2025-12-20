<?php
/**
 * 公共初始化文件
 * 
 * 1. 加载配置
 * 2. 设置错误处理
 * 3. 设置 CORS 头
 * 4. 启动会话
 */

declare(strict_types=1);

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 错误报告（生产环境应关闭）
error_reporting(E_ALL);
ini_set('display_errors', '1');

// 加载配置
require_once __DIR__ . '/_config.php';

// 加载数据库连接
require_once __DIR__ . '/_db.php';

// 加载响应工具
require_once __DIR__ . '/_resp.php';

// 加载鉴权工具
require_once __DIR__ . '/_auth.php';

// CORS 设置
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
