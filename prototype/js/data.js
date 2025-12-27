/**
 * 模拟数据
 */
const MockData = {
    currentUser: null,
    currentAdmin: null,
    users: [
        // 教师账户
        { id: 1, username: '张三', password: '123456', phone: '13800000001', type: 'teacher', realName: '张三', title: '教授', college: '计算机学院' },
        { id: 2, username: '王教授', password: '123456', phone: '13800000010', type: 'teacher', realName: '王建国', title: '副教授', college: '物联网学院' },
        // 学生账户
        { id: 3, username: '李四', password: '123456', phone: '13800000002', type: 'student', realName: '李四', studentNo: 'S2024001', major: '软件工程', college: '计算机学院', advisorId: 1 },
        { id: 4, username: '赵小明', password: '123456', phone: '13800000011', type: 'student', realName: '赵小明', studentNo: 'S2024002', major: '计算机科学', college: '计算机学院', advisorId: 1 },
        { id: 5, username: '孙丽丽', password: '123456', phone: '13800000012', type: 'student', realName: '孙丽丽', studentNo: 'S2024003', major: '物联网工程', college: '物联网学院', advisorId: 2 },
        { id: 6, username: '周杰', password: '123456', phone: '13800000013', type: 'student', realName: '周杰', studentNo: 'S2023015', major: '电子信息', college: '物联网学院', advisorId: 2 },
        // 校外人员账户
        { id: 7, username: '王五', password: '123456', phone: '13800000003', type: 'external', realName: '王五', organization: 'XX科技公司' },
        { id: 8, username: '刘经理', password: '123456', phone: '13900000001', type: 'external', realName: '刘伟', organization: '华为技术有限公司' },
        { id: 9, username: '陈工程师', password: '123456', phone: '13900000002', type: 'external', realName: '陈明', organization: '中兴通讯股份有限公司' },
        { id: 10, username: '李研究员', password: '123456', phone: '13900000003', type: 'external', realName: '李芳', organization: '中国科学院' }
    ],
    admins: [
        { id: 1, username: 'admin', password: 'admin123', role: 'supervisor', name: '系统管理员' }
    ],
    devices: [
        { id: 1, name: '示波器 A-001', category: '测量仪器', model: 'Tektronix TBS1102C', status: 0, location: '实验室A301', price: 50 },
        { id: 2, name: '万用表 B-002', category: '测量仪器', model: 'Fluke 17B+', status: 0, location: '实验室A301', price: 10 },
        { id: 3, name: '信号发生器 C-003', category: '信号源', model: 'RIGOL DG1022Z', status: 0, location: '实验室A302', price: 30 },
        { id: 4, name: '电源供应器 D-004', category: '电源设备', model: 'KORAD KA3005D', status: 1, location: '实验室A302', price: 20 },
        { id: 5, name: '逻辑分析仪 E-005', category: '分析仪器', model: 'Saleae Logic Pro 16', status: 2, location: '实验室A303', price: 80 }
    ],
    reservations: [
        { id: 1, userId: 1, deviceId: 4, startDate: '2024-12-25', endDate: '2024-12-27', status: 'approved', reason: '课程实验使用' },
        { id: 2, userId: 2, deviceId: 1, startDate: '2024-12-28', endDate: '2024-12-30', status: 'pending', reason: '毕业设计测试' }
    ],
    borrowRecords: [
        { id: 1, userId: 1, deviceId: 4, borrowDate: '2024-12-25', expectedReturn: '2024-12-27', actualReturn: null, status: 'borrowing' },
        { id: 2, userId: 2, deviceId: 1, borrowDate: '2024-12-10', expectedReturn: '2024-12-15', actualReturn: '2024-12-15', status: 'returned' }
    ],
    payments: [
        { id: 1, userId: 1, borrowId: 1, amount: 60, status: 'unpaid', description: '电源供应器借用3天' },
        { id: 2, userId: 2, borrowId: 2, amount: 250, status: 'paid', description: '示波器借用5天' }
    ],
    categories: ['全部', '测量仪器', '信号源', '电源设备', '分析仪器'],
    deviceStatus: { 0: { text: '可用', class: 'available' }, 1: { text: '借出', class: 'borrowed' }, 2: { text: '维护中', class: 'maintenance' } },
    reservationStatus: { 'pending': { text: '待审核', class: 'pending' }, 'approved': { text: '已批准', class: 'approved' }, 'rejected': { text: '已驳回', class: 'rejected' } },
    borrowStatus: { 'borrowing': { text: '借用中', class: 'borrowed' }, 'returned': { text: '已归还', class: 'available' } },
    paymentStatus: { 'paid': { text: '已支付', class: 'paid' }, 'unpaid': { text: '待支付', class: 'unpaid' } }
};

const DataService = {
    login(username, password) {
        const user = MockData.users.find(u => u.username === username && u.password === password);
        if (user) { MockData.currentUser = user; localStorage.setItem('currentUser', JSON.stringify(user)); return { success: true, user }; }
        return { success: false, message: '用户名或密码错误' };
    },
    adminLogin(username, password) {
        const admin = MockData.admins.find(a => a.username === username && a.password === password);
        if (admin) { MockData.currentAdmin = admin; localStorage.setItem('currentAdmin', JSON.stringify(admin)); return { success: true, admin }; }
        return { success: false, message: '管理员账号或密码错误' };
    },
    logout() { MockData.currentUser = null; localStorage.removeItem('currentUser'); },
    adminLogout() { MockData.currentAdmin = null; localStorage.removeItem('currentAdmin'); },
    getCurrentUser() { if (!MockData.currentUser) { const s = localStorage.getItem('currentUser'); if (s) MockData.currentUser = JSON.parse(s); } return MockData.currentUser; },
    getCurrentAdmin() { if (!MockData.currentAdmin) { const s = localStorage.getItem('currentAdmin'); if (s) MockData.currentAdmin = JSON.parse(s); } return MockData.currentAdmin; },
    getDevices(filter = {}) {
        let devices = [...MockData.devices];
        if (filter.category && filter.category !== '全部') devices = devices.filter(d => d.category === filter.category);
        if (filter.keyword) { const kw = filter.keyword.toLowerCase(); devices = devices.filter(d => d.name.toLowerCase().includes(kw)); }
        return devices;
    },
    getDevice(id) { return MockData.devices.find(d => d.id === parseInt(id)); },
    getUserReservations(userId) { return MockData.reservations.filter(r => r.userId === userId).map(r => ({ ...r, device: this.getDevice(r.deviceId) })); },
    getUserBorrows(userId) { return MockData.borrowRecords.filter(b => b.userId === userId).map(b => ({ ...b, device: this.getDevice(b.deviceId) })); },
    getUserPayments(userId) { return MockData.payments.filter(p => p.userId === userId); },
    createReservation(data) { const id = MockData.reservations.length + 1; MockData.reservations.push({ id, userId: MockData.currentUser.id, ...data, status: 'pending' }); return { success: true }; },
    cancelReservation(id) { const i = MockData.reservations.findIndex(r => r.id === id); if (i > -1) { MockData.reservations.splice(i, 1); return { success: true }; } return { success: false }; },
    approveReservation(id, approved) { const r = MockData.reservations.find(r => r.id === id); if (r) { r.status = approved ? 'approved' : 'rejected'; return { success: true }; } return { success: false }; },
    payBill(id) { const p = MockData.payments.find(p => p.id === id); if (p) { p.status = 'paid'; return { success: true }; } return { success: false }; },
    returnDevice(id) { const b = MockData.borrowRecords.find(b => b.id === id); if (b) { b.status = 'returned'; b.actualReturn = new Date().toLocaleDateString(); const d = this.getDevice(b.deviceId); if (d) d.status = 0; return { success: true }; } return { success: false }; },
    addDevice(data) { const id = MockData.devices.length + 1; MockData.devices.push({ id, status: 0, ...data }); return { success: true }; },
    updateDevice(id, data) { const d = MockData.devices.find(d => d.id === id); if (d) { Object.assign(d, data); return { success: true }; } return { success: false }; },
    deleteDevice(id) { const i = MockData.devices.findIndex(d => d.id === id); if (i > -1) { MockData.devices.splice(i, 1); return { success: true }; } return { success: false }; },
    getStats() { return { totalDevices: MockData.devices.length, availableDevices: MockData.devices.filter(d => d.status === 0).length, borrowingDevices: MockData.devices.filter(d => d.status === 1).length, pendingReservations: MockData.reservations.filter(r => r.status === 'pending').length, totalUsers: MockData.users.length }; },
    getAllReservations() { return MockData.reservations.map(r => ({ ...r, device: this.getDevice(r.deviceId), user: MockData.users.find(u => u.id === r.userId) })); },
    getAllBorrows() { return MockData.borrowRecords.map(b => ({ ...b, device: this.getDevice(b.deviceId), user: MockData.users.find(u => u.id === b.userId) })); },
    getAllPayments() { return MockData.payments.map(p => ({ ...p, user: MockData.users.find(u => u.id === p.userId) })); }
};

window.MockData = MockData;
window.DataService = DataService;
