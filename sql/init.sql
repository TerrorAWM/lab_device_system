CREATE DATABASE IF NOT EXISTS lab_device_system
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE lab_device_system;

CREATE TABLE IF NOT EXISTS t_user (
    user_id INT NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    PRIMARY KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS t_device (
    device_id INT NOT NULL AUTO_INCREMENT,
    device_name VARCHAR(100) NOT NULL,
    status TINYINT NOT NULL DEFAULT 0,
    PRIMARY KEY (device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS t_borrow_record (
    record_id INT NOT NULL AUTO_INCREMENT,
    fk_user_id INT NOT NULL,
    fk_device_id INT NOT NULL,
    borrow_date DATETIME NOT NULL,
    PRIMARY KEY (record_id),
    CONSTRAINT fk_borrow_user FOREIGN KEY (fk_user_id) REFERENCES t_user(user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_borrow_device FOREIGN KEY (fk_device_id) REFERENCES t_device(device_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_borrow_user ON t_borrow_record(fk_user_id);
CREATE INDEX idx_borrow_device ON t_borrow_record(fk_device_id);
CREATE INDEX idx_borrow_date ON t_borrow_record(borrow_date);

INSERT INTO t_user (username) VALUES 
    ('张三'),
    ('李四'),
    ('王五');

INSERT INTO t_device (device_name, status) VALUES 
    ('示波器 A-001', 0),
    ('万用表 B-002', 0),
    ('信号发生器 C-003', 0),
    ('电源供应器 D-004', 1),
    ('逻辑分析仪 E-005', 2);

INSERT INTO t_borrow_record (fk_user_id, fk_device_id, borrow_date) VALUES 
    (1, 4, NOW());
