<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户侧 API 测试台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .main-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border-top: 4px solid #0d6efd; }
        .api-section { background: #ffffff; border: 1px solid #e9ecef; border-radius: 8px; margin-bottom: 1rem; overflow: hidden; }
        .api-section h5 { background: #f8f9fa; color: #0d6efd; padding: 12px 20px; border-bottom: 1px solid #e9ecef; margin: 0; font-weight: 600; font-size: 1rem; }
        .api-btn { margin: 4px; }
        .response-area { background: #212529; color: #f8f9fa; border-radius: 6px; font-family: 'Consolas', 'Monaco', monospace; font-size: 13px; max-height: 600px; overflow-y: auto; border: 1px solid #dee2e6; }
        .token-display { background: #e7f1ff; color: #0c5460; border-radius: 6px; word-break: break-all; padding: 10px; border: 1px solid #b8daff; }
        .status-badge { font-size: 0.75rem; }
        pre { margin: 0; white-space: pre-wrap; }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="main-card p-4">
            <div class="text-center mb-4">
                <h2><i class="fas fa-flask text-primary me-2"></i>用户侧 API 测试台</h2>
                <p class="text-muted">实验室设备管理系统 - User API Tester</p>
            </div>
            
            <!-- Token 状态 -->
            <div class="alert alert-info d-flex align-items-center mb-4" id="tokenStatus">
                <i class="fas fa-info-circle me-2"></i>
                <span>尚未登录，请先登录获取 Token</span>
            </div>

            <div class="row">
                <!-- 左侧：API 操作 -->
                <div class="col-lg-6">
                    <!-- 认证模块 -->
                    <div class="api-section">
                        <h5><i class="fas fa-key me-2"></i>认证模块</h5>
                        <div class="p-3">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <input type="text" class="form-control" id="username" placeholder="用户名" value="张三">
                                </div>
                                <div class="col-6">
                                    <input type="password" class="form-control" id="password" placeholder="密码" value="123456">
                                </div>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-4">
                                    <input type="text" class="form-control form-control-sm" id="regRealName" placeholder="真实姓名" value="测试用户">
                                </div>
                                <div class="col-4">
                                    <select class="form-control form-control-sm" id="regUserType">
                                        <option value="student">学生</option>
                                        <option value="teacher">教师</option>
                                        <option value="external">校外人员</option>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <input type="text" class="form-control form-control-sm" id="regExtra" placeholder="学号/工号" value="S001">
                                </div>
                            </div>
                            <button class="btn btn-primary api-btn" onclick="login()"><i class="fas fa-sign-in-alt me-1"></i>登录</button>
                            <button class="btn btn-outline-danger api-btn" onclick="logout()"><i class="fas fa-sign-out-alt me-1"></i>退出</button>
                            <button class="btn btn-outline-secondary api-btn" onclick="testRegister()"><i class="fas fa-user-plus me-1"></i>注册</button>
                        </div>
                    </div>

                    <!-- 设备模块 -->
                    <div class="api-section">
                        <h5><i class="fas fa-desktop me-2"></i>设备模块</h5>
                        <div class="p-3">
                            <div class="row g-2 mb-2">
                                <div class="col-4"><input type="text" class="form-control form-control-sm" id="devKeyword" placeholder="搜索关键词"></div>
                                <div class="col-4"><input type="text" class="form-control form-control-sm" id="devCategory" placeholder="类别"></div>
                                <div class="col-4">
                                    <select class="form-control form-control-sm" id="devStatus">
                                        <option value="">所有状态</option>
                                        <option value="1">可用</option>
                                        <option value="2">借出</option>
                                        <option value="3">维护</option>
                                    </select>
                                </div>
                            </div>
                            <button class="btn btn-success api-btn" onclick="getDevices()"><i class="fas fa-list me-1"></i>设备列表</button>
                            <button class="btn btn-info api-btn" onclick="getDeviceDetail()"><i class="fas fa-info-circle me-1"></i>详情</button>
                            <button class="btn btn-secondary api-btn" onclick="getCategories()"><i class="fas fa-tags me-1"></i>设备类别</button>
                            <div class="mt-2">
                                <input type="number" class="form-control form-control-sm d-inline-block" id="deviceId" placeholder="设备ID" value="1" style="width:100px">
                            </div>
                        </div>
                    </div>

                    <!-- 预约模块 -->
                    <div class="api-section">
                        <h5><i class="fas fa-calendar-check me-2"></i>预约模块</h5>
                        <div class="p-3">
                            <div class="row g-2 mt-2">
                                <div class="col-4"><input type="date" class="form-control form-control-sm" id="reserveDate"></div>
                                <div class="col-4">
                                    <select class="form-control form-control-sm" id="timeSlot">
                                        <option value="08:00-10:00">08:00-10:00</option>
                                        <option value="10:00-12:00">10:00-12:00</option>
                                        <option value="14:00-16:00">14:00-16:00</option>
                                        <option value="16:00-18:00">16:00-18:00</option>
                                        <option value="19:00-21:00">19:00-21:00</option>
                                    </select>
                                </div>
                                <div class="col-4"><input type="number" class="form-control form-control-sm" id="reservationId" placeholder="预约ID" value="1"></div>
                                <div class="col-12"><input type="text" class="form-control form-control-sm" id="reservePurpose" placeholder="预约用途" value="实验测试"></div>
                            </div>
                            <div class="mt-2">
                                <button class="btn btn-primary api-btn" onclick="createReservation()"><i class="fas fa-plus me-1"></i>创建预约</button>
                                <button class="btn btn-success api-btn" onclick="getReservations()"><i class="fas fa-list me-1"></i>我的预约</button>
                                <button class="btn btn-warning api-btn" onclick="updateReservation()"><i class="fas fa-edit me-1"></i>修改预约</button>
                                <button class="btn btn-danger api-btn" onclick="cancelReservation()"><i class="fas fa-times me-1"></i>取消预约</button>
                            </div>
                        </div>
                    </div>

                    <!-- 借用模块 -->
                    <div class="api-section">
                        <h5><i class="fas fa-hand-holding me-2"></i>借用模块</h5>
                        <div class="p-3">
                            <button class="btn btn-success api-btn" onclick="getBorrows()"><i class="fas fa-list me-1"></i>借用记录</button>
                            <button class="btn btn-info api-btn" onclick="getBorrowDetail()"><i class="fas fa-info-circle me-1"></i>借用详情</button>
                            <button class="btn btn-warning api-btn" onclick="returnDevice()"><i class="fas fa-undo me-1"></i>归还设备</button>
                            <div class="mt-2">
                                <input type="number" class="form-control form-control-sm d-inline-block" id="borrowId" placeholder="借用ID" value="1" style="width:100px">
                            </div>
                        </div>
                    </div>

                    <!-- 缴费模块 -->
                    <div class="api-section">
                        <h5><i class="fas fa-credit-card me-2"></i>缴费模块</h5>
                        <div class="p-3">
                            <div class="row g-2 mb-2">
                                <div class="col-6"><input type="number" class="form-control form-control-sm" id="payId" placeholder="支付ID"></div>
                                <div class="col-6"><input type="text" class="form-control form-control-sm" id="payOrderNo" placeholder="订单号"></div>
                            </div>
                            <button class="btn btn-success api-btn" onclick="getPayments()"><i class="fas fa-list me-1"></i>支付记录</button>
                            <button class="btn btn-warning api-btn" onclick="getPendingPayments()"><i class="fas fa-clock me-1"></i>待支付</button>
                            <button class="btn btn-primary api-btn" onclick="payOrder()"><i class="fas fa-money-bill me-1"></i>发起支付</button>
                            <button class="btn btn-info api-btn" onclick="confirmPayment()"><i class="fas fa-check me-1"></i>确认支付</button>
                        </div>
                    </div>

                    <!-- 个人中心 -->
                    <div class="api-section">
                        <h5><i class="fas fa-user-circle me-2"></i>个人中心</h5>
                        <div class="p-3">
                            <div class="row g-2 mb-2">
                                <div class="col-12"><input type="text" class="form-control form-control-sm" id="userPhone" placeholder="新手机号" value="13800138000"></div>
                                <div class="col-6"><input type="password" class="form-control form-control-sm" id="oldPwd" placeholder="旧密码"></div>
                                <div class="col-6"><input type="password" class="form-control form-control-sm" id="newPwd" placeholder="新密码"></div>
                            </div>
                            <button class="btn btn-success api-btn" onclick="getPersonal()"><i class="fas fa-user me-1"></i>获取信息</button>
                            <button class="btn btn-primary api-btn" onclick="updatePersonal()"><i class="fas fa-save me-1"></i>更新信息</button>
                            <button class="btn btn-warning api-btn" onclick="changePassword()"><i class="fas fa-key me-1"></i>修改密码</button>
                        </div>
                    </div>

                    <!-- 教师审批 -->
                    <div class="api-section">
                        <h5><i class="fas fa-user-graduate me-2"></i>教师审批 (需教师账号)</h5>
                        <div class="p-3">
                            <div class="row g-2 mb-2">
                                <div class="col-6"><input type="number" class="form-control form-control-sm" id="approvalResId" placeholder="预约ID"></div>
                                <div class="col-6"><input type="text" class="form-control form-control-sm" id="rejectReason" placeholder="驳回原因"></div>
                            </div>
                            <button class="btn btn-success api-btn" onclick="getApprovals()"><i class="fas fa-list me-1"></i>待审批列表</button>
                            <button class="btn btn-primary api-btn" onclick="approveStudent()"><i class="fas fa-check me-1"></i>批准</button>
                            <button class="btn btn-danger api-btn" onclick="rejectStudent()"><i class="fas fa-times me-1"></i>驳回</button>
                            <button class="btn btn-secondary api-btn" onclick="getApprovalHistory()"><i class="fas fa-history me-1"></i>审批历史</button>
                        </div>
                    </div>

                    <!-- 学生管理 -->
                    <div class="api-section">
                        <h5><i class="fas fa-users me-2"></i>学生管理 (需教师账号)</h5>
                        <div class="p-3">
                            <div class="row g-2 mb-2">
                                <div class="col-4"><input type="text" class="form-control form-control-sm" id="stuNo" placeholder="学号" value="S2025001"></div>
                                <div class="col-4"><input type="text" class="form-control form-control-sm" id="stuName" placeholder="姓名" value="测试学生"></div>
                                <div class="col-4"><input type="text" class="form-control form-control-sm" id="stuMajor" placeholder="专业"></div>
                                <div class="col-6"><input type="text" class="form-control form-control-sm" id="stuCollege" placeholder="学院"></div>
                                <div class="col-6"><input type="text" class="form-control form-control-sm" id="stuPhone" placeholder="手机号"></div>
                            </div>
                            <button class="btn btn-success api-btn" onclick="getStudents()"><i class="fas fa-list me-1"></i>学生列表</button>
                            <button class="btn btn-primary api-btn" onclick="addStudent()"><i class="fas fa-plus me-1"></i>添加学生</button>
                            <button class="btn btn-info api-btn" onclick="batchImportStudents()"><i class="fas fa-file-import me-1"></i>批量导入(JSON)</button>
                            <button class="btn btn-warning api-btn" onclick="removeStudent()"><i class="fas fa-unlink me-1"></i>解绑学生</button>
                            <div class="mt-2">
                                <input type="number" class="form-control form-control-sm d-inline-block" id="stuUserId" placeholder="学生用户ID" style="width:120px">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 右侧：响应结果 -->
                <div class="col-lg-6">
                    <div class="sticky-top" style="top: 20px;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="mb-0"><i class="fas fa-terminal me-2"></i>响应结果</h5>
                            <button class="btn btn-sm btn-outline-secondary" onclick="clearResponse()"><i class="fas fa-trash me-1"></i>清空</button>
                        </div>
                        <div class="response-area p-3" id="responseArea" style="min-height: 600px;">
                            <pre id="responseContent">// API 响应将显示在这里...</pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '.';
        let token = localStorage.getItem('user_token') || '';
        
        // 初始化日期
        document.getElementById('reserveDate').value = new Date(Date.now() + 86400000).toISOString().split('T')[0];
        
        // 更新 Token 状态
        function updateTokenStatus() {
            const el = document.getElementById('tokenStatus');
            if (token) {
                el.className = 'alert alert-success d-flex align-items-center mb-4';
                el.innerHTML = '<i class="fas fa-check-circle me-2"></i><span>已登录，Token: ' + token.substring(0, 20) + '...</span>';
            } else {
                el.className = 'alert alert-info d-flex align-items-center mb-4';
                el.innerHTML = '<i class="fas fa-info-circle me-2"></i><span>尚未登录，请先登录获取 Token</span>';
            }
        }
        updateTokenStatus();

        // API 请求封装
        async function apiRequest(method, endpoint, data = null, requireAuth = true) {
            const options = {
                method: method,
                headers: { 'Content-Type': 'application/json' }
            };
            if (requireAuth && token) {
                options.headers['Authorization'] = 'Bearer ' + token;
            }
            if (data) {
                options.body = JSON.stringify(data);
            }
            
            const startTime = Date.now();
            try {
                const res = await fetch(API_BASE + endpoint, options);
                const json = await res.json();
                const duration = Date.now() - startTime;
                showResponse({ method, endpoint, status: res.status, duration, data: json });
                return json;
            } catch (e) {
                showResponse({ method, endpoint, error: e.message });
                return null;
            }
        }

        function showResponse(data) {
            const el = document.getElementById('responseContent');
            const time = new Date().toLocaleTimeString();
            const color = data.error ? '#f44336' : (data.data?.code === 0 ? '#4caf50' : '#ff9800');
            el.innerHTML = `<span style="color:#888">[${time}]</span> <span style="color:#61dafb">${data.method}</span> <span style="color:#ce9178">${data.endpoint}</span>\n` +
                `<span style="color:${color}">Status: ${data.status || 'ERROR'}</span> ${data.duration ? `(${data.duration}ms)` : ''}\n\n` +
                JSON.stringify(data.data || data.error, null, 2);
        }

        function clearResponse() {
            document.getElementById('responseContent').innerHTML = '// API 响应将显示在这里...';
        }

        // 认证
        async function login() {
            const res = await apiRequest('POST', '/login.php', {
                username: document.getElementById('username').value,
                password: document.getElementById('password').value
            }, false);
            if (res?.code === 0) {
                token = res.data.token;
                localStorage.setItem('user_token', token);
                updateTokenStatus();
            }
        }

        async function logout() {
            await apiRequest('POST', '/login.php?action=logout');
            token = '';
            localStorage.removeItem('user_token');
            updateTokenStatus();
        }

        async function testRegister() {
            const data = {
                username: 'test_' + Date.now(),
                password: '123456',
                real_name: document.getElementById('regRealName').value,
                user_type: document.getElementById('regUserType').value
            };
            const extra = document.getElementById('regExtra').value;
            if (data.user_type === 'student') data.student_no = extra;
            else if (data.user_type === 'teacher') data.title = extra; // 简化
            else if (data.user_type === 'external') data.organization = extra; // 简化
            
            await apiRequest('POST', '/register.php', data, false);
        }

        // 设备
        async function getDevices() { 
            const keyword = document.getElementById('devKeyword').value;
            const category = document.getElementById('devCategory').value;
            const status = document.getElementById('devStatus').value;
            let query = [];
            if(keyword) query.push('keyword='+keyword);
            if(category) query.push('category='+category);
            if(status) query.push('status='+status);
            await apiRequest('GET', '/device.php?' + query.join('&')); 
        }
        async function getDeviceDetail() { await apiRequest('GET', '/device.php?id=' + document.getElementById('deviceId').value); }
        async function getCategories() { await apiRequest('GET', '/device.php?action=categories', null, false); }

        // 预约
        async function createReservation() {
            await apiRequest('POST', '/reservation.php', {
                device_id: parseInt(document.getElementById('deviceId').value),
                reserve_date: document.getElementById('reserveDate').value,
                time_slot: document.getElementById('timeSlot').value,
                purpose: document.getElementById('reservePurpose').value
            });
        }
        async function getReservations() { await apiRequest('GET', '/reservation.php'); }
        async function updateReservation() {
            await apiRequest('POST', '/reservation.php?action=update', {
                reservation_id: parseInt(document.getElementById('reservationId').value),
                reserve_date: document.getElementById('reserveDate').value,
                time_slot: document.getElementById('timeSlot').value,
                purpose: document.getElementById('reservePurpose').value
            });
        }
        async function cancelReservation() {
            await apiRequest('POST', '/reservation.php?action=cancel', {
                reservation_id: parseInt(document.getElementById('reservationId').value)
            });
        }

        // 借用
        async function getBorrows() { await apiRequest('GET', '/borrow.php'); }
        async function getBorrowDetail() { await apiRequest('GET', '/borrow.php?id=' + document.getElementById('borrowId').value); }
        async function returnDevice() {
            await apiRequest('POST', '/return.php', { borrow_id: parseInt(document.getElementById('borrowId').value) });
        }

        // 缴费
        async function getPayments() { await apiRequest('GET', '/payment.php'); }
        async function getPendingPayments() { await apiRequest('GET', '/payment.php?action=pending'); }
        async function payOrder() { 
            const id = document.getElementById('payId').value;
            if(!id) return alert('请输入支付ID');
            await apiRequest('POST', '/payment.php?action=pay', { payment_id: parseInt(id), pay_method: 'wechat' }); 
        }
        async function confirmPayment() { 
            const no = document.getElementById('payOrderNo').value;
            if(!no) return alert('请输入订单号');
            await apiRequest('POST', '/payment.php?action=confirm', { order_no: no }, false); 
        }

        // 个人中心
        async function getPersonal() { await apiRequest('GET', '/personal.php'); }
        async function updatePersonal() { 
            await apiRequest('POST', '/personal.php?action=update', { phone: document.getElementById('userPhone').value }); 
        }
        async function changePassword() { 
            await apiRequest('POST', '/personal.php?action=change_password', { 
                old_password: document.getElementById('oldPwd').value, 
                new_password: document.getElementById('newPwd').value 
            }); 
        }

        // 教师审批
        async function getApprovals() { await apiRequest('GET', '/approval.php'); }
        async function getApprovalHistory() { await apiRequest('GET', '/approval.php?action=history'); }
        async function approveStudent() { await apiRequest('POST', '/approval.php?action=approve', { reservation_id: parseInt(document.getElementById('approvalResId').value) }); }
        async function rejectStudent() { await apiRequest('POST', '/approval.php?action=reject', { 
            reservation_id: parseInt(document.getElementById('approvalResId').value), 
            reason: document.getElementById('rejectReason').value 
        }); }

        // 学生管理
        async function getStudents() { await apiRequest('GET', '/student.php'); }
        async function addStudent() {
            await apiRequest('POST', '/student.php', {
                student_no: document.getElementById('stuNo').value,
                real_name: document.getElementById('stuName').value,
                major: document.getElementById('stuMajor').value,
                college: document.getElementById('stuCollege').value,
                phone: document.getElementById('stuPhone').value
            });
        }
        async function batchImportStudents() {
            // 示例批量导入
            await apiRequest('POST', '/student.php?action=import', {
                students: [
                    { student_no: 'S2025001', real_name: '测试学生1', major: '软件工程', college: '计算机学院' },
                    { student_no: 'S2025002', real_name: '测试学生2', major: '计算机科学', college: '计算机学院' }
                ]
            });
        }
        async function removeStudent() { 
            const userId = document.getElementById('stuUserId').value;
            if(!userId) return alert('请输入学生用户ID');
            await apiRequest('POST', '/student.php?action=remove', { user_id: parseInt(userId) }); 
        }
    </script>
</body>
</html>
