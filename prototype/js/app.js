/**
 * 应用逻辑
 */
const App = {
    showToast(message, type = 'success') {
        let container = document.getElementById('toast-container');
        if (!container) { container = document.createElement('div'); container.id = 'toast-container'; container.className = 'toast-container'; document.body.appendChild(container); }
        const toast = document.createElement('div'); toast.className = `toast ${type}`; toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'exclamation-circle'}"></i><span>${message}</span>`;
        container.appendChild(toast); setTimeout(() => toast.remove(), 3000);
    },
    navigate(url) { window.location.href = url; },
    checkAuth() { const user = DataService.getCurrentUser(); if (!user) { this.navigate('../index.html'); return false; } return user; },
    checkAdminAuth() { const admin = DataService.getCurrentAdmin(); if (!admin) { this.navigate('index.html'); return false; } return admin; },
    openModal(id) { document.getElementById(id).classList.add('active'); },
    closeModal(id) { document.getElementById(id).classList.remove('active'); },
    formatDate(date) { return new Date(date).toLocaleDateString('zh-CN'); },
    renderStatusBadge(statusObj) { return `<span class="status-badge ${statusObj.class}">${statusObj.text}</span>`; },
    renderAdminSidebar(role, activePage) {
        const roleInfo = MockData.adminRoles[role] || { permissions: [] };
        const permissions = roleInfo.permissions;
        const menuItems = [
            { id: 'dashboard', href: 'dashboard.html', icon: 'fa-tachometer-alt', text: '仪表盘' },
            { id: 'device', href: 'device.html', icon: 'fa-desktop', text: '设备管理' },
            { id: 'reservation', href: 'reservation.html', icon: 'fa-calendar-check', text: '预约审批' },
            { id: 'borrow', href: 'borrow.html', icon: 'fa-hand-holding', text: '借用管理' },
            { id: 'payment', href: 'payment.html', icon: 'fa-credit-card', text: '收费管理' },
            { id: 'user', href: 'user.html', icon: 'fa-users', text: '用户管理' },
            { id: 'maintenance', href: 'maintenance.html', icon: 'fa-wrench', text: '设备检修' },
            { id: 'reports', href: 'reports.html', icon: 'fa-chart-bar', text: '统计报表' }
        ];
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.innerHTML = menuItems
                .filter(item => permissions.includes(item.id))
                .map(item => `<a href="${item.href}" class="nav-item${item.id === activePage ? ' active' : ''}"><i class="fas ${item.icon}"></i>${item.text}</a>`)
                .join('');
        }
    },
    getAdminRoleName(role) {
        return MockData.adminRoles[role]?.name || '管理员';
    }
};

window.App = App;
