/**
 * 身份验证工具类
 */
const Auth = {
    TOKEN_KEY: 'lab_token',
    ADMIN_TOKEN_KEY: 'lab_admin_token',
    USER_KEY: 'lab_user',
    ADMIN_KEY: 'lab_admin',

    /**
     * 获取 Token
     */
    getToken(isAdmin = false) {
        return localStorage.getItem(isAdmin ? this.ADMIN_TOKEN_KEY : this.TOKEN_KEY);
    },

    /**
     * 保存登录信息
     */
    saveLogin(data, isAdmin = false) {
        if (isAdmin) {
            localStorage.setItem(this.ADMIN_TOKEN_KEY, data.token);
            localStorage.setItem(this.ADMIN_KEY, JSON.stringify(data.admin));
        } else {
            localStorage.setItem(this.TOKEN_KEY, data.token);
            localStorage.setItem(this.USER_KEY, JSON.stringify(data.user));
        }
    },

    /**
     * 获取当前用户信息
     */
    getUser(isAdmin = false) {
        const userStr = localStorage.getItem(isAdmin ? this.ADMIN_KEY : this.USER_KEY);
        return userStr ? JSON.parse(userStr) : null;
    },

    /**
     * 检查是否已登录
     */
    isLoggedIn(isAdmin = false) {
        return !!this.getToken(isAdmin);
    },

    /**
     * 登出
     */
    logout(isAdmin = false) {
        if (isAdmin) {
            localStorage.removeItem(this.ADMIN_TOKEN_KEY);
            localStorage.removeItem(this.ADMIN_KEY);
        } else {
            localStorage.removeItem(this.TOKEN_KEY);
            localStorage.removeItem(this.USER_KEY);
        }
    },

    /**
     * 检查权限并重定向（如果未登录）
     */
    checkAuth(isAdmin = false) {
        if (!this.isLoggedIn(isAdmin)) {
            const path = window.location.pathname;
            const root = (path.includes('/admin/') || path.includes('/user/')) ? '..' : '.';
            const loginPage = isAdmin ? `${root}/admin/index.html` : `${root}/index.html`;
            // 避免在登录页循环跳转
            if (!path.endsWith('index.html') || (isAdmin && !path.includes('/admin/')) || (!isAdmin && path.includes('/admin/'))) {
                window.location.href = loginPage;
            }
            return false;
        }
        return true;
    }
};

window.Auth = Auth;
