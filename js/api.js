/**
 * API 请求工具类
 */
const API = {
    // 基础路径，根据当前页面深度动态计算
    get _root() {
        const path = window.location.pathname;
        // 如果当前在 admin 或 user 目录下，则根目录在上一级
        return (path.includes('/admin/') || path.includes('/user/')) ? '..' : '.';
    },
    get BASE_URL() { return `${this._root}/api`; },
    get ADMIN_BASE_URL() { return `${this._root}/admin/api`; },

    /**
     * 发送请求
     * @param {string} url 相对路径
     * @param {object} options fetch 选项
     * @param {boolean} isAdmin 是否为管理端请求
     */
    async request(url, options = {}, isAdmin = false) {
        const baseUrl = isAdmin ? this.ADMIN_BASE_URL : this.BASE_URL;
        const fullUrl = `${baseUrl}/${url.startsWith('/') ? url.substring(1) : url}`;

        // 默认选项
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
            }
        };

        // 注入 Token
        const token = Auth.getToken(isAdmin);
        if (token) {
            defaultOptions.headers['Authorization'] = `Bearer ${token}`;
        }

        const mergedOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...(options.headers || {})
            }
        };

        try {
            const response = await fetch(fullUrl, mergedOptions);
            const result = await response.json();

            if (response.status === 401) {
                // Token 过期或未授权
                Auth.logout(isAdmin);
                if (!url.includes('login.php')) {
                    const root = this._root;
                    window.location.href = isAdmin ? `${root}/admin/index.html` : `${root}/index.html`;
                }
                return result;
            }

            return result;
        } catch (error) {
            console.error('API Request Error:', error);
            return {
                code: -1,
                message: '网络请求失败，请稍后再试'
            };
        }
    },

    /**
     * GET 请求
     */
    get(url, params = {}, isAdmin = false) {
        // 添加时间戳防止缓存
        const paramsWithTime = { ...params, _t: new Date().getTime() };
        const queryString = new URLSearchParams(paramsWithTime).toString();
        const fullUrl = queryString ? `${url}${url.includes('?') ? '&' : '?'}${queryString}` : url;
        return this.request(fullUrl, { method: 'GET' }, isAdmin);
    },

    /**
     * POST 请求
     */
    post(url, data = {}, isAdmin = false) {
        return this.request(url, {
            method: 'POST',
            body: JSON.stringify(data)
        }, isAdmin);
    },

    /**
     * 文件上传 (multipart/form-data)
     */
    upload(url, formData, isAdmin = false) {
        return this.request(url, {
            method: 'POST',
            body: formData,
            headers: {} // 让浏览器自动设置 Content-Type 及其 boundary
        }, isAdmin);
    }
};

window.API = API;
