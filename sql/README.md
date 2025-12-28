# 数据库说明文档

本文档说明实验室设备管理系统的数据库结构和初始化数据。

## 表结构说明

### 用户模块

#### t_user - 用户基表

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| user_id | INT | 主键 |
| username | VARCHAR(50) | 用户名，唯一 |
| password | VARCHAR(100) | 密码 |
| real_name | VARCHAR(50) | 真实姓名 |
| role | ENUM | admin/user |
| user_type | ENUM | teacher/student/external/device |
| phone | VARCHAR(20) | 联系电话 |
| status | TINYINT | 1启用 0禁用 |

#### t_user_teacher - 教师扩展表

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| user_id | INT | 关联t_user主键 |
| title | VARCHAR(50) | 职称 |
| college | VARCHAR(50) | 学院 |
| research_area | VARCHAR(100) | 研究方向 |

#### t_user_student - 学生扩展表

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| user_id | INT | 关联t_user主键 |
| student_no | VARCHAR(20) | 学号，唯一 |
| major | VARCHAR(50) | 专业 |
| college | VARCHAR(50) | 学院 |
| advisor_id | INT | 导师ID，关联t_user |

#### t_user_external - 校外人员扩展表

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| user_id | INT | 关联t_user主键 |
| organization | VARCHAR(100) | 所属单位 |
| identity_card | VARCHAR(20) | 身份证号 |

---

### 管理员模块

#### t_admin - 管理员表

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| admin_id | INT | 主键 |
| username | VARCHAR(50) | 用户名，唯一 |
| password | VARCHAR(100) | 密码 |
| real_name | VARCHAR(50) | 真实姓名 |
| role | ENUM | supervisor/device/finance |
| status | TINYINT | 1启用 0禁用 |

**管理员角色说明：**

| 角色 | 说明 | 权限 |
| --- | --- | --- |
| supervisor | 实验室负责人 | 全部功能 |
| device | 设备管理员 | 设备管理、预约审批、借用管理 |
| finance | 财务管理员 | 收费管理、统计报表 |

---

### 设备模块

#### t_device - 设备表

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| device_id | INT | 主键 |
| device_name | VARCHAR(100) | 设备名称 |
| model | VARCHAR(100) | 型号 |
| manufacturer | VARCHAR(100) | 制造商 |
| price | DECIMAL(10,2) | 设备价值 |
| rent_price | DECIMAL(10,2) | 2小时租金 |
| category | VARCHAR(50) | 分类 |
| status | TINYINT | 1可用 2借出 3维修 |
| location | VARCHAR(50) | 存放位置 |

---

### 业务模块

#### t_reservation - 预约表

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| reservation_id | INT | 主键 |
| user_id | INT | 申请人ID |
| device_id | INT | 设备ID |
| reserve_date | DATE | 预约日期 |
| time_slot | ENUM | 时段 |
| purpose | TEXT | 用途说明 |
| status | TINYINT | 0待审核 1已通过 2已驳回 |
| approvals | JSON | 审批节点状态 |

**时段选项：**
- 08:00-10:00
- 10:00-12:00
- 14:00-16:00
- 16:00-18:00
- 19:00-21:00

**approvals JSON格式示例：**
```json
{
  "teacher": "approved",
  "device": "pending",
  "supervisor": "waiting",
  "finance": "waiting"
}
```
状态值：pending(待审) / approved(已批) / rejected(驳回) / waiting(等待前置审批)

#### t_borrow_record - 借用记录表

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| record_id | INT | 主键 |
| reservation_id | INT | 关联预约 |
| borrow_date | DATE | 借用日期 |
| time_slot | ENUM | 时段 |
| actual_return | DATETIME | 实际归还时间 |
| status | TINYINT | 1借用中 2已归还 |

#### t_payment - 支付记录表

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| payment_id | INT | 主键 |
| reservation_id | INT | 关联预约 |
| user_id | INT | 用户ID |
| amount | DECIMAL(10,2) | 金额(校内为0) |
| status | TINYINT | 0待支付 1已支付 2已取消 |

---

### 审批流程模块

#### t_approval_workflow - 审批流程配置表

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| workflow_id | INT | 主键 |
| user_type | ENUM | teacher/student/external |
| step_order | INT | 步骤顺序 1,2,3... |
| role_type | ENUM | advisor/device/supervisor/finance |
| is_payment_required | TINYINT | 是否需要付款 |
| is_enabled | TINYINT | 是否启用 |
| description | VARCHAR(100) | 步骤描述 |

**审批角色说明：**

| 角色 | 说明 |
| --- | --- |
| advisor | 导师 |
| device | 设备管理员 |
| supervisor | 实验室负责人 |
| finance | 财务 |

**默认审批流程：**

| 用户类型 | 审批链路 |
| --- | --- |
| 学生 | 导师 → 设备管理员 |
| 教师 | 设备管理员 |
| 校外 | 设备管理员 + 实验室负责人(并行) → 支付 → 财务 |

#### t_approval_log - 审批日志表

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| log_id | INT | 主键 |
| reservation_id | INT | 预约ID |
| step_order | INT | 审批步骤 |
| role_type | ENUM | 审批角色 |
| approver_id | INT | 审批人ID |
| approver_type | ENUM | user/admin |
| action | ENUM | approve/reject |
| reason | VARCHAR(255) | 原因/备注 |

---

## 初始化数据

### 管理员账号

| 用户名 | 密码 | 角色 |
| --- | --- | --- |
| supervisor | 123456 | 实验室负责人 |
| device | 123456 | 设备管理员 |
| finance | 123456 | 财务管理员 |

### 用户账号

| 用户名 | 密码 | 类型 | 说明 |
| --- | --- | --- | --- |
| 张三 | 123456 | 教师 | 教授，李四的导师 |
| 王老师 | 123456 | 教师 | 副教授 |
| 李四 | 123456 | 学生 | 张三的学生 |
| 赵小明 | 123456 | 学生 | 张三的学生 |
| 刘经理 | 123456 | 校外 | 华为 |
| 陈工程师 | 123456 | 校外 | 中兴 |
| 赵设备员 | 123456 | 设备管理员 | - |

### 设备数据

| 名称 | 型号 | 2小时租金 | 状态 |
| --- | --- | --- | --- |
| 示波器 A-001 | TBS1102C | ¥50 | 可用 |
| 万用表 B-002 | 17B+ | ¥10 | 可用 |
| 信号发生器 C-003 | DG1022Z | ¥30 | 可用 |
| 电源供应器 D-004 | KA3005D | ¥20 | 借出 |
| 逻辑分析仪 E-005 | Logic Pro 16 | ¥80 | 维修 |
