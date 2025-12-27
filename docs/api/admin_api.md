# 管理端 API 使用文档

> 实验室设备管理系统 - Admin API Reference  
> Base URL: `/admin/api`

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

### 管理员角色
| 角色 | 权限说明 |
|------|----------|
| supervisor | 系统负责人，拥有全部权限 |
| device | 设备管理员，可管理设备和借用 |
| finance | 财务管理员，可管理收费 |

---

## 1. 认证模块

### 1.1 管理员登录
`POST /admin/api/login.php`

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
    "admin": {
      "id": 1,
      "username": "supervisor",
      "real_name": "系统管理员",
      "role": "supervisor"
    }
  }
}
```

---

### 1.2 管理员退出
`POST /admin/api/login.php?action=logout`

**请求头:** `Authorization: Bearer <token>`

---

### 1.3 管理员注册（开发期）
`POST /admin/api/register.php`

> ⚠️ 此接口仅限开发环境使用

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| username | string | ✓ | 用户名 |
| password | string | ✓ | 密码（≥6位，bcrypt加密存储） |
| real_name | string | ✓ | 真实姓名 |
| role | string | - | 角色: `supervisor` / `device` / `finance` |
| phone | string | - | 手机号 |

---

## 2. 统计报表

### 2.1 仪表盘数据
`GET /admin/api/stats.php?action=dashboard`

**返回数据:**
- 今日预约数、借用数、待归还数
- 设备统计（总数、可用、借出、维护）
- 用户统计（总数、各类型数量）
- 待处理事项数量

---

### 2.2 设备使用统计
`GET /admin/api/stats.php?action=device_usage`

**查询参数:**
| 参数 | 类型 | 说明 |
|------|------|------|
| period | string | `week` / `month` / `year` |
| start_date | string | 开始日期 |
| end_date | string | 结束日期 |

---

### 2.3 收入统计
`GET /admin/api/stats.php?action=revenue`

**查询参数:** 同上

---

## 3. 设备管理

### 3.1 设备列表
`GET /admin/api/device.php`

**查询参数:**
| 参数 | 类型 | 说明 |
|------|------|------|
| keyword | string | 搜索关键词 |
| category | string | 设备类别 |
| status | int | 状态码 |
| page | int | 页码 |
| page_size | int | 每页数量 |

---

### 3.2 设备详情
`GET /admin/api/device.php?id=<device_id>`

---

### 3.3 新增设备
`POST /admin/api/device.php?action=create`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| device_name | string | ✓ | 设备名称 |
| model | string | ✓ | 型号 |
| manufacturer | string | - | 生产厂商 |
| category | string | - | 类别 |
| location | string | - | 存放位置 |
| price | float | - | 设备价值 |
| rent_price | float | - | 租金单价 |

---

### 3.4 更新设备
`POST /admin/api/device.php?action=update`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| device_id | int | ✓ | 设备ID |
| device_name | string | - | 设备名称 |
| ... | ... | - | 其他可更新字段 |

---

### 3.5 更新设备状态
`POST /admin/api/device.php?action=update_status`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| device_id | int | ✓ | 设备ID |
| status | int | ✓ | 状态: 1=可用, 3=维护, 4=报废 |

---

### 3.6 删除设备
`POST /admin/api/device.php?action=delete`

> ⚠️ 需 supervisor 权限

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| device_id | int | ✓ | 设备ID |

---

## 4. 预约审批

### 4.1 预约列表
`GET /admin/api/reservation.php`

**查询参数:**
| 参数 | 类型 | 说明 |
|------|------|------|
| status | int | 0=待审批, 1=已批准, 2=已驳回, 3=已取消 |
| user_id | int | 用户ID |
| device_id | int | 设备ID |

---

### 4.2 预约详情
`GET /admin/api/reservation.php?id=<reservation_id>`

---

### 4.3 批准预约
`POST /admin/api/reservation.php?action=approve`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| reservation_id | int | ✓ | 预约ID |
| remark | string | - | 备注 |

**操作效果:**
- 预约状态 → `approved`
- 自动创建借用记录
- 设备状态 → `借出`

---

### 4.4 驳回预约
`POST /admin/api/reservation.php?action=reject`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| reservation_id | int | ✓ | 预约ID |
| reason | string | ✓ | 驳回原因 |

---

## 5. 借用管理

### 5.1 借用列表
`GET /admin/api/borrow.php`

**查询参数:**
| 参数 | 类型 | 说明 |
|------|------|------|
| status | int | 1=待发放, 2=已发放, 3=已归还 |
| user_id | int | 用户ID |

---

### 5.2 借用详情
`GET /admin/api/borrow.php?id=<borrow_id>`

---

### 5.3 发放设备
`POST /admin/api/borrow.php?action=dispatch`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| reservation_id | int | ✓ | 预约ID |

---

### 5.4 确认归还
`POST /admin/api/borrow.php?action=confirm_return`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| borrow_id | int | ✓ | 借用记录ID |
| device_condition | string | - | 设备状态: `good` / `damaged` |

**操作效果:**
- 借用状态 → `已归还`
- 设备状态 → `可用` 或 `维护` (损坏时)
- 预约状态 → `completed`

---

## 6. 收费管理

### 6.1 订单列表
`GET /admin/api/payment.php`

**查询参数:**
| 参数 | 类型 | 说明 |
|------|------|------|
| status | int | 0=待支付, 1=已支付 |
| user_id | int | 用户ID |

---

### 6.2 订单详情
`GET /admin/api/payment.php?id=<payment_id>`

---

### 6.3 创建收费单
`POST /admin/api/payment.php?action=create`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| user_id | int | ✓ | 用户ID |
| amount | float | ✓ | 金额 |
| description | string | - | 费用描述 |
| borrow_id | int | - | 关联借用ID |

---

### 6.4 标记已支付
`POST /admin/api/payment.php?action=mark_paid`

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| payment_id | int | ✓ | 订单ID |

---

## 7. 用户管理

### 7.1 用户列表
`GET /admin/api/user.php`

**查询参数:**
| 参数 | 类型 | 说明 |
|------|------|------|
| keyword | string | 搜索关键词 |
| user_type | string | 用户类型 |
| status | int | 0=禁用, 1=正常 |

---

### 7.2 用户详情
`GET /admin/api/user.php?id=<user_id>`

---

### 7.3 禁用/启用用户
`POST /admin/api/user.php?action=toggle_status`

> ⚠️ 需 supervisor 权限

**请求参数:**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| user_id | int | ✓ | 用户ID |
| status | int | ✓ | 0=禁用, 1=启用 |

**禁用效果:**
- 用户状态 → `禁用`
- 清除所有登录 Token

---

## 安全说明

1. **密码加密**: 所有密码使用 PHP `password_hash()` (bcrypt) 加密存储
2. **Token 有效期**: 7天
3. **权限控制**: 部分敏感操作需 supervisor 角色
4. **操作日志**: 重要操作记录管理员信息和时间戳
