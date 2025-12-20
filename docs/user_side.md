# 用户侧 API 文档

> 更新时间：2025-12-20
> 
> 本文档描述实验室设备管理系统用户侧 API 接口规范。

---

## 概述

### 基础信息

| 项目 | 说明 |
| --- | --- |
| Base URL | `/api/` |
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

### 状态码说明

| HTTP 状态码 | 说明 |
| --- | --- |
| 200 | 请求成功 |
| 400 | 请求参数错误 |
| 401 | 未授权（Token 无效或过期） |
| 403 | 禁止访问 |
| 404 | 资源不存在 |
| 500 | 服务器内部错误 |

### 鉴权方式

需要登录的接口必须在请求头中携带 Token：

```
Authorization: Bearer <token>
```

### 接口权限分类

本系统接口根据鉴权要求分为以下三类：

| 类别 | 说明 | Token 要求 |
| --- | --- | --- |
| 🔓 **公开接口** | 无需登录即可访问 | 不需要 |
| 🔐 **可选登录** | 登录后可获取更多信息 | 可选 |
| 🔒 **需要登录** | 必须携带有效 Token | 必须 |

### 接口权限一览表

| 接口 | 方法 | 权限 | 说明 |
| --- | --- | --- | --- |
| `/api/register.php` | POST | 🔓 公开 | 用户注册 |
| `/api/login.php` | POST | 🔓 公开 | 用户登录 |
| `/api/login.php?action=logout` | POST | 🔒 登录 | 退出登录 |
| `/api/device.php` | GET | 🔐 可选 | 设备列表（公开可查询可用设备） |
| `/api/device.php?id=X` | GET | 🔐 可选 | 设备详情（公开可查看基本信息） |
| `/api/device.php?action=categories` | GET | 🔓 公开 | 设备类别列表 |
| `/api/reservation.php` | POST | 🔒 登录 | 提交预约申请 |
| `/api/reservation.php` | GET | 🔒 登录 | 获取**我的**预约列表 |
| `/api/reservation.php?action=cancel` | POST | 🔒 登录 | 取消**我的**预约 |
| `/api/borrow.php` | GET | 🔒 登录 | 获取**我的**借用记录 |
| `/api/borrow.php?id=X` | GET | 🔒 登录 | 获取**我的**借用详情 |
| `/api/return.php` | POST | 🔒 登录 | 申请归还 |
| `/api/payment.php` | GET | 🔒 登录 | 获取**我的**支付记录 |
| `/api/payment.php?action=pending` | GET | 🔒 登录 | 获取**我的**待支付订单 |
| `/api/payment.php?action=pay` | POST | 🔒 登录 | 发起支付 |
| `/api/payment.php?action=confirm` | POST | 🔓 公开 | 支付回调确认（模拟） |
| `/api/personal.php` | GET | 🔒 登录 | 获取**我的**个人信息 |
| `/api/personal.php?action=update` | POST | 🔒 登录 | 更新**我的**个人信息 |
| `/api/personal.php?action=change_password` | POST | 🔒 登录 | 修改密码 |

> ⚠️ **敏感数据保护**
> - 用户只能查询和操作**自己的**预约、借用、支付记录
> - 设备详情中的借用人信息对普通用户不可见
> - 管理员查询需使用管理端 API 并携带管理员 Token

---

## 1. 用户认证

### 1.1 用户注册

**POST** `/api/register.php`

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| username | string | ✅ | 用户名（4-20 位，字母数字下划线） |
| password | string | ✅ | 密码（6-32 位） |
| email | string | ✅ | 邮箱 |
| real_name | string | ✅ | 真实姓名 |
| phone | string | ❌ | 手机号 |
| user_type | string | ✅ | 用户类型：`teacher`/`student`/`external` |
| department | string | ❌ | 所属部门/学院 |
| student_id | string | ❌ | 学号（学生必填） |

**请求示例**

```json
{
  "username": "zhangsan",
  "password": "123456",
  "email": "zhangsan@example.com",
  "real_name": "张三",
  "phone": "13800138000",
  "user_type": "student",
  "department": "计算机学院",
  "student_id": "2023130001"
}
```

**响应示例**

```json
{
  "code": 0,
  "message": "注册成功",
  "data": {
    "user_id": 1,
    "username": "zhangsan"
  }
}
```

---

### 1.2 用户登录

**POST** `/api/login.php`

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| username | string | ✅ | 用户名或邮箱 |
| password | string | ✅ | 密码 |

**请求示例**

```json
{
  "username": "zhangsan",
  "password": "123456"
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
    "user": {
      "id": 1,
      "username": "zhangsan",
      "real_name": "张三",
      "user_type": "student",
      "email": "zhangsan@example.com"
    }
  }
}
```

---

### 1.3 退出登录

**POST** `/api/login.php?action=logout`

**请求头**

```
Authorization: Bearer <token>
```

**响应示例**

```json
{
  "code": 0,
  "message": "退出成功",
  "data": null
}
```

---

## 2. 设备管理

### 2.1 获取设备列表

**GET** `/api/device.php`

**权限**：🔐 可选登录

> 💡 **说明**
> - 未登录：可查询所有设备的基本信息和可用状态
> - 已登录：额外显示设备当前被占用的日期区间（不显示借用人信息）

**请求头**（可选）

```
Authorization: Bearer <token>
```

**查询参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| page | int | ❌ | 页码，默认 1 |
| page_size | int | ❌ | 每页数量，默认 20，最大 100 |
| keyword | string | ❌ | 搜索关键词（设备名称、编号） |
| category | string | ❌ | 设备类别 |
| status | string | ❌ | 设备状态：`available`/`borrowed`/`maintenance`/`scrapped` |
| lab_id | int | ❌ | 所属实验室 ID |
| available_only | bool | ❌ | 仅显示可用设备，默认 false |

**响应示例（未登录）**

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
        "description": "数字示波器，100MHz 带宽",
        "image_url": "/uploads/devices/1.jpg"
      }
    ],
    "pagination": {
      "page": 1,
      "page_size": 20,
      "total": 100,
      "total_pages": 5
    }
  }
}
```

**响应示例（已登录 - 额外字段）**

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
        "status": "borrowed",
        "occupied_periods": [
          {
            "start_date": "2025-12-20",
            "end_date": "2025-12-25"
          }
        ],
        "next_available_date": "2025-12-26"
      }
    ]
  }
}
```

---

### 2.2 获取设备详情

**GET** `/api/device.php?id={device_id}`

**权限**：🔐 可选登录

> 💡 **说明**
> - 未登录：可查看设备基本信息、规格、价格等公开信息
> - 已登录：额外显示当前占用时段（不显示借用人信息）
> - 借用人详细信息对普通用户**不可见**，仅管理员可查

**响应示例**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": 1,
    "device_no": "EQ-2023-0001",
    "name": "示波器",
    "category": "电子仪器",
    "brand": "Tektronix",
    "model": "TDS1012B",
    "specifications": "100MHz 带宽，2 通道",
    "status": "available",
    "lab_id": 1,
    "lab_name": "电子工程实验室",
    "daily_price": 50.00,
    "deposit": 500.00,
    "description": "数字示波器，适用于电子电路实验",
    "image_url": "/uploads/devices/1.jpg",
    "purchase_date": "2023-01-15",
    "created_at": "2023-01-20 10:00:00"
  }
}
```

---

### 2.3 获取设备类别列表

**GET** `/api/device.php?action=categories`

**响应示例**

```json
{
  "code": 0,
  "message": "success",
  "data": [
    { "id": 1, "name": "电子仪器", "device_count": 50 },
    { "id": 2, "name": "光学仪器", "device_count": 30 },
    { "id": 3, "name": "机械设备", "device_count": 20 }
  ]
}
```

---

## 3. 预约管理

### 3.1 提交预约申请

**POST** `/api/reservation.php`

**请求头**

```
Authorization: Bearer <token>
```

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| device_id | int | ✅ | 设备 ID |
| start_date | string | ✅ | 开始日期（YYYY-MM-DD） |
| end_date | string | ✅ | 结束日期（YYYY-MM-DD） |
| purpose | string | ✅ | 借用目的 |
| remark | string | ❌ | 备注 |

**请求示例**

```json
{
  "device_id": 1,
  "start_date": "2025-12-25",
  "end_date": "2025-12-30",
  "purpose": "毕业设计实验",
  "remark": "需要配套探头"
}
```

**响应示例**

```json
{
  "code": 0,
  "message": "预约申请已提交",
  "data": {
    "reservation_id": 100,
    "reservation_no": "RSV-20251220-0001",
    "status": "pending",
    "estimated_price": 300.00
  }
}
```

---

### 3.2 获取我的预约列表

**GET** `/api/reservation.php`

**请求头**

```
Authorization: Bearer <token>
```

**查询参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| page | int | ❌ | 页码 |
| page_size | int | ❌ | 每页数量 |
| status | string | ❌ | 状态筛选：`pending`/`approved`/`rejected`/`cancelled` |

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
    "pagination": {
      "page": 1,
      "page_size": 20,
      "total": 5,
      "total_pages": 1
    }
  }
}
```

---

### 3.3 取消预约

**POST** `/api/reservation.php?action=cancel`

**请求头**

```
Authorization: Bearer <token>
```

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| reservation_id | int | ✅ | 预约 ID |
| reason | string | ❌ | 取消原因 |

**响应示例**

```json
{
  "code": 0,
  "message": "预约已取消",
  "data": null
}
```

---

## 4. 借用管理

### 4.1 获取借用记录

**GET** `/api/borrow.php`

**请求头**

```
Authorization: Bearer <token>
```

**查询参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| page | int | ❌ | 页码 |
| page_size | int | ❌ | 每页数量 |
| status | string | ❌ | 状态：`borrowing`/`returned`/`overdue` |

**响应示例**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "items": [
      {
        "id": 50,
        "borrow_no": "BRW-20251220-0001",
        "device_id": 1,
        "device_name": "示波器",
        "device_no": "EQ-2023-0001",
        "borrow_date": "2025-12-20",
        "expected_return_date": "2025-12-25",
        "actual_return_date": null,
        "status": "borrowing",
        "daily_price": 50.00,
        "total_price": 250.00,
        "deposit": 500.00,
        "is_overdue": false
      }
    ],
    "pagination": {
      "page": 1,
      "page_size": 20,
      "total": 3,
      "total_pages": 1
    }
  }
}
```

---

### 4.2 获取借用详情

**GET** `/api/borrow.php?id={borrow_id}`

**请求头**

```
Authorization: Bearer <token>
```

**响应示例**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": 50,
    "borrow_no": "BRW-20251220-0001",
    "reservation_id": 100,
    "device": {
      "id": 1,
      "name": "示波器",
      "device_no": "EQ-2023-0001",
      "lab_name": "电子工程实验室"
    },
    "borrow_date": "2025-12-20",
    "expected_return_date": "2025-12-25",
    "actual_return_date": null,
    "status": "borrowing",
    "daily_price": 50.00,
    "total_price": 250.00,
    "deposit": 500.00,
    "payment_status": "paid",
    "is_overdue": false,
    "overdue_days": 0,
    "overdue_fee": 0,
    "created_at": "2025-12-20 10:00:00"
  }
}
```

---

## 5. 归还管理

### 5.1 申请归还

**POST** `/api/return.php`

**请求头**

```
Authorization: Bearer <token>
```

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| borrow_id | int | ✅ | 借用记录 ID |
| remark | string | ❌ | 备注（设备状况说明） |

**响应示例**

```json
{
  "code": 0,
  "message": "归还申请已提交，请等待管理员确认",
  "data": {
    "return_id": 30,
    "status": "pending"
  }
}
```

---

## 6. 缴费管理

### 6.1 获取待支付订单

**GET** `/api/payment.php?action=pending`

**请求头**

```
Authorization: Bearer <token>
```

**响应示例**

```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": 10,
      "order_no": "PAY-20251220-0001",
      "type": "borrow_fee",
      "type_name": "借用费用",
      "amount": 300.00,
      "borrow_id": 50,
      "device_name": "示波器",
      "created_at": "2025-12-20 10:00:00"
    }
  ]
}
```

---

### 6.2 获取支付历史

**GET** `/api/payment.php`

**请求头**

```
Authorization: Bearer <token>
```

**查询参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| page | int | ❌ | 页码 |
| page_size | int | ❌ | 每页数量 |
| status | string | ❌ | 状态：`pending`/`paid`/`refunded` |

**响应示例**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "items": [
      {
        "id": 10,
        "order_no": "PAY-20251220-0001",
        "type": "borrow_fee",
        "type_name": "借用费用",
        "amount": 300.00,
        "status": "paid",
        "pay_method": "wechat",
        "pay_time": "2025-12-20 10:30:00",
        "created_at": "2025-12-20 10:00:00"
      }
    ],
    "pagination": {
      "page": 1,
      "page_size": 20,
      "total": 10,
      "total_pages": 1
    }
  }
}
```

---

### 6.3 发起支付（模拟）

**POST** `/api/payment.php?action=pay`

**请求头**

```
Authorization: Bearer <token>
```

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| order_id | int | ✅ | 订单 ID |
| pay_method | string | ✅ | 支付方式：`wechat`/`alipay` |

**响应示例**

```json
{
  "code": 0,
  "message": "支付链接已生成",
  "data": {
    "pay_url": "https://example.com/pay/qr/xxx",
    "qr_code": "base64_encoded_qr_image"
  }
}
```

---

### 6.4 确认支付（模拟回调）

**POST** `/api/payment.php?action=confirm`

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| order_no | string | ✅ | 订单号 |

**响应示例**

```json
{
  "code": 0,
  "message": "支付成功",
  "data": {
    "order_no": "PAY-20251220-0001",
    "status": "paid",
    "pay_time": "2025-12-20 10:30:00"
  }
}
```

---

## 7. 个人信息

### 7.1 获取个人信息

**GET** `/api/personal.php`

**请求头**

```
Authorization: Bearer <token>
```

**响应示例**

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": 1,
    "username": "zhangsan",
    "real_name": "张三",
    "email": "zhangsan@example.com",
    "phone": "13800138000",
    "user_type": "student",
    "user_type_name": "学生",
    "department": "计算机学院",
    "student_id": "2023130001",
    "avatar_url": "/uploads/avatars/1.jpg",
    "created_at": "2025-01-01 10:00:00"
  }
}
```

---

### 7.2 更新个人信息

**POST** `/api/personal.php?action=update`

**请求头**

```
Authorization: Bearer <token>
```

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| real_name | string | ❌ | 真实姓名 |
| phone | string | ❌ | 手机号 |
| email | string | ❌ | 邮箱 |
| department | string | ❌ | 所属部门 |

**响应示例**

```json
{
  "code": 0,
  "message": "更新成功",
  "data": null
}
```

---

### 7.3 修改密码

**POST** `/api/personal.php?action=change_password`

**请求头**

```
Authorization: Bearer <token>
```

**请求参数**

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| old_password | string | ✅ | 旧密码 |
| new_password | string | ✅ | 新密码（6-32 位） |

**响应示例**

```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": null
}
```

---

## 错误码说明

| 错误码 | 说明 |
| --- | --- |
| 0 | 成功 |
| 1 | 通用错误 |
| 100 | 参数错误 |
| 101 | 用户名已存在 |
| 102 | 邮箱已注册 |
| 103 | 用户名或密码错误 |
| 200 | 设备不存在 |
| 201 | 设备不可用 |
| 300 | 预约不存在 |
| 301 | 预约已取消 |
| 302 | 日期冲突 |
| 400 | 借用记录不存在 |
| 401 | 未授权访问 |
| 500 | 支付失败 |

---

## 附录：用户类型

| 类型值 | 说明 | 最大借用天数 |
| --- | --- | --- |
| teacher | 教师 | 30 天 |
| student | 学生 | 14 天 |
| external | 校外人员 | 7 天 |

## 附录：设备状态

| 状态值 | 说明 |
| --- | --- |
| available | 可用 |
| borrowed | 已借出 |
| maintenance | 维护中 |
| scrapped | 已报废 |

## 附录：预约状态

| 状态值 | 说明 |
| --- | --- |
| pending | 待审核 |
| approved | 已批准 |
| rejected | 已驳回 |
| cancelled | 已取消 |
| completed | 已完成（已借用） |

## 附录：借用状态

| 状态值 | 说明 |
| --- | --- |
| borrowing | 借用中 |
| returned | 已归还 |
| overdue | 逾期中 |

## 附录：支付状态

| 状态值 | 说明 |
| --- | --- |
| pending | 待支付 |
| paid | 已支付 |
| refunded | 已退款 |
