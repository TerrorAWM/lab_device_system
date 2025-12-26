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
    renderStatusBadge(statusObj) { return `<span class="status-badge ${statusObj.class}">${statusObj.text}</span>`; }
};

window.App = App;
