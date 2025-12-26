-- ============================================
-- 实验室设备管理系统 - 数据库初始化脚本
-- Lab Device Management System - Database Init Script
-- ============================================

-- 创建数据库（如果不存在）
CREATE DATABASE IF NOT EXISTS lab_device_system
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE lab_device_system;

-- ============================================
-- 用户表 (t_user)
-- ============================================
CREATE TABLE IF NOT EXISTS t_user (
    user_id INT NOT NULL AUTO_INCREMENT COMMENT '用户ID，主键',
    username VARCHAR(50) NOT NULL COMMENT '用户名',
    PRIMARY KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- ============================================
-- 设备表 (t_device)
-- ============================================
CREATE TABLE IF NOT EXISTS t_device (
    device_id INT NOT NULL AUTO_INCREMENT COMMENT '设备ID，主键',
    device_name VARCHAR(100) NOT NULL COMMENT '设备名称',
    status TINYINT NOT NULL DEFAULT 0 COMMENT '设备状态 (0: 可用, 1: 借出, 2: 维修中)',
    PRIMARY KEY (device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='设备表';

-- ============================================
-- 借用记录表 (t_borrow_record)
-- ============================================
CREATE TABLE IF NOT EXISTS t_borrow_record (
    record_id INT NOT NULL AUTO_INCREMENT COMMENT '记录ID，主键',
    fk_user_id INT NOT NULL COMMENT '外键，关联用户ID',
    fk_device_id INT NOT NULL COMMENT '外键，关联设备ID',
    borrow_date DATETIME NOT NULL COMMENT '借用日期时间',
    PRIMARY KEY (record_id),
    CONSTRAINT fk_borrow_user FOREIGN KEY (fk_user_id) REFERENCES t_user(user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_borrow_device FOREIGN KEY (fk_device_id) REFERENCES t_device(device_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='借用记录表';

-- ============================================
-- 创建索引以优化查询性能
-- ============================================
CREATE INDEX idx_borrow_user ON t_borrow_record(fk_user_id);
CREATE INDEX idx_borrow_device ON t_borrow_record(fk_device_id);
CREATE INDEX idx_borrow_date ON t_borrow_record(borrow_date);

-- ============================================
-- 插入示例数据 (可选)
-- ============================================

-- 插入示例用户
INSERT INTO t_user (username) VALUES 
    ('张三'),
    ('李四'),
    ('王五');

-- 插入示例设备
INSERT INTO t_device (device_name, status) VALUES 
    ('示波器 A-001', 0),
    ('万用表 B-002', 0),
    ('信号发生器 C-003', 0),
    ('电源供应器 D-004', 1),
    ('逻辑分析仪 E-005', 2);

-- 插入示例借用记录
INSERT INTO t_borrow_record (fk_user_id, fk_device_id, borrow_date) VALUES 
    (1, 4, NOW());
