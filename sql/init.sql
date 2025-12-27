-- 数据库初始化脚本
-- 更新时间：2025-12-26
-- 对应文档：《江南大学实验室设备管理系统软件需求描述文档》

CREATE DATABASE IF NOT EXISTS lab_device_system
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE lab_device_system;

-- ==========================================
-- 1. 用户与权限模块
-- ==========================================

-- 基础用户表（所有角色公用）
CREATE TABLE IF NOT EXISTS t_user (
    user_id INT NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(100) NOT NULL,
    real_name VARCHAR(50) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    user_type ENUM('teacher', 'student', 'external', 'admin') NOT NULL DEFAULT 'student',
    phone VARCHAR(20) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status TINYINT DEFAULT 1,
    PRIMARY KEY (user_id),
    UNIQUE KEY uk_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 教师扩展信息表
CREATE TABLE IF NOT EXISTS t_user_teacher (
    user_id INT NOT NULL,
    title VARCHAR(50),
    college VARCHAR(50),
    research_area VARCHAR(100),
    PRIMARY KEY (user_id),
    CONSTRAINT fk_teacher_user FOREIGN KEY (user_id) REFERENCES t_user(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 学生扩展信息表
CREATE TABLE IF NOT EXISTS t_user_student (
    user_id INT NOT NULL,
    student_no VARCHAR(20) NOT NULL,
    major VARCHAR(50),
    college VARCHAR(50),
    advisor_id INT DEFAULT NULL,
    PRIMARY KEY (user_id),
    UNIQUE KEY uk_student_no (student_no),
    CONSTRAINT fk_student_user FOREIGN KEY (user_id) REFERENCES t_user(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_student_advisor FOREIGN KEY (advisor_id) REFERENCES t_user(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 校外人员扩展信息表
CREATE TABLE IF NOT EXISTS t_user_external (
    user_id INT NOT NULL,
    organization VARCHAR(100),
    identity_card VARCHAR(20),
    PRIMARY KEY (user_id),
    CONSTRAINT fk_external_user FOREIGN KEY (user_id) REFERENCES t_user(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户登录Token表
CREATE TABLE IF NOT EXISTS t_user_token (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expire_time DATETIME NOT NULL,
    KEY idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 2. 设备管理模块
-- ==========================================

CREATE TABLE IF NOT EXISTS t_device (
    device_id INT NOT NULL AUTO_INCREMENT,
    device_name VARCHAR(100) NOT NULL,
    model VARCHAR(100) NOT NULL,
    manufacturer VARCHAR(100),
    purchase_date DATE,
    price DECIMAL(10,2),
    rent_price DECIMAL(10,2) DEFAULT 0.00,
    category VARCHAR(50),
    status TINYINT NOT NULL DEFAULT 1,
    location VARCHAR(50),
    image_url VARCHAR(255),
    purpose TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 设备检修记录
CREATE TABLE IF NOT EXISTS t_device_maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    reason VARCHAR(255),
    operator_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_maint_device FOREIGN KEY (device_id) REFERENCES t_device(device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 3. 预约与借用模块
-- ==========================================

CREATE TABLE IF NOT EXISTS t_reservation (
    reservation_id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    device_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    purpose TEXT,
    status TINYINT DEFAULT 0,
    reject_reason VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    audit_process JSON,
    PRIMARY KEY (reservation_id),
    KEY idx_user (user_id),
    KEY idx_device (device_id),
    CONSTRAINT fk_res_user FOREIGN KEY (user_id) REFERENCES t_user(user_id),
    CONSTRAINT fk_res_device FOREIGN KEY (device_id) REFERENCES t_device(device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 借用记录表（设备实际出入库）
CREATE TABLE IF NOT EXISTS t_borrow_record (
    record_id INT NOT NULL AUTO_INCREMENT,
    reservation_id INT NOT NULL,
    user_id INT NOT NULL,
    device_id INT NOT NULL,
    borrow_time DATETIME NOT NULL,
    expected_return DATETIME NOT NULL,
    actual_return DATETIME DEFAULT NULL,
    status TINYINT DEFAULT 1,
    operator_out_id INT,
    operator_in_id INT,
    PRIMARY KEY (record_id),
    CONSTRAINT fk_borrow_res FOREIGN KEY (reservation_id) REFERENCES t_reservation(reservation_id),
    CONSTRAINT fk_borrow_user FOREIGN KEY (user_id) REFERENCES t_user(user_id),
    CONSTRAINT fk_borrow_device FOREIGN KEY (device_id) REFERENCES t_device(device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 4. 财务模块
-- ==========================================

CREATE TABLE IF NOT EXISTS t_payment (
    payment_id INT NOT NULL AUTO_INCREMENT,
    reservation_id INT NOT NULL UNIQUE,
    order_no VARCHAR(64) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status TINYINT DEFAULT 0,
    pay_time DATETIME DEFAULT NULL,
    payer_name VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (payment_id),
    CONSTRAINT fk_pay_res FOREIGN KEY (reservation_id) REFERENCES t_reservation(reservation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ==========================================
-- 5. 初始化数据 (Seeds)
-- ==========================================

-- 1. 管理员 (admin / 123456)
INSERT INTO t_user (username, password, real_name, role, user_type) VALUES 
('admin', '123456', '超级管理员', 'admin', 'admin');

-- 2. 教师 (teacher1 / 123456)
INSERT INTO t_user (username, password, real_name, role, user_type) VALUES 
('teacher1', '123456', '王教授', 'user', 'teacher');
SET @teacher_id = LAST_INSERT_ID();
INSERT INTO t_user_teacher (user_id, title, college, research_area) VALUES 
(@teacher_id, '教授', '计算机学院', '物联网技术');

-- 3. 学生 (student1 / 123456)
INSERT INTO t_user (username, password, real_name, role, user_type) VALUES 
('student1', '123456', '张三', 'user', 'student');
SET @student_id = LAST_INSERT_ID();
INSERT INTO t_user_student (user_id, student_no, major, college, advisor_id) VALUES 
(@student_id, 'S2024001', '软件工程', '计算机学院', @teacher_id);

-- 4. 校外人员 (ext1 / 123456)
INSERT INTO t_user (username, password, real_name, role, user_type) VALUES 
('ext1', '123456', '李经理', 'user', 'external');
SET @ext_id = LAST_INSERT_ID();
INSERT INTO t_user_external (user_id, organization) VALUES 
(@ext_id, 'XX科技公司');

-- 5. 设备数据
INSERT INTO t_device (device_name, model, manufacturer, category, price, rent_price, status, location) VALUES 
('数字示波器', 'DS1054Z', 'RIGOL', '示波器', 2500.00, 50.00, 1, '实验室A-101'),
('函数信号发生器', 'DG1022Z', 'RIGOL', '信号源', 1800.00, 40.00, 1, '实验室A-102'),
('直流稳压电源', 'DP832', 'RIGOL', '电源', 3200.00, 60.00, 2, '实验室B-201'), -- satus 2 借出
('频谱分析仪', 'DSA815', 'RIGOL', '分析仪', 8500.00, 150.00, 1, '实验室A-101'),
('台式万用表', 'SDM3055', 'SIGLENT', '万用表', 2200.00, 45.00, 3, '维修区'); -- status 3 维修
