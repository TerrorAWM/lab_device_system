# API文档缺失项清单

## 用户端 API (`/api`)

### 1. 用户登录 (`POST /api/login.php`)
**缺失响应字段:**
- `user` 对象中缺少扩展字段：
  - 学生：`student_no`, `major`, `college`, `advisor_id`, `advisor_name`
  - 教师：`title`, `college`, `research_area`
  - 校外：`organization`, `identity_card`

### 2. 设备列表 (`GET /api/device.php`)
**缺失查询参数:** 无（已有）
**缺失响应字段:**
- `status_code` (int)
- `image_url` (string)
- `purpose` (string)
- `pagination` 对象

### 3. 设备详情 (`GET /api/device.php?id=X`)
**缺失响应字段:**
- `status_code` (int)
- `purchase_date` (string)
- `occupied_slots` (array) - 仅已登录用户可见

### 4. 预约列表 (`GET /api/reservation.php`)
**缺失查询参数:**
- `status` (int)
- `page` (int)
- `page_size` (int)

**缺失响应字段:**
- `status_code` (int)
- `model`, `location` 字段
- `pagination` 对象

### 5. 创建预约 (`POST /api/reservation.php`)
**缺失响应字段:**
- `payment` 对象（自动创建的支付订单）

**缺失功能说明:**
- 自动创建支付订单
- 校内用户金额为0且自动已支付
- 校外人员需要支付租金

### 6. 修改预约 (`POST /api/reservation.php?action=update`)
**缺失说明:**
- 所有参数都是可选的
- 只能修改待审核状态（status=0）的预约
- 修改时会自动检查冲突

### 7. 取消预约 (`POST /api/reservation.php?action=cancel`)
**缺失请求参数:**
- `reason` (string) - 可选

**缺失功能说明:**
- 只有待审核或已批准状态才能取消
- 会同时取消关联的支付订单

### 8. 借用记录列表 (`GET /api/borrow.php`)
**缺失查询参数:**
- `status` (int)
- `page` (int)
- `page_size` (int)

**缺失响应字段:**
- `status_code` (int)
- `model`, `location`, `reserve_date` 字段
- `pagination` 对象

### 9. 借用详情 (`GET /api/borrow.php?id=X`)
**缺失响应字段:**
- `device` 对象（嵌套）
- `purpose` (string)
- `rent_price` (float)
- `status_code` (int)

### 10. 申请归还 (`POST /api/return.php`)
**缺失请求参数:**
- `remark` (string) - 可选

**缺失响应字段:**
- `return_time` (string)
- `status` (string)

### 11. 支付记录列表 (`GET /api/payment.php`)
**缺失查询参数:**
- `status` (int)
- `page` (int)
- `page_size` (int)

**缺失响应字段:**
- `status_code` (int)
- `device_name` (string)
- `pagination` 对象

### 12. 待支付列表 (`GET /api/payment.php?action=pending`)
**缺失响应字段:**
- `device_name` (string)

### 13. 发起支付 (`POST /api/payment.php?action=pay`)
**缺失说明:**
- `pay_method` 默认值为 `"wechat"`

**缺失响应字段:**
- `pay_url` (string)
- `message` (string)

### 14. 待审批列表 (`GET /api/approval.php`)
**缺失响应字段:**
- `stats` 对象（统计信息）
- `device_id`, `model`, `location` 字段
- 字段名：文档写 `reservation_id`，实际返回 `id`

### 15. 批准预约 (`POST /api/approval.php?action=approve`)
**缺失请求参数:**
- `remark` (string) - 可选

**缺失响应字段说明:**
- 可能返回 `next_step`, `next_role`, `next_description`（有下一步）
- 可能返回 `status: "approved"`（审批完成）

### 16. 获取学生列表 (`GET /api/student.php`)
**缺失响应字段:**
- `status` (int)

### 17. 添加单个学生 (`POST /api/student.php`)
**缺失响应字段:**
- `username` (string)
- `real_name` (string)

**缺失功能说明:**
- 自动创建账号，默认密码 `123456`
- 用户名使用学号

### 18. 批量导入学生 (`POST /api/student.php?action=import`)
**缺失响应字段:**
- `total` (int)
- `imported` (array)
- `parse_errors` (array)

### 19. 批量导入学生JSON (`POST /api/student.php?action=import_json`)
**⚠️ 接口差异:**
- 文档要求 `action=import_json`
- 实际使用 `action=import` + `Content-Type: application/json`

---

## 管理端 API (`/admin/api`)

### 1. 仪表盘数据 (`GET /admin/api/stats.php?action=dashboard`)
**缺失:** 完整响应示例

### 2. 设备使用统计 (`GET /admin/api/stats.php?action=device_usage`)
**缺失响应字段:**
- `period`, `start_date`, `end_date`
- `top_devices`, `by_category`, `daily_stats`

### 3. 收入统计 (`GET /admin/api/stats.php?action=revenue`)
**缺失响应字段:**
- `period`, `start_date`, `end_date`
- `pending_amount`, `by_user_type`, `daily_revenue`

### 4. 设备列表 (`GET /admin/api/device.php`)
**缺失响应字段:**
- `status_code` (int)
- `current_borrower` (object)
- `pagination` 对象

### 5. 设备详情 (`GET /admin/api/device.php?id=X`)
**缺失响应字段:**
- `status_code` (int)
- `image_url` (string)
- `purpose` (string)
- `total_borrows` (int)
- `current_borrower` (object) - 详细信息

### 6. 新增设备 (`POST /admin/api/device.php?action=create`)
**缺失请求参数:**
- `purpose` (string) - 可选
- `purchase_date` (string) - 可选

### 7. 更新设备 (`POST /admin/api/device.php?action=update`)
**缺失说明:**
- 可更新字段列表：`device_name`, `model`, `manufacturer`, `category`, `location`, `price`, `rent_price`, `purpose`, `purchase_date`, `image_url`

### 8. 更新设备状态 (`POST /admin/api/device.php?action=update_status`)
**缺失功能说明:**
- 借用中的设备不允许直接修改状态（除非改为2）
- 需要先归还才能修改

### 9. 删除设备 (`POST /admin/api/device.php?action=delete`)
**缺失功能说明:**
- 只能删除报废状态（status=4）的设备
- 有未完成借用时无法删除

### 10. 预约列表 (`GET /admin/api/reservation.php`)
**缺失查询参数:**
- `page` (int)
- `page_size` (int)

**缺失响应字段:**
- `status_code` (int)
- `current_step` (int)
- `current_step_info` (array)
- `user_phone` (string)
- `pagination` 对象

### 11. ⚠️ 待审批列表 (`GET /admin/api/reservation.php?action=pending`)
**完全缺失此接口！**

**需要补充:**
- 接口说明
- 响应字段：`items`, `count`, `admin_role`
- 每个预约项：`is_parallel`, `is_payment_required`, `step_description`

### 12. 预约详情 (`GET /admin/api/reservation.php?id=X`)
**缺失响应字段:**
- `user` 对象（嵌套）
- `device` 对象（嵌套）
- `current_step` (int)
- `approvals` (object)
- `approval_logs` (array)
- `workflow` (array)

### 13. 批准预约 (`POST /admin/api/reservation.php?action=approve`)
**缺失响应字段说明:**
- 三种情况：
  1. 有下一步：`next_step`, `next_role`, `next_description`, `payment_required`
  2. 审批完成：`status: "approved"`
  3. 并行审批部分完成：`status: "partial_approved"`, `pending_approvals`

**缺失功能说明:**
- 支持并行审批
- 校外人员财务审批需检查付款状态
- 审批完成后自动创建借用记录

### 14. 驳回预约 (`POST /admin/api/reservation.php?action=reject`)
**缺失响应字段:**
- `status` (string)

### 15. 借用列表 (`GET /admin/api/borrow.php`)
**缺失查询参数:**
- `device_id` (int)
- `page` (int)
- `page_size` (int)

**缺失响应字段:**
- `status_code` (int)
- `pagination` 对象

### 16. 借用详情 (`GET /admin/api/borrow.php?id=X`)
**缺失响应字段:**
- `user` 对象（嵌套）
- `device` 对象（嵌套）
- `purpose` (string)
- `status_code` (int)

### 17. 发放设备 (`POST /admin/api/borrow.php?action=dispatch`)
**缺失响应字段:**
- `borrow_id` (int)

### 18. 确认归还 (`POST /admin/api/borrow.php?action=confirm_return`)
**缺失响应字段:**
- `status` (string)
- `device_condition` (string)

### 19. 订单列表 (`GET /admin/api/payment.php`)
**缺失查询参数:**
- `page` (int)
- `page_size` (int)

**缺失响应字段:**
- `status_code` (int)
- `pagination` 对象

### 20. 订单详情 (`GET /admin/api/payment.php?id=X`)
**缺失响应字段:**
- `user` 对象（嵌套）
- `reserve_date` (string)
- `time_slot` (string)
- `status_code` (int)

### 21. 创建收费单 (`POST /admin/api/payment.php?action=create`)
**缺失说明:**
- `reservation_id` 为可选（可为null）

**缺失响应字段:**
- `payment_id` (int)

### 22. 标记已支付 (`POST /admin/api/payment.php?action=mark_paid`)
**缺失响应字段:**
- `status` (string)
- `pay_time` (string)

### 23. 用户列表 (`GET /admin/api/user.php`)
**缺失查询参数:**
- `page` (int)
- `page_size` (int)

**缺失响应字段:**
- `status` (string)
- `status_code` (int)
- `pagination` 对象

### 24. 用户详情 (`GET /admin/api/user.php?id=X`)
**缺失响应字段:**
- 根据用户类型的扩展信息
- `reservation_count` (int)
- `borrow_count` (int)
- `status` (string)
- `status_code` (int)

### 25. 禁用/启用用户 (`POST /admin/api/user.php?action=toggle_status`)
**缺失响应字段:**
- `status` (string)

---

## 总结

### 最严重的缺失
1. **管理端待审批列表接口** - 完全缺失
2. **所有接口的 `status_code` 字段** - 普遍缺失
3. **所有列表接口的分页参数和响应** - 普遍缺失
4. **创建预约自动创建支付订单功能** - 功能说明缺失
5. **并行审批功能** - 功能说明缺失

### 缺失统计
- 查询参数缺失：15+ 处
- 请求参数缺失：8+ 处
- 响应字段缺失：50+ 处
- 功能说明缺失：10+ 处
- 完整接口缺失：1 处


