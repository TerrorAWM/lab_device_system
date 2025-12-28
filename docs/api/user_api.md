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

**响应字段（通用）:**
| 字段 | 类型 | 说明 |
|------|------|------|
| user_id | int | 用户ID |
| username | string | 用户名 |
| real_name | string | 真实姓名 |
| user_type | string | 用户类型: `teacher` / `student` / `external` / `device` |
| phone | string | 手机号（可为null） |
| created_at | string | 注册时间 |
| reservation_count | int | 预约总数 |
| borrow_count | int | 借用总数 |

**学生用户额外字段:**
| 字段 | 类型 | 说明 |
|------|------|------|
| student_no | string | 学号 |
| major | string | 专业 |
| college | string | 学院 |
| advisor_id | int | 导师用户ID（可为null） |
| advisor_name | string | 导师姓名（可为null） |
| advisor_phone | string | **导师电话**（可为null） |

**教师用户额外字段:**
| 字段 | 类型 | 说明 |
|------|------|------|
| title | string | 职称 |
| college | string | 学院 |
| research_area | string | 研究方向 |

**校外人员额外字段:**
| 字段 | 类型 | 说明 |
|------|------|------|
| organization | string | 所属单位 |
| identity_card | string | 身份证号 |

**学生用户响应示例:**
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "user_id": 3,
    "username": "李四",
    "real_name": "李四",
    "user_type": "student",
    "phone": null,
    "created_at": "2025-12-28 10:00:00",
    "student_no": "S2024001",
    "major": "软件工程",
    "college": "物联网工程学院",
    "advisor_id": 1,
    "advisor_name": "张三",
    "advisor_phone": "13800138000",
    "reservation_count": 5,
    "borrow_count": 3
  }
}
```

**教师用户响应示例:**
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "user_id": 1,
    "username": "张三",
    "real_name": "张三",
    "user_type": "teacher",
    "phone": "13800138000",
    "created_at": "2025-12-28 10:00:00",
    "title": "教授",
    "college": "物联网工程学院",
    "research_area": "嵌入式系统",
    "reservation_count": 10,
    "borrow_count": 8
  }
}
```

---

### 7.2 更新个人信息
`POST /api/personal.php?action=update`

**请求头:** `Authorization: Bearer <token>`

**请求参数 (按需提供):**
| 参数 | 类型 | 说明 |
|------|------|------|
| phone | string | 手机号（所有用户） |
| title | string | 职称（仅教师） |
| college | string | 学院（教师/学生） |
| research_area | string | 研究方向（仅教师） |
| major | string | 专业（仅学生） |
| organization | string | 单位（仅校外） |
| identity_card | string | 身份证号（仅校外） |

**响应示例:**
```json
{
  "code": 0,
  "message": "个人信息更新成功",
  "data": null
}
```

---

### 7.3 修改密码
`POST /api/personal.php?action=change_password`

**请求头:** `Authorization: Bearer <token>`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| old_password | string | ✓ | 原密码 |
| new_password | string | ✓ | 新密码（≥6位） |

**响应示例:**
```json
{
  "code": 0,
  "message": "密码修改成功，请重新登录",
  "data": null
}
```

> ⚠️ **注意**: 修改密码后，所有已登录的 Token 会自动失效，需要重新登录。

---

## 8. 教师审批（教师专用）

### 8.1 待审批列表
`GET /api/approval.php`

**请求头:** `Authorization: Bearer <token>`（需教师账号）

**响应字段:**
| 字段 | 类型 | 说明 |
|------|------|------|
| reservation_id | int | 预约ID |
| user_id | int | 申请学生ID |
| student_name | string | 学生姓名 |
| student_no | string | 学号 |
| device_name | string | 设备名称 |
| reserve_date | string | 预约日期 |
| time_slot | string | 时段 |
| purpose | string | 使用目的 |
| status | int | 状态 |
| current_step | int | 当前审批步骤 |
| created_at | string | 申请时间 |

---

### 8.2 审批历史
`GET /api/approval.php?action=history`

**请求头:** `Authorization: Bearer <token>`（需教师账号）

**响应字段:**
| 字段 | 类型 | 说明 |
|------|------|------|
| log_id | int | 日志ID |
| reservation_id | int | 预约ID |
| device_name | string | 设备名称 |
| reserve_date | string | 预约日期 |
| time_slot | string | 时段 |
| action | string | 操作: `approve` / `reject` |
| reason | string | 驳回原因（仅驳回时有） |
| created_at | string | 审批时间 |

**响应示例:**
```json
{
  "code": 0,
  "data": {
    "items": [
      {
        "log_id": 1,
        "reservation_id": 5,
        "device_name": "示波器 A-001",
        "reserve_date": "2025-12-30",
        "time_slot": "14:00-16:00",
        "action": "approve",
        "reason": null,
        "created_at": "2025-12-28 10:00:00"
      }
    ]
  }
}
```

---

### 8.3 批准预约
`POST /api/approval.php?action=approve`

**请求头:** `Authorization: Bearer <token>`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| reservation_id | int | ✓ | 预约ID |

**响应示例:**
```json
{
  "code": 0,
  "message": "审批成功",
  "data": {
    "reservation_id": 5,
    "new_step": 2
  }
}
```

---

### 8.4 驳回预约
`POST /api/approval.php?action=reject`

**请求头:** `Authorization: Bearer <token>`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| reservation_id | int | ✓ | 预约ID |
| reason | string | ✓ | 驳回原因 |

**响应示例:**
```json
{
  "code": 0,
  "message": "已驳回",
  "data": null
}
```

---

## 9. 导师学生管理（教师专用）

> 教师用户可通过此模块管理名下学生，支持添加、导入、移除学生。

### 9.1 获取学生列表
`GET /api/student.php`

**请求头:** `Authorization: Bearer <token>`（需教师账号）

**响应字段:**
| 字段 | 类型 | 说明 |
|------|------|------|
| user_id | int | 学生用户ID |
| username | string | 用户名 |
| real_name | string | 真实姓名 |
| student_no | string | 学号 |
| major | string | 专业 |
| college | string | 学院 |
| phone | string | 手机号 |
| created_at | string | 注册时间 |

**响应示例:**
```json
{
  "code": 0,
  "data": {
    "items": [
      {
        "user_id": 3,
        "username": "李四",
        "real_name": "李四",
        "student_no": "S2024001",
        "major": "软件工程",
        "college": "物联网工程学院",
        "phone": null,
        "created_at": "2025-12-28 10:00:00"
      }
    ],
    "total": 1
  }
}
```

---

### 9.2 添加单个学生
`POST /api/student.php`

**请求头:** `Authorization: Bearer <token>`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| student_no | string | ✓ | 学号 |
| real_name | string | ✓ | 学生姓名 |
| major | string | - | 专业 |
| college | string | - | 学院 |
| phone | string | - | 手机号 |

**响应示例:**
```json
{
  "code": 0,
  "message": "学生添加成功",
  "data": {
    "user_id": 10,
    "student_no": "S2024010"
  }
}
```

> 💡 **说明**: 如果学号已存在于系统中，将直接绑定为当前导师的学生；如果不存在，将自动创建账号（默认密码为学号）。

---

### 9.3 批量导入学生（文件上传）
`POST /api/student.php?action=import`

**请求头:** 
- `Authorization: Bearer <token>`
- `Content-Type: multipart/form-data`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| file | file | ✓ | CSV/TXT 文件 |

**文件格式（CSV/制表符分隔）:**
```
学号,姓名,专业,学院,手机号
S2024001,张三,软件工程,物联网学院,13800138001
S2024002,李四,计算机科学,物联网学院,13800138002
```

**响应示例:**
```json
{
  "code": 0,
  "message": "导入完成",
  "data": {
    "success": 5,
    "failed": 1,
    "errors": ["行3: 学号 S2024003 格式非法"]
  }
}
```

---

### 9.4 批量导入学生（JSON格式）
`POST /api/student.php?action=import_json`

**请求头:** `Authorization: Bearer <token>`

**请求参数:**
```json
{
  "students": [
    {
      "student_no": "S2024001",
      "real_name": "张三",
      "major": "软件工程",
      "college": "物联网学院",
      "phone": "13800138001"
    }
  ]
}
```

---

### 9.5 移除学生
`POST /api/student.php?action=remove`

**请求头:** `Authorization: Bearer <token>`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| user_id | int | ✓ | 学生用户ID |

**响应示例:**
```json
{
  "code": 0,
  "message": "已移除该学生",
  "data": null
}
```

> ⚠️ **注意**: 此操作仅解除学生与导师的绑定关系，不会删除学生账号。

---

## 安全说明

1. **密码加密**: 所有密码使用 PHP `password_hash()` (bcrypt) 加密存储
2. **Token 有效期**: 7天
3. **Token 失效**: 修改密码后所有 Token 自动失效
4. **教师专用接口**: 第8、9章节接口需要 `user_type = teacher` 的账号
