<?php
/**
 * 数据库配置文件
 * 
 * ⚠️ 请勿将此文件提交到版本控制
 * 
 * 使用说明：
 * 1. 复制此文件为 _config.php
 * 2. 修改数据库连接信息
 */

// 数据库连接配置
define('DB_DSN', 'mysql:host=127.0.0.1;dbname=lab_device_system;charset=utf8mb4');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');

// JWT 密钥（用于 Token 生成）
define('JWT_SECRET', 'LAB_DEVICE_SYSTEM_20252026'); //your_jwt_secret_key

// Token 过期时间（秒）
define('TOKEN_EXPIRE', 86400 * 7); // 7 天

// 调试模式
define('DEBUG_MODE', true);
