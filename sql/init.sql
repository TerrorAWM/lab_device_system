-- 数据库初始化脚本
-- 更新时间：2025-12-27
-- 详细说明请参阅 sql/README.md

CREATE DATABASE IF NOT EXISTS lab_device_system
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE lab_device_system;

-- ==========================================
-- 1. 用户与权限模块
-- ==========================================

CREATE TABLE IF NOT EXISTS t_user (
    user_id INT NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(100) NOT NULL,
    real_name VARCHAR(50) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    user_type ENUM('teacher', 'student', 'external', 'device') NOT NULL DEFAULT 'student',
    phone VARCHAR(20) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status TINYINT DEFAULT 1,
    need_change_pwd TINYINT DEFAULT 0,
    PRIMARY KEY (user_id),
    UNIQUE KEY uk_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS t_user_teacher (
    user_id INT NOT NULL,
    title VARCHAR(50),
    college VARCHAR(50),
    research_area VARCHAR(100),
    PRIMARY KEY (user_id),
    CONSTRAINT fk_teacher_user FOREIGN KEY (user_id) REFERENCES t_user(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS t_user_external (
    user_id INT NOT NULL,
    organization VARCHAR(100),
    identity_card VARCHAR(20),
    PRIMARY KEY (user_id),
    CONSTRAINT fk_external_user FOREIGN KEY (user_id) REFERENCES t_user(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS t_user_token (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expire_time DATETIME NOT NULL,
    KEY idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 1.1 管理员模块
-- ==========================================

CREATE TABLE IF NOT EXISTS t_admin (
    admin_id INT NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(100) NOT NULL,
    real_name VARCHAR(50) NOT NULL,
    role ENUM('supervisor', 'device', 'finance') NOT NULL DEFAULT 'device',
    phone VARCHAR(20) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status TINYINT DEFAULT 1,
    PRIMARY KEY (admin_id),
    UNIQUE KEY uk_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS t_admin_token (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expire_time DATETIME NOT NULL,
    KEY idx_admin_token (token),
    CONSTRAINT fk_admin_token FOREIGN KEY (admin_id) REFERENCES t_admin(admin_id) ON DELETE CASCADE
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
    reserve_date DATE NOT NULL,
    time_slot ENUM('08:00-10:00', '10:00-12:00', '14:00-16:00', '16:00-18:00', '19:00-21:00') NOT NULL,
    purpose TEXT,
    status TINYINT DEFAULT 0,
    current_step INT DEFAULT 1,
    reject_reason VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approvals JSON,
    PRIMARY KEY (reservation_id),
    KEY idx_user (user_id),
    KEY idx_device (device_id),
    KEY idx_date_slot (reserve_date, time_slot),
    CONSTRAINT fk_res_user FOREIGN KEY (user_id) REFERENCES t_user(user_id),
    CONSTRAINT fk_res_device FOREIGN KEY (device_id) REFERENCES t_device(device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS t_borrow_record (
    record_id INT NOT NULL AUTO_INCREMENT,
    reservation_id INT NOT NULL,
    user_id INT NOT NULL,
    device_id INT NOT NULL,
    borrow_date DATE NOT NULL,
    time_slot ENUM('08:00-10:00', '10:00-12:00', '14:00-16:00', '16:00-18:00', '19:00-21:00') NOT NULL,
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
    user_id INT NOT NULL,
    order_no VARCHAR(64) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status TINYINT DEFAULT 0,
    pay_time DATETIME DEFAULT NULL,
    description VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (payment_id),
    CONSTRAINT fk_pay_res FOREIGN KEY (reservation_id) REFERENCES t_reservation(reservation_id),
    CONSTRAINT fk_pay_user FOREIGN KEY (user_id) REFERENCES t_user(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 5. 审批流程模块
-- ==========================================

CREATE TABLE IF NOT EXISTS t_approval_workflow (
    workflow_id INT NOT NULL AUTO_INCREMENT,
    user_type ENUM('teacher', 'student', 'external') NOT NULL,
    step_order INT NOT NULL,
    role_type ENUM('advisor', 'device', 'supervisor', 'finance') NOT NULL,
    is_parallel TINYINT DEFAULT 0,
    is_payment_required TINYINT DEFAULT 0,
    is_enabled TINYINT DEFAULT 1,
    description VARCHAR(100),
    PRIMARY KEY (workflow_id),
    UNIQUE KEY uk_workflow_step (user_type, step_order, role_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS t_approval_log (
    log_id INT NOT NULL AUTO_INCREMENT,
    reservation_id INT NOT NULL,
    step_order INT NOT NULL,
    role_type ENUM('advisor', 'device', 'supervisor', 'finance') NOT NULL,
    approver_id INT NOT NULL,
    approver_type ENUM('user', 'admin') NOT NULL DEFAULT 'admin',
    approver_name VARCHAR(50),
    action ENUM('approve', 'reject') NOT NULL,
    reason VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    KEY idx_reservation (reservation_id),
    CONSTRAINT fk_approval_log_res FOREIGN KEY (reservation_id) REFERENCES t_reservation(reservation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 5. 初始化数据 (Seeds)
-- ==========================================

INSERT INTO t_admin (username, password, real_name, role) VALUES 
('supervisor', '$2y$12$0pbirdTPAJ9bZJ1DllZmTO5ERg4e9zHr.Xj8gC0oCswsx0fCTYzI6', '王主任', 'supervisor'),
('device', '$2y$12$0pbirdTPAJ9bZJ1DllZmTO5ERg4e9zHr.Xj8gC0oCswsx0fCTYzI6', '赵设备员', 'device'),
('finance', '$2y$12$0pbirdTPAJ9bZJ1DllZmTO5ERg4e9zHr.Xj8gC0oCswsx0fCTYzI6', '张财务', 'finance');

INSERT INTO t_user (username, password, real_name, role, user_type) VALUES 
('张三', '$2y$12$0pbirdTPAJ9bZJ1DllZmTO5ERg4e9zHr.Xj8gC0oCswsx0fCTYzI6', '张三', 'user', 'teacher');
SET @teacher1_id = LAST_INSERT_ID();
INSERT INTO t_user_teacher (user_id, title, college, research_area) VALUES 
(@teacher1_id, '教授', '物联网工程学院', '嵌入式系统');

INSERT INTO t_user (username, password, real_name, role, user_type) VALUES 
('王老师', '$2y$12$0pbirdTPAJ9bZJ1DllZmTO5ERg4e9zHr.Xj8gC0oCswsx0fCTYzI6', '王建国', 'user', 'teacher');
SET @teacher2_id = LAST_INSERT_ID();
INSERT INTO t_user_teacher (user_id, title, college, research_area) VALUES 
(@teacher2_id, '副教授', '物联网工程学院', '物联网技术');

INSERT INTO t_user (username, password, real_name, role, user_type) VALUES 
('李四', '$2y$12$0pbirdTPAJ9bZJ1DllZmTO5ERg4e9zHr.Xj8gC0oCswsx0fCTYzI6', '李四', 'user', 'student');
SET @student1_id = LAST_INSERT_ID();
INSERT INTO t_user_student (user_id, student_no, major, college, advisor_id) VALUES 
(@student1_id, 'S2024001', '软件工程', '物联网工程学院', @teacher1_id);

INSERT INTO t_user (username, password, real_name, role, user_type) VALUES 
('赵小明', '$2y$12$0pbirdTPAJ9bZJ1DllZmTO5ERg4e9zHr.Xj8gC0oCswsx0fCTYzI6', '赵小明', 'user', 'student');
SET @student2_id = LAST_INSERT_ID();
INSERT INTO t_user_student (user_id, student_no, major, college, advisor_id) VALUES 
(@student2_id, 'S2024002', '计算机科学', '物联网工程学院', @teacher1_id);

INSERT INTO t_user (username, password, real_name, role, user_type) VALUES 
('刘经理', '$2y$12$0pbirdTPAJ9bZJ1DllZmTO5ERg4e9zHr.Xj8gC0oCswsx0fCTYzI6', '刘伟', 'user', 'external');
SET @ext1_id = LAST_INSERT_ID();
INSERT INTO t_user_external (user_id, organization) VALUES 
(@ext1_id, '华为技术有限公司');

INSERT INTO t_user (username, password, real_name, role, user_type) VALUES 
('陈工程师', '$2y$12$0pbirdTPAJ9bZJ1DllZmTO5ERg4e9zHr.Xj8gC0oCswsx0fCTYzI6', '陈明', 'user', 'external');
SET @ext2_id = LAST_INSERT_ID();
INSERT INTO t_user_external (user_id, organization) VALUES 
(@ext2_id, '中兴通讯股份有限公司');

INSERT INTO t_user (username, password, real_name, role, user_type) VALUES 
('赵设备员', '$2y$12$0pbirdTPAJ9bZJ1DllZmTO5ERg4e9zHr.Xj8gC0oCswsx0fCTYzI6', '赵飞', 'user', 'device');

INSERT INTO t_device (device_name, model, manufacturer, category, price, rent_price, status, location) VALUES 
('示波器 A-001', 'TBS1102C', 'Tektronix', '测量仪器', 2500.00, 50.00, 1, '实验室A301'),
('万用表 B-002', '17B+', 'Fluke', '测量仪器', 800.00, 10.00, 1, '实验室A301'),
('信号发生器 C-003', 'DG1022Z', 'RIGOL', '信号源', 1800.00, 30.00, 1, '实验室A302'),
('电源供应器 D-004', 'KA3005D', 'KORAD', '电源设备', 500.00, 20.00, 2, '实验室A302'),
('逻辑分析仪 E-005', 'Logic Pro 16', 'Saleae', '分析仪器', 6000.00, 80.00, 3, '实验室A303');

-- 审批流程配置数据
-- 学生审批流程：导师 → 设备管理员
INSERT INTO t_approval_workflow (user_type, step_order, role_type, is_payment_required, description) VALUES 
('student', 1, 'advisor', 0, '导师审批'),
('student', 2, 'device', 0, '设备管理员审批');

-- 教师审批流程：设备管理员
INSERT INTO t_approval_workflow (user_type, step_order, role_type, is_payment_required, description) VALUES 
('teacher', 1, 'device', 0, '设备管理员审批');

-- 校外人员审批流程：设备管理员 + 实验室负责人(并行) → 支付 → 财务
INSERT INTO t_approval_workflow (user_type, step_order, role_type, is_parallel, is_payment_required, description) VALUES 
('external', 1, 'device', 1, 0, '设备管理员审批'),
('external', 1, 'supervisor', 1, 0, '实验室负责人审批'),
('external', 2, 'finance', 0, 1, '财务审批');
