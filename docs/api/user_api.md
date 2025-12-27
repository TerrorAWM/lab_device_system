# 用户端 API 使用文档

> 实验室设备管理系统 - User API Reference  
> Base URL: `/api`

---

## 通用说明

### 请求格式
- Content-Type: `application/json`
- 认证方式: Bearer Token (在 Header 中携带 `Authorization: Bearer <token>`)

### 响应格式
```json
{
  "code": 0,           // 0=成功, 其他=失败
  "message": "success",
  "data": { ... }      // 业务数据
}
```

### 状态码
| HTTP Code | 说明 |
|-----------|------|
| 200 | 成功 |
| 400 | 参数错误 |
| 401 | 未授权 |
| 404 | 资源不存在 |
| 405 | 方法不允许 |
| 500 | 服务器错误 |

---

## 1. 认证模块

### 1.1 用户注册
`POST /api/register.php`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| username | string | ✓ | 用户名 |
| password | string | ✓ | 密码（≥6位，bcrypt加密存储） |
| real_name | string | ✓ | 真实姓名 |
| user_type | string | ✓ | 用户类型: `teacher` / `student` / `external` |
| phone | string | - | 手机号 |

**学生额外参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| student_no | string | ✓ | 学号 |
| major | string | - | 专业 |
| college | string | - | 学院 |
| advisor_id | int | - | 导师ID |

**教师额外参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| title | string | - | 职称 |
| college | string | - | 学院 |
| research_area | string | - | 研究方向 |

**校外人员额外参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| organization | string | - | 所属单位 |
| identity_card | string | - | 身份证号 |

**响应示例:**
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
`POST /api/login.php`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| username | string | ✓ | 用户名 |
| password | string | ✓ | 密码 |

**响应示例:**
```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiI...",
    "expires_at": "2025-01-03 19:00:00",
    "user": {
      "user_id": 1,
      "username": "zhangsan",
      "real_name": "张三",
      "user_type": "student"
    }
  }
}
```

---

### 1.3 退出登录
`POST /api/login.php?action=logout`

**请求头:** `Authorization: Bearer <token>`

**响应示例:**
```json
{
  "code": 0,
  "message": "退出成功",
  "data": null
}
```

---

## 2. 设备模块

### 2.1 设备列表
`GET /api/device.php`

**查询参数:**
| 参数 | 类型 | 说明 |
|------|------|------|
| keyword | string | 搜索关键词 |
| category | string | 设备类别 |
| status | int | 状态: 1=可用, 2=借出, 3=维护, 4=报废 |
| page | int | 页码（默认1） |
| page_size | int | 每页数量（默认20） |

### 2.2 设备详情
`GET /api/device.php?id=<device_id>`

### 2.3 设备类别
`GET /api/device.php?action=categories`

---

## 3. 预约模块

### 3.1 我的预约列表
`GET /api/reservation.php`

**请求头:** `Authorization: Bearer <token>`

### 3.2 创建预约
`POST /api/reservation.php`

**请求头:** `Authorization: Bearer <token>`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| device_id | int | ✓ | 设备ID |
| reserve_date | string | ✓ | 预约日期 (YYYY-MM-DD) |
| time_slot | string | ✓ | 时段: `08:00-10:00` 等 |
| purpose | string | ✓ | 使用目的 |

### 3.3 修改预约
`POST /api/reservation.php?action=update`

### 3.4 取消预约
`POST /api/reservation.php?action=cancel`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| reservation_id | int | ✓ | 预约ID |

---

## 4. 借用模块

### 4.1 借用记录列表
`GET /api/borrow.php`

**请求头:** `Authorization: Bearer <token>`

### 4.2 借用详情
`GET /api/borrow.php?id=<borrow_id>`

---

## 5. 归还模块

### 5.1 申请归还
`POST /api/return.php`

**请求头:** `Authorization: Bearer <token>`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| borrow_id | int | ✓ | 借用记录ID |

---

## 6. 缴费模块

### 6.1 支付记录列表
`GET /api/payment.php`

### 6.2 待支付列表
`GET /api/payment.php?action=pending`

### 6.3 发起支付
`POST /api/payment.php?action=pay`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| payment_id | int | ✓ | 支付订单ID |
| pay_method | string | ✓ | 支付方式: `wechat` / `alipay` |

### 6.4 支付确认（回调）
`POST /api/payment.php?action=confirm`

---

## 7. 个人中心

### 7.1 获取个人信息
`GET /api/personal.php`

**请求头:** `Authorization: Bearer <token>`

### 7.2 更新个人信息
`POST /api/personal.php?action=update`

**请求参数 (按需提供):**
| 参数 | 类型 | 说明 |
|------|------|------|
| phone | string | 手机号 |
| title | string | 职称（教师） |
| college | string | 学院 |
| major | string | 专业（学生） |
| organization | string | 单位（校外） |

### 7.3 修改密码
`POST /api/personal.php?action=change_password`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| old_password | string | ✓ | 原密码 |
| new_password | string | ✓ | 新密码（≥6位） |

---

## 8. 教师审批（教师专用）

### 8.1 待审批列表
`GET /api/approval.php`

**请求头:** `Authorization: Bearer <token>`（需教师账号）

### 8.2 批准预约
`POST /api/approval.php?action=approve`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| reservation_id | int | ✓ | 预约ID |

### 8.3 驳回预约
`POST /api/approval.php?action=reject`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| reservation_id | int | ✓ | 预约ID |
| reason | string | ✓ | 驳回原因 |

---

## 安全说明

1. **密码加密**: 所有密码使用 PHP `password_hash()` (bcrypt) 加密存储
2. **Token 有效期**: 7天
3. **Token 失效**: 修改密码后所有 Token 自动失效
