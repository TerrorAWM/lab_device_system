# 管理端 API 文档

> 更新时间：2025-12-20
> 
> 本文档描述实验室设备管理系统管理端 API 接口规范。

---

## 概述

### 基础信息

| 项目 | 说明 |
| --- | --- |
| Base URL | `/admin/api/` |
| 协议 | HTTP/HTTPS |
| 数据格式 | JSON |
| 编码 | UTF-8 |

### 通用响应格式

**成功响应**

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

**错误响应**

```json
{
  "code": 1,
  "message": "错误信息",
  "data": null
}
```

### 鉴权方式

所有管理端接口（除登录外）必须在请求头中携带管理员 Token：

```
Authorization: Bearer <admin_token>
```

### 安全说明

> ⚠️ **重要提醒**
> 
> - **所有管理端查询接口都需要有效的管理员 Token**
> - 管理员可查看所有用户的预约、借用、支付等敏感数据
> - 普通用户 Token **无法** 访问管理端 API，会返回 401 错误
> - 不同角色的管理员权限有所不同，详见附录

### 接口权限一览表

| 接口 | 方法 | 最低权限 | 说明 |
| --- | --- | --- | --- |
| `/admin/api/login.php` | POST | 🔓 公开 | 管理员登录 |
| `/admin/api/register.php` | POST | 🔓 公开 | 管理员注册（开发期） |
| `/admin/api/reset_password.php` | POST | 🔓 公开 | 密码重置（需安全密钥） |
| `/admin/api/device.php` | GET | 🔒 device_admin | 获取设备列表（含借用人信息） |
| `/admin/api/device.php?action=create` | POST | 🔒 device_admin | 新增设备 |
| `/admin/api/device.php?action=update` | POST | 🔒 device_admin | 更新设备 |
| `/admin/api/device.php?action=delete` | POST | 🔒 super_admin | 删除设备 |
| `/admin/api/reservation.php` | GET | 🔒 lab_manager | 获取所有预约列表 |
| `/admin/api/reservation.php?action=approve` | POST | 🔒 lab_manager | 审批预约 |
| `/admin/api/reservation.php?action=reject` | POST | 🔒 lab_manager | 驳回预约 |
| `/admin/api/borrow.php` | GET | 🔒 device_admin | 获取所有借用记录 |
| `/admin/api/borrow.php?action=dispatch` | POST | 🔒 device_admin | 发放设备 |
| `/admin/api/borrow.php?action=confirm_return` | POST | 🔒 device_admin | 确认归还 |
| `/admin/api/payment.php` | GET | 🔒 lab_manager | 获取所有支付订单 |
| `/admin/api/payment.php?action=mark_paid` | POST | 🔒 lab_manager | 标记已支付 |
| `/admin/api/payment.php?action=refund` | POST | 🔒 super_admin | 退款 |
| `/admin/api/user.php` | GET | 🔒 lab_manager | 获取用户列表 |
| `/admin/api/user.php?action=toggle_status` | POST | 🔒 super_admin | 禁用/启用用户 |
| `/admin/api/stats.php` | GET | 🔒 lab_manager | 统计报表 |
| `/admin/api/stats.php?action=export` | GET | 🔒 lab_manager | 导出报表 |

### 与用户侧 API 的区别

| 特性 | 用户侧 API | 管理端 API |
| --- | --- | --- |
| **数据范围** | 仅自己的数据 | 所有用户的数据 |
| **预约信息** | 仅自己的预约 | 所有预约（含用户信息） |
| **借用信息** | 仅自己的借用 | 所有借用（含借用人详情） |
| **设备信息** | 公开信息 + 占用时段 | 完整信息 + 当前借用人 |
| **支付信息** | 仅自己的支付 | 所有支付记录 |

---

## 1. 管理员认证

### 1.1 管理员登录

**POST** `/admin/api/login.php`

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| username | string | ✅ | 管理员用户名 |
| password | string | ✅ | 密码 |

**请求示例**

```json
{
  "username": "admin",
  "password": "admin123"
}
```

**响应示例**

```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_at": "2025-12-27 14:00:00",
    "admin": {
      "id": 1,
      "username": "admin",
      "real_name": "系统管理员",
      "role": "super_admin"
    }
  }
}
```

---

### 1.2 管理员注册（开发期）

**POST** `/admin/api/register.php`

> ⚠️ **注意**：此接口仅供开发测试使用，生产环境应关闭

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| username | string | ✅ | 用户名 |
| password | string | ✅ | 密码 |
| real_name | string | ✅ | 真实姓名 |
| role | string | ✅ | 角色：`super_admin`/`lab_manager`/`device_admin` |

---

### 1.3 重置密码（开发期）

**POST** `/admin/api/reset_password.php`

> ⚠️ **注意**：此接口仅供开发测试使用，生产环境应关闭或加强验证

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| admin_id | int | ✅ | 管理员 ID |
| new_password | string | ✅ | 新密码 |
| secret_key | string | ✅ | 安全密钥 |

---

## 2. 设备台账管理

### 2.1 获取设备列表

**GET** `/admin/api/device.php`

**请求头**

```
Authorization: Bearer <admin_token>
```

**查询参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| page | int | ❌ | 页码 |
| page_size | int | ❌ | 每页数量 |
| keyword | string | ❌ | 搜索关键词 |
| category | string | ❌ | 设备类别 |
| status | string | ❌ | 设备状态 |
| lab_id | int | ❌ | 实验室 ID |

**响应示例**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "items": [
      {
        "id": 1,
        "device_no": "EQ-2023-0001",
        "name": "示波器",
        "category": "电子仪器",
        "brand": "Tektronix",
        "model": "TDS1012B",
        "status": "available",
        "lab_id": 1,
        "lab_name": "电子工程实验室",
        "daily_price": 50.00,
        "deposit": 500.00,
        "purchase_date": "2023-01-15",
        "purchase_price": 5000.00,
        "total_borrows": 20,
        "current_borrower": null
      }
    ],
    "pagination": { ... }
  }
}
```

---

### 2.2 新增设备

**POST** `/admin/api/device.php?action=create`

**请求头**

```
Authorization: Bearer <admin_token>
```

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| name | string | ✅ | 设备名称 |
| category | string | ✅ | 设备类别 |
| brand | string | ❌ | 品牌 |
| model | string | ❌ | 型号 |
| specifications | string | ❌ | 规格参数 |
| lab_id | int | ✅ | 所属实验室 ID |
| daily_price | decimal | ✅ | 日租金 |
| deposit | decimal | ❌ | 押金 |
| description | string | ❌ | 描述 |
| purchase_date | string | ❌ | 购买日期（YYYY-MM-DD） |
| purchase_price | decimal | ❌ | 购买价格 |

**响应示例**

```json
{
  "code": 0,
  "message": "设备添加成功",
  "data": {
    "id": 100,
    "device_no": "EQ-2025-0100"
  }
}
```

---

### 2.3 更新设备

**POST** `/admin/api/device.php?action=update`

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| id | int | ✅ | 设备 ID |
| name | string | ❌ | 设备名称 |
| category | string | ❌ | 设备类别 |
| ... | ... | ❌ | 其他可更新字段 |

---

### 2.4 更新设备状态

**POST** `/admin/api/device.php?action=update_status`

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| id | int | ✅ | 设备 ID |
| status | string | ✅ | 新状态：`available`/`maintenance`/`scrapped` |
| reason | string | ❌ | 状态变更原因 |

---

### 2.5 删除设备

**POST** `/admin/api/device.php?action=delete`

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| id | int | ✅ | 设备 ID |

> ⚠️ 仅可删除状态为"报废"且无未完成借用的设备

---

## 3. 预约审批管理

### 3.1 获取预约列表

**GET** `/admin/api/reservation.php`

**查询参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| page | int | ❌ | 页码 |
| page_size | int | ❌ | 每页数量 |
| status | string | ❌ | 预约状态 |
| user_id | int | ❌ | 用户 ID |
| device_id | int | ❌ | 设备 ID |
| start_date | string | ❌ | 开始日期范围起点 |
| end_date | string | ❌ | 开始日期范围终点 |

**响应示例**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "items": [
      {
        "id": 100,
        "reservation_no": "RSV-20251220-0001",
        "user_id": 1,
        "user_name": "张三",
        "user_type": "student",
        "device_id": 1,
        "device_name": "示波器",
        "device_no": "EQ-2023-0001",
        "start_date": "2025-12-25",
        "end_date": "2025-12-30",
        "days": 6,
        "purpose": "毕业设计实验",
        "status": "pending",
        "estimated_price": 300.00,
        "created_at": "2025-12-20 14:00:00"
      }
    ],
    "pagination": { ... }
  }
}
```

---

### 3.2 审批预约

**POST** `/admin/api/reservation.php?action=approve`

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| reservation_id | int | ✅ | 预约 ID |
| remark | string | ❌ | 审批备注 |

**响应示例**

```json
{
  "code": 0,
  "message": "预约已批准",
  "data": {
    "reservation_id": 100,
    "status": "approved"
  }
}
```

---

### 3.3 驳回预约

**POST** `/admin/api/reservation.php?action=reject`

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| reservation_id | int | ✅ | 预约 ID |
| reason | string | ✅ | 驳回原因 |

---

## 4. 借用管理

### 4.1 获取借用列表

**GET** `/admin/api/borrow.php`

**查询参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| page | int | ❌ | 页码 |
| page_size | int | ❌ | 每页数量 |
| status | string | ❌ | 借用状态 |
| user_id | int | ❌ | 用户 ID |
| device_id | int | ❌ | 设备 ID |
| is_overdue | bool | ❌ | 是否逾期 |

---

### 4.2 发放设备（确认借用）

**POST** `/admin/api/borrow.php?action=dispatch`

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| reservation_id | int | ✅ | 预约 ID |
| remark | string | ❌ | 发放备注 |

**响应示例**

```json
{
  "code": 0,
  "message": "设备已发放",
  "data": {
    "borrow_id": 50,
    "borrow_no": "BRW-20251220-0001"
  }
}
```

---

### 4.3 确认归还

**POST** `/admin/api/borrow.php?action=confirm_return`

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| borrow_id | int | ✅ | 借用记录 ID |
| device_condition | string | ✅ | 设备状况：`good`/`damaged`/`lost` |
| damage_fee | decimal | ❌ | 损坏赔偿费（如有损坏） |
| remark | string | ❌ | 归还备注 |

---

### 4.4 处理逾期

**POST** `/admin/api/borrow.php?action=handle_overdue`

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| borrow_id | int | ✅ | 借用记录 ID |
| overdue_fee | decimal | ✅ | 逾期费用 |
| action | string | ✅ | 处理方式：`charge`/`waive` |

---

## 5. 收费管理

### 5.1 获取支付订单列表

**GET** `/admin/api/payment.php`

**查询参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| page | int | ❌ | 页码 |
| page_size | int | ❌ | 每页数量 |
| status | string | ❌ | 支付状态 |
| user_id | int | ❌ | 用户 ID |
| type | string | ❌ | 费用类型 |
| start_date | string | ❌ | 开始日期 |
| end_date | string | ❌ | 结束日期 |

---

### 5.2 手动标记已支付

**POST** `/admin/api/payment.php?action=mark_paid`

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| order_id | int | ✅ | 订单 ID |
| pay_method | string | ✅ | 支付方式：`cash`/`transfer`/`wechat`/`alipay` |
| remark | string | ❌ | 备注 |

---

### 5.3 退款

**POST** `/admin/api/payment.php?action=refund`

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| order_id | int | ✅ | 订单 ID |
| refund_amount | decimal | ✅ | 退款金额 |
| reason | string | ✅ | 退款原因 |

---

### 5.4 创建费用单

**POST** `/admin/api/payment.php?action=create`

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| user_id | int | ✅ | 用户 ID |
| borrow_id | int | ❌ | 关联借用记录 ID |
| type | string | ✅ | 费用类型：`borrow_fee`/`deposit`/`overdue_fee`/`damage_fee` |
| amount | decimal | ✅ | 金额 |
| description | string | ❌ | 费用说明 |

---

## 6. 用户管理

### 6.1 获取用户列表

**GET** `/admin/api/user.php`

**查询参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| page | int | ❌ | 页码 |
| page_size | int | ❌ | 每页数量 |
| keyword | string | ❌ | 搜索关键词（用户名、姓名、学号） |
| user_type | string | ❌ | 用户类型 |
| status | string | ❌ | 账号状态：`active`/`disabled` |

---

### 6.2 获取用户详情

**GET** `/admin/api/user.php?id={user_id}`

---

### 6.3 禁用/启用用户

**POST** `/admin/api/user.php?action=toggle_status`

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| user_id | int | ✅ | 用户 ID |
| status | string | ✅ | 新状态：`active`/`disabled` |
| reason | string | ❌ | 原因 |

---

## 7. 统计报表

### 7.1 仪表盘概览

**GET** `/admin/api/stats.php?action=dashboard`

**响应示例**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "summary": {
      "total_devices": 150,
      "available_devices": 100,
      "borrowed_devices": 45,
      "maintenance_devices": 5,
      "total_users": 500,
      "active_borrows": 45,
      "pending_reservations": 10,
      "overdue_borrows": 2
    },
    "today": {
      "new_reservations": 5,
      "completed_borrows": 3,
      "new_payments": 8,
      "revenue": 1500.00
    }
  }
}
```

---

### 7.2 设备使用统计

**GET** `/admin/api/stats.php?action=device_usage`

**查询参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| period | string | ✅ | 统计周期：`week`/`month`/`year` |
| device_id | int | ❌ | 指定设备 ID |
| category | string | ❌ | 设备类别 |

**响应示例**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "period": "month",
    "start_date": "2025-12-01",
    "end_date": "2025-12-31",
    "usage_rate": 75.5,
    "total_borrow_days": 450,
    "top_devices": [
      { "device_id": 1, "name": "示波器", "borrow_count": 20, "usage_days": 60 },
      { "device_id": 2, "name": "信号发生器", "borrow_count": 15, "usage_days": 45 }
    ],
    "daily_stats": [
      { "date": "2025-12-01", "borrows": 5, "returns": 3 },
      { "date": "2025-12-02", "borrows": 8, "returns": 4 }
    ]
  }
}
```

---

### 7.3 收入统计

**GET** `/admin/api/stats.php?action=revenue`

**查询参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| period | string | ✅ | 统计周期：`week`/`month`/`year` |
| start_date | string | ❌ | 自定义开始日期 |
| end_date | string | ❌ | 自定义结束日期 |

**响应示例**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "period": "month",
    "total_revenue": 50000.00,
    "by_type": {
      "borrow_fee": 40000.00,
      "deposit": 5000.00,
      "overdue_fee": 3000.00,
      "damage_fee": 2000.00
    },
    "daily_revenue": [
      { "date": "2025-12-01", "amount": 1500.00 },
      { "date": "2025-12-02", "amount": 2000.00 }
    ],
    "refunds": 1000.00,
    "net_revenue": 49000.00
  }
}
```

---

### 7.4 用户活跃统计

**GET** `/admin/api/stats.php?action=user_activity`

**查询参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| period | string | ✅ | 统计周期 |

**响应示例**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "active_users": 150,
    "new_users": 20,
    "by_type": {
      "teacher": 30,
      "student": 100,
      "external": 20
    },
    "top_borrowers": [
      { "user_id": 1, "name": "张三", "borrow_count": 10 },
      { "user_id": 2, "name": "李四", "borrow_count": 8 }
    ]
  }
}
```

---

### 7.5 导出报表

**GET** `/admin/api/stats.php?action=export`

**查询参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| report_type | string | ✅ | 报表类型：`device_usage`/`revenue`/`borrow_records` |
| period | string | ✅ | 统计周期 |
| format | string | ❌ | 导出格式：`csv`/`xlsx`，默认 csv |

**响应**

返回文件下载

---

## 错误码说明

| 错误码 | 说明 |
| --- | --- |
| 0 | 成功 |
| 1 | 通用错误 |
| 100 | 参数错误 |
| 401 | 未授权访问 |
| 403 | 权限不足 |
| 1001 | 设备不存在 |
| 1002 | 设备状态不允许操作 |
| 2001 | 预约不存在 |
| 2002 | 预约状态不允许操作 |
| 3001 | 借用记录不存在 |
| 3002 | 借用状态不允许操作 |
| 4001 | 用户不存在 |
| 5001 | 订单不存在 |
| 5002 | 订单已支付 |
| 5003 | 退款失败 |

---

## 附录：管理员角色

| 角色值 | 说明 | 权限范围 |
| --- | --- | --- |
| super_admin | 超级管理员 | 全部权限 |
| lab_manager | 实验室负责人 | 预约审批、借用管理、查看报表 |
| device_admin | 设备管理员 | 设备管理、借用操作、归还确认 |

## 附录：费用类型

| 类型值 | 说明 |
| --- | --- |
| borrow_fee | 借用费用 |
| deposit | 押金 |
| overdue_fee | 逾期费用 |
| damage_fee | 损坏赔偿 |
| refund | 退款 |

## 附录：操作日志

所有管理端操作都会记录到 `admin_logs` 表，包含：

- 管理员 ID
- 操作类型
- 操作详情
- IP 地址
- 操作时间
