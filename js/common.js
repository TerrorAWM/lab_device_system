/**
 * 通用 UI 逻辑 (侧边栏、导航栏等)
 */
const CommonUI = {
    /**
     * 初始化页面基础结构
     */
    init(activeId, isAdmin = false) {
        if (!Auth.checkAuth(isAdmin)) return;

        const user = Auth.getUser(isAdmin);
        this.renderTopNav(user, isAdmin);
        this.renderSidebar(user, activeId, isAdmin);
    },

    /**
     * 渲染顶部导航
     */
    renderTopNav(user, isAdmin) {
        const topNav = document.querySelector('.top-nav');
        if (!topNav) return;

        const realName = user.real_name || user.username || '用户';
        const avatarChar = realName.charAt(0);

        // 角色显示逻辑
        let roleText = '';
        let roleClass = '';

        if (isAdmin) {
            const adminRoles = {
                'supervisor': { text: '实验室负责人', class: 'bg-purple' },
                'device': { text: '设备管理员', class: 'bg-primary' },
                'finance': { text: '财务管理员', class: 'bg-success' }
            };
            const roleInfo = adminRoles[user.role] || { text: '管理员', class: 'bg-secondary' };
            roleText = roleInfo.text;
            roleClass = roleInfo.class;
        } else {
            const userTypes = {
                'teacher': { text: '教师', class: 'bg-indigo' },
                'student': { text: '学生', class: 'bg-info' },
                'external': { text: '校外人员', class: 'bg-orange' },
                'device': { text: '设备管理员', class: 'bg-primary' }
            };
            const typeInfo = userTypes[user.user_type] || { text: '用户', class: 'bg-secondary' };
            roleText = typeInfo.text;
            roleClass = typeInfo.class;
        }

        topNav.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="user-profile me-3" id="userProfileDropdown">
                    <div class="user-avatar">${avatarChar}</div>
                    <span class="fw-bold">${realName}</span>
                    <span class="badge ${roleClass} ms-2" style="font-weight: 500; padding: 0.4em 0.8em; border-radius: 6px;">${roleText}</span>
                </div>
                <button class="btn btn-outline-secondary btn-sm" onclick="CommonUI.logout(${isAdmin})">
                    <i class="fas fa-sign-out-alt me-1"></i> 退出
                </button>
            </div>
        `;
    },

    /**
     * 渲染侧边栏
     */
    renderSidebar(user, activeId, isAdmin) {
        const sidebar = document.querySelector('.sidebar');
        if (!sidebar) return;

        let menuItems = [];
        if (isAdmin) {
            menuItems = [
                { id: 'dashboard', href: 'dashboard.html', icon: 'fa-tachometer-alt', text: '仪表盘' },
                { id: 'device', href: 'device.html', icon: 'fa-desktop', text: '设备管理' },
                { id: 'reservation', href: 'reservation.html', icon: 'fa-calendar-check', text: '预约审批' },
                { id: 'borrow', href: 'borrow.html', icon: 'fa-hand-holding', text: '借用管理' },
                { id: 'payment', href: 'payment.html', icon: 'fa-credit-card', text: '收费管理' },
                { id: 'reports', href: 'reports.html', icon: 'fa-chart-bar', text: '统计报表' },
                { id: 'user', href: 'user.html', icon: 'fa-users', text: '用户管理' }
            ];

            // 根据角色过滤权限 (简单实现)
            // 调试信息：检查用户角色
            if (!user || !user.role) {
                console.warn('警告：管理员用户对象缺少role字段', user);
            }
            
            if (user && user.role === 'device') {
                menuItems = menuItems.filter(item => ['dashboard', 'device', 'borrow', 'reservation', 'reports'].includes(item.id));
                console.log('Device管理员菜单已过滤，包含预约审批');
            } else if (user && user.role === 'finance') {
                menuItems = menuItems.filter(item => ['dashboard', 'payment', 'reports'].includes(item.id));
            } else if (user && user.role === 'supervisor') {
                // supervisor角色显示所有菜单，不需要过滤
            } else {
                console.warn('未知的管理员角色:', user?.role);
            }
        } else {
            menuItems = [
                { id: 'device_list', href: 'device_list.html', icon: 'fa-desktop', text: '设备列表' },
                { id: 'reservation', href: 'reservation.html', icon: 'fa-calendar-check', text: '我的预约' },
                { id: 'borrow', href: 'borrow.html', icon: 'fa-hand-holding', text: '借用记录' },
                { id: 'payment', href: 'payment.html', icon: 'fa-credit-card', text: '缴费中心' },
                { id: 'profile', href: 'profile.html', icon: 'fa-user-circle', text: '个人中心' }
            ];

            // 教师额外菜单
            if (user.user_type === 'teacher') {
                menuItems.push({ id: 'student_approval', href: 'student_approval.html', icon: 'fa-clipboard-check', text: '导师审批' });
                menuItems.push({ id: 'students', href: 'students.html', icon: 'fa-user-graduate', text: '学生管理' });
            }

            // Device管理员额外菜单
            if (user.user_type === 'device') {
                menuItems.push({ id: 'reservation_approval', href: 'reservation_approval.html', icon: 'fa-check-circle', text: '预约审批' });
            }
        }

        const menuHtml = menuItems.map(item => `
            <a href="${item.href}" class="nav-item ${item.id === activeId ? 'active' : ''}" id="nav-${item.id}">
                <i class="fas ${item.icon}"></i>
                <span>${item.text}</span>
            </a>
        `).join('');

        sidebar.innerHTML = `
            <div class="sidebar-header">
                <i class="fas fa-flask me-2"></i>
                <span>${isAdmin ? '管理后台' : '设备管理'}</span>
            </div>
            <div class="sidebar-menu">
                ${menuHtml}
            </div>
        `;
    },

    /**
     * 退出登录
     */
    async logout(isAdmin = false) {
        try {
            await API.post('login.php?action=logout', {}, isAdmin);
        } catch (e) { }
        Auth.logout(isAdmin);
        const path = window.location.pathname;
        const root = (path.includes('/admin/') || path.includes('/user/')) ? '..' : '.';
        window.location.href = isAdmin ? `${root}/admin/index.html` : `${root}/index.html`;
    }
};

window.CommonUI = CommonUI;
