<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理端 API 测试台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .main-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border-top: 4px solid #0d6efd; }
        .api-section { background: #ffffff; border: 1px solid #e9ecef; border-radius: 8px; margin-bottom: 1rem; overflow: hidden; }
        .api-section h5 { background: #f8f9fa; color: #0d6efd; padding: 12px 20px; border-bottom: 1px solid #e9ecef; margin: 0; font-weight: 600; font-size: 1rem; }
        .api-btn { margin: 4px; }
        .response-area { background: #212529; color: #f8f9fa; border-radius: 6px; font-family: 'Consolas', 'Monaco', monospace; font-size: 13px; max-height: 700px; overflow-y: auto; border: 1px solid #dee2e6; }
        .token-display { background: #fff3cd; color: #856404; border-radius: 6px; word-break: break-all; padding: 10px; border: 1px solid #ffeeba; }
        pre { margin: 0; white-space: pre-wrap; }
        .badge-role { font-size: 0.8rem; }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="main-card p-4">
            <div class="text-center mb-4">
                <h2><i class="fas fa-cogs text-danger me-2"></i>管理端 API 测试台</h2>
                <p class="text-muted">实验室设备管理系统 - Admin API Tester</p>
            </div>
            
            <!-- Token 状态 -->
            <div class="alert alert-warning d-flex align-items-center mb-4" id="tokenStatus">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <span>尚未登录，请先使用管理员账号登录</span>
            </div>

            <div class="row">
                <!-- 左侧：API 操作 -->
                <div class="col-lg-7">
                    <div class="row">
                        <!-- 认证模块 -->
                        <div class="col-md-6">
                            <div class="api-section">
                                <h5><i class="fas fa-shield-alt me-2"></i>管理员认证</h5>
                                <div class="p-3">
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <input type="text" class="form-control" id="username" placeholder="用户名" value="supervisor">
                                        </div>
                                        <div class="col-6">
                                            <input type="password" class="form-control" id="password" placeholder="密码" value="123456">
                                        </div>
                                    </div>
                                    <button class="btn btn-danger api-btn" onclick="login()"><i class="fas fa-sign-in-alt me-1"></i>登录</button>
                                    <button class="btn btn-outline-danger api-btn" onclick="logout()"><i class="fas fa-sign-out-alt me-1"></i>退出</button>
                                    <button class="btn btn-outline-secondary api-btn" onclick="testRegister()"><i class="fas fa-user-plus me-1"></i>注册</button>
                                    <div class="row mt-2 g-2">
                                        <div class="col-6"><input type="text" class="form-control form-control-sm" id="regAdminName" placeholder="新管理员名"></div>
                                        <div class="col-6">
                                            <select class="form-control form-control-sm" id="regAdminRole">
                                                <option value="device">设备管理员</option>
                                                <option value="finance">财务管理员</option>
                                                <option value="supervisor">负责人</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mt-2 text-muted small">默认账号: supervisor / device / finance (pwd: 123456)</div>
                                </div>
                            </div>
                        </div>

                        <!-- 统计报表 -->
                        <div class="col-md-6">
                            <div class="api-section">
                                <h5><i class="fas fa-chart-bar me-2"></i>统计报表</h5>
                                <div class="p-3">
                                    <button class="btn btn-info api-btn" onclick="getDashboard()"><i class="fas fa-tachometer-alt me-1"></i>仪表盘</button>
                                    <div class="input-group input-group-sm mt-2">
                                        <select class="form-select" id="statsPeriod">
                                            <option value="week">本周</option>
                                            <option value="month" selected>本月</option>
                                            <option value="year">本年</option>
                                        </select>
                                        <button class="btn btn-success" onclick="getDeviceUsage()"><i class="fas fa-chart-line me-1"></i>设备使用</button>
                                        <button class="btn btn-warning" onclick="getRevenue()"><i class="fas fa-dollar-sign me-1"></i>收入统计</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 设备管理 -->
                        <div class="col-md-6">
                            <div class="api-section">
                                <h5><i class="fas fa-desktop me-2"></i>设备管理</h5>
                                <div class="p-3">
                                    <div class="row g-2 mb-2">
                                        <div class="col-4"><input type="text" class="form-control form-control-sm" id="newDevName" placeholder="名称" value="测试设备"></div>
                                        <div class="col-4"><input type="text" class="form-control form-control-sm" id="newDevModel" placeholder="型号" value="M-01"></div>
                                        <div class="col-4"><input type="number" class="form-control form-control-sm" id="newDevPrice" placeholder="价格" value="1000"></div>
                                    </div>
                                    <button class="btn btn-success api-btn" onclick="getDevices()"><i class="fas fa-list me-1"></i>列表</button>
                                    <button class="btn btn-info api-btn" onclick="getDeviceDetail()"><i class="fas fa-info me-1"></i>详情</button>
                                    <button class="btn btn-primary api-btn" onclick="createDevice()"><i class="fas fa-plus me-1"></i>新增</button>
                                    <button class="btn btn-warning api-btn" onclick="updateDevice()"><i class="fas fa-edit me-1"></i>更新</button>
                                    <button class="btn btn-danger api-btn" onclick="deleteDevice()"><i class="fas fa-trash me-1"></i>删除</button>
                                    <div class="input-group input-group-sm mt-2">
                                        <input type="number" class="form-control" id="deviceId" placeholder="设备ID" value="1">
                                        <select class="form-select" id="newDevStatus">
                                            <option value="1">可用</option>
                                            <option value="3">维护</option>
                                            <option value="4">报废</option>
                                        </select>
                                        <button class="btn btn-secondary" onclick="updateDeviceStatus()"><i class="fas fa-toggle-on"></i>状态</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 预约审批 -->
                        <div class="col-md-6">
                            <div class="api-section">
                                <h5><i class="fas fa-clipboard-check me-2"></i>预约审批</h5>
                                <div class="p-3">
                                    <button class="btn btn-success api-btn" onclick="getReservations()"><i class="fas fa-list me-1"></i>预约列表</button>
                                    <button class="btn btn-info api-btn" onclick="getReservationDetail()"><i class="fas fa-info me-1"></i>详情</button>
                                    <button class="btn btn-primary api-btn" onclick="approveReservation()"><i class="fas fa-check me-1"></i>批准</button>
                                    <div class="input-group input-group-sm mt-2">
                                        <input type="number" class="form-control" id="reservationId" placeholder="预约ID" value="1" style="max-width: 80px;">
                                        <input type="text" class="form-control" id="rejectReason" placeholder="驳回原因">
                                        <button class="btn btn-danger" onclick="rejectReservation()"><i class="fas fa-times"></i>驳回</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 借用管理 -->
                        <div class="col-md-6">
                            <div class="api-section">
                                <h5><i class="fas fa-hand-holding me-2"></i>借用管理</h5>
                                <div class="p-3">
                                    <button class="btn btn-success api-btn" onclick="getBorrows()"><i class="fas fa-list me-1"></i>借用列表</button>
                                    <button class="btn btn-info api-btn" onclick="getBorrowDetail()"><i class="fas fa-info me-1"></i>详情</button>
                                    <button class="btn btn-primary api-btn" onclick="dispatchDevice()"><i class="fas fa-truck me-1"></i>发放</button>
                                    <div class="input-group input-group-sm mt-2">
                                        <input type="number" class="form-control" id="borrowId" placeholder="借用ID" value="1" style="max-width: 80px;">
                                        <select class="form-select" id="deviceCondition">
                                            <option value="good">完好</option>
                                            <option value="damaged">损坏</option>
                                        </select>
                                        <button class="btn btn-warning" onclick="confirmReturn()"><i class="fas fa-undo"></i>确认归还</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 收费管理 -->
                        <div class="col-md-6">
                            <div class="api-section">
                                <h5><i class="fas fa-money-bill me-2"></i>收费管理</h5>
                                <div class="p-3">
                                    <button class="btn btn-success api-btn" onclick="getPayments()"><i class="fas fa-list me-1"></i>订单列表</button>
                                    <button class="btn btn-info api-btn" onclick="getPaymentDetail()"><i class="fas fa-info me-1"></i>详情</button>
                                    <button class="btn btn-warning api-btn" onclick="markPaid()"><i class="fas fa-check me-1"></i>标记支付</button>
                                    <div class="input-group input-group-sm mt-2">
                                        <input type="number" class="form-control" id="paymentUserId" placeholder="用户ID" value="1" style="max-width: 70px;">
                                        <input type="number" class="form-control" id="paymentAmount" placeholder="金额" value="100" style="max-width: 80px;">
                                        <input type="text" class="form-control" id="paymentDesc" placeholder="费用描述">
                                        <button class="btn btn-primary" onclick="createPayment()"><i class="fas fa-plus"></i>收费</button>
                                    </div>
                                    <div class="mt-2">
                                        <input type="number" class="form-control form-control-sm" id="paymentId" placeholder="订单ID操作" value="1">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 用户管理 -->
                        <div class="col-md-12">
                            <div class="api-section">
                                <h5><i class="fas fa-users me-2"></i>用户管理</h5>
                                <div class="p-3">
                                    <button class="btn btn-success api-btn" onclick="getUsers()"><i class="fas fa-list me-1"></i>用户列表</button>
                                    <button class="btn btn-info api-btn" onclick="getUserDetail()"><i class="fas fa-info me-1"></i>用户详情</button>
                                    <button class="btn btn-warning api-btn" onclick="disableUser()"><i class="fas fa-ban me-1"></i>禁用</button>
                                    <button class="btn btn-success api-btn" onclick="enableUser()"><i class="fas fa-check me-1"></i>启用</button>
                                    <input type="number" class="form-control form-control-sm d-inline-block ms-2" id="userId" placeholder="用户ID" value="1" style="width:80px">
                                    <input type="text" class="form-control form-control-sm d-inline-block ms-2" id="userKeyword" placeholder="搜索关键词" style="width:120px">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 右侧：响应结果 -->
                <div class="col-lg-5">
                    <div class="sticky-top" style="top: 20px;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="mb-0"><i class="fas fa-terminal me-2"></i>响应结果</h5>
                            <button class="btn btn-sm btn-outline-secondary" onclick="clearResponse()"><i class="fas fa-trash me-1"></i>清空</button>
                        </div>
                        <div class="response-area p-3" id="responseArea" style="min-height: 700px;">
                            <pre id="responseContent">// API 响应将显示在这里...</pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '/lab_device_system/admin/api';
        let token = localStorage.getItem('admin_token') || '';
        
        // 更新 Token 状态
        function updateTokenStatus() {
            const el = document.getElementById('tokenStatus');
            if (token) {
                el.className = 'alert alert-success d-flex align-items-center mb-4';
                el.innerHTML = '<i class="fas fa-check-circle me-2"></i><span>已登录，Token: ' + token.substring(0, 20) + '...</span>';
            } else {
                el.className = 'alert alert-warning d-flex align-items-center mb-4';
                el.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i><span>尚未登录，请先使用管理员账号登录</span>';
            }
        }
        updateTokenStatus();

        // API 请求封装
        async function apiRequest(method, endpoint, data = null) {
            const options = {
                method: method,
                headers: { 'Content-Type': 'application/json' }
            };
            if (token) {
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
            });
            if (res?.code === 0) {
                token = res.data.token;
                localStorage.setItem('admin_token', token);
                updateTokenStatus();
            }
        }

        async function logout() {
            await apiRequest('POST', '/login.php?action=logout');
            token = '';
            localStorage.removeItem('admin_token');
            updateTokenStatus();
        }

        async function testRegister() {
            const name = document.getElementById('regAdminName').value;
            await apiRequest('POST', '/register.php', {
                username: 'admin_' + (name || Date.now()),
                password: '123456',
                real_name: name || '测试管理员',
                role: document.getElementById('regAdminRole').value
            });
        }

        // 统计
        async function getDashboard() { await apiRequest('GET', '/stats.php?action=dashboard'); }
        async function getDeviceUsage() { await apiRequest('GET', '/stats.php?action=device_usage&period=' + document.getElementById('statsPeriod').value); }
        async function getRevenue() { await apiRequest('GET', '/stats.php?action=revenue&period=' + document.getElementById('statsPeriod').value); }

        // 设备
        async function getDevices() { await apiRequest('GET', '/device.php'); }
        async function getDeviceDetail() { await apiRequest('GET', '/device.php?id=' + document.getElementById('deviceId').value); }
        async function createDevice() {
            await apiRequest('POST', '/device.php?action=create', {
                device_name: document.getElementById('newDevName').value,
                model: document.getElementById('newDevModel').value,
                manufacturer: '测试厂商',
                category: '测试类别',
                location: '实验室T101',
                price: parseFloat(document.getElementById('newDevPrice').value),
                rent_price: 20
            });
        }
        async function updateDevice() {
            await apiRequest('POST', '/device.php?action=update', {
                device_id: parseInt(document.getElementById('deviceId').value),
                device_name: document.getElementById('newDevName').value,
                location: '实验室B202'
            });
        }
        async function updateDeviceStatus() {
            await apiRequest('POST', '/device.php?action=update_status', {
                device_id: parseInt(document.getElementById('deviceId').value),
                status: parseInt(document.getElementById('newDevStatus').value)
            });
        }
        async function deleteDevice() {
            await apiRequest('POST', '/device.php?action=delete', {
                device_id: parseInt(document.getElementById('deviceId').value)
            });
        }

        // 预约
        async function getReservations() { await apiRequest('GET', '/reservation.php'); }
        async function getReservationDetail() { await apiRequest('GET', '/reservation.php?id=' + document.getElementById('reservationId').value); }
        async function approveReservation() {
            await apiRequest('POST', '/reservation.php?action=approve', {
                reservation_id: parseInt(document.getElementById('reservationId').value)
            });
        }
        async function rejectReservation() {
            await apiRequest('POST', '/reservation.php?action=reject', {
                reservation_id: parseInt(document.getElementById('reservationId').value),
                reason: document.getElementById('rejectReason').value || '未填写原因'
            });
        }

        // 借用
        async function getBorrows() { await apiRequest('GET', '/borrow.php'); }
        async function getBorrowDetail() { await apiRequest('GET', '/borrow.php?id=' + document.getElementById('borrowId').value); }
        async function dispatchDevice() {
            await apiRequest('POST', '/borrow.php?action=dispatch', {
                reservation_id: parseInt(document.getElementById('reservationId').value) // 注意借用发放用的是预约ID
            });
        }
        async function confirmReturn() {
            await apiRequest('POST', '/borrow.php?action=confirm_return', {
                borrow_id: parseInt(document.getElementById('borrowId').value),
                device_condition: document.getElementById('deviceCondition').value
            });
        }

        // 收费
        async function getPayments() { await apiRequest('GET', '/payment.php'); }
        async function getPaymentDetail() { await apiRequest('GET', '/payment.php?id=' + document.getElementById('paymentId').value); }
        async function createPayment() {
            await apiRequest('POST', '/payment.php?action=create', {
                user_id: parseInt(document.getElementById('paymentUserId').value),
                amount: parseFloat(document.getElementById('paymentAmount').value),
                description: document.getElementById('paymentDesc').value || '测试费用'
            });
        }
        async function markPaid() {
            await apiRequest('POST', '/payment.php?action=mark_paid', {
                payment_id: parseInt(document.getElementById('paymentId').value)
            });
        }

        // 用户
        async function getUsers() { 
            const keyword = document.getElementById('userKeyword').value;
            await apiRequest('GET', '/user.php' + (keyword ? '?keyword=' + keyword : '')); 
        }
        async function getUserDetail() { await apiRequest('GET', '/user.php?id=' + document.getElementById('userId').value); }
        async function disableUser() {
            await apiRequest('POST', '/user.php?action=toggle_status', {
                user_id: parseInt(document.getElementById('userId').value),
                status: 0
            });
        }
        async function enableUser() {
            await apiRequest('POST', '/user.php?action=toggle_status', {
                user_id: parseInt(document.getElementById('userId').value),
                status: 1
            });
        }
    </script>
</body>
</html>
