# 实验室设备管理系统 - README

> 更新：2025-12-20
> 
> 本项目是基于 PHP + MariaDB 的实验室设备管理系统后端示例，前后端分离，前端页面使用 Bootstrap 5.x 做快速 UI。
> 数据库名统一使用 `lab_device_system`。

---

## 目录结构

```
lab_device_system/
├─ api/                              # 用户侧 API
│  ├─ login.php                      # 用户登录
│  ├─ register.php                   # 用户注册
│  ├─ device.php                     # 设备列表/详情/搜索
│  ├─ reservation.php                # 预约申请/查询/取消
│  ├─ borrow.php                     # 借用记录/申请
│  ├─ return.php                     # 归还操作
│  ├─ payment.php                    # 缴费记录/支付
│  ├─ personal.php                   # 个人信息管理
│  ├─ _init.php                      # 公共初始化
│  ├─ _resp.php                      # 统一 JSON 响应
│  ├─ _db.php                        # 数据库连接
│  ├─ _auth.php                      # 用户鉴权
│  ├─ _config.php                    # 配置文件（不提交）
│  └─ _config.example.php            # 配置文件模板
├─ admin/                            # 管理后台
│  ├─ index.php                      # Admin 测试台（Bootstrap 5）
│  ├─ css/                           # 管理端样式
│  │  ├─ bootstrap.min.css           # Bootstrap 5.x
│  │  └─ fontawesome/                # Font Awesome 6.x Pro
│  │     ├─ css/
│  │     ├─ js/
│  │     ├─ webfonts/
│  │     └─ .gitkeep
│  ├─ js/                            # 管理端脚本
│  │  └─ bootstrap.bundle.min.js     # Bootstrap JS
│  └─ api/                           # 管理端 API
│     ├─ login.php                   # 管理员登录
│     ├─ register.php                # 管理员注册（开发期）
│     ├─ device.php                  # 设备台账管理（CRUD）
│     ├─ reservation.php             # 预约审批管理
│     ├─ borrow.php                  # 借用管理
│     ├─ payment.php                 # 收费管理
│     ├─ stats.php                   # 仪表盘统计
│     ├─ user.php                    # 用户管理
│     ├─ reset_password.php          # 管理员密码重置
│     ├─ _init.php                   # 公共初始化
│     ├─ _resp.php                   # 统一 JSON 响应
│     ├─ _db.php                     # 数据库连接
│     ├─ _auth.php                   # 管理员鉴权
│     ├─ _util.php                   # 工具函数
│     └─ _config.example.php         # 配置文件模板
├─ css/                              # 用户侧样式
│  ├─ bootstrap.min.css              # Bootstrap 5.x
│  └─ fontawesome/                   # Font Awesome 6.x Pro
│     ├─ css/
│     ├─ js/
│     ├─ webfonts/
│     └─ .gitkeep
├─ js/                               # 用户侧脚本
│  └─ bootstrap.bundle.min.js        # Bootstrap JS
├─ docs/                             # API 文档
│  ├─ user_side.md                   # 用户侧 API 文档
│  └─ admin_side.md                  # 管理端 API 文档
├─ sql/                              # SQL 脚本
│  └─ init.sql                       # 完整初始化脚本（建库+建表+示例数据）
├─ resources/                        # 项目资源文档
│  ├─ 总体规划.md
│  └─ 软件项目管理综合实践要求.md
├─ index.php                         # 用户侧测试页（Bootstrap 5）
├─ db.sql                            # 业务表结构
├─ .gitignore                        # Git 忽略规则
├─ .htaccess                         # Apache 配置
└─ readme.md                         # 本文件
```

---

## 系统角色

| 角色 | 描述 |
| --- | --- |
| **实验室负责人** | 审批设备借用申请、管理设备台账、查看统计报表 |
| **设备管理员** | 设备日常维护、借用/归还操作、收费确认 |
| **借用人员** | 教师/学生/校外人员，可预约、借用、归还设备 |

---

## 核心功能

### 用户侧
- **设备浏览**：设备列表、搜索、详情查看
- **预约申请**：提交设备借用预约、查询预约状态、取消预约
- **借用管理**：借用记录查询、归还操作
- **缴费功能**：查看费用明细、在线支付（模拟）
- **个人中心**：个人信息维护、借用历史

### 管理端
- **设备台账管理**：设备的增删改查、状态管理
- **预约审批**：审批借用申请、驳回处理
- **借用管理**：发放设备、确认归还、超期处理
- **收费管理**：费用生成、收费确认、财务对接
- **统计报表**：周/月/年统计、设备使用率分析

---

## 环境与依赖

| 组件 | 版本要求 |
| --- | --- |
| **PHP** | ≥ 8.2.4 |
| **MySQL / MariaDB** | ≥ 10.4.28 |
| **Web Server** | Apache / Nginx |
| **前端框架** | Bootstrap 5.x |
| **图标库** | Font Awesome 6.x Pro |

### 推荐开发环境

建议使用以下集成环境，内置 PHP 8.2+ 和 MariaDB 10.4+：

- **XAMPP** (最新版)：[https://www.apachefriends.org/](https://www.apachefriends.org/)
- **WAMP** (最新版)：[https://www.wampserver.com/](https://www.wampserver.com/)

### Font Awesome 图标

项目使用 **Font Awesome 6.x Pro** 版本，包含丰富的图标资源。

- **下载地址**：http://tangerine.international/fontawesome.zip
- **官方图标查询**：[https://fontawesome.com/icons](https://fontawesome.com/icons)
- **本地目录**：`css/fontawesome/` 和 `admin/css/fontawesome/`

> ⚠️ **注意**：`fontawesome/` 目录内容已被 `.gitignore` 排除，克隆项目后需手动下载并解压到 `css/` 和 `admin/css/` 目录。

**下载后解压步骤**：
```bash
# 下载 fontawesome.zip
curl -L -o fontawesome.zip http://tangerine.international/fontawesome.zip

# 解压到 css 目录
unzip fontawesome.zip -d css/

# 复制到 admin/css 目录
cp -r css/fontawesome admin/css/

# 删除压缩包
rm fontawesome.zip
```

---

## 初始化步骤

### 1. 创建数据库

```sql
CREATE DATABASE IF NOT EXISTS lab_device_system DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. 导入表结构

```bash
# 方法一：使用 sql/init.sql（推荐，包含建库、建表、示例数据）
mysql -u root -p < sql/init.sql

# 方法二：手动导入（如已有 db.sql）
mysql -u root -p lab_device_system < db.sql
```

**方法三：使用 phpMyAdmin（图形界面）**

1. 打开 phpMyAdmin（通常地址为 `http://localhost/phpmyadmin`）
2. 点击顶部的 **"导入"** 选项卡
3. 点击 **"选择文件"**，选择 `sql/init.sql` 文件
4. 保持默认设置，点击 **"执行"** 按钮
5. 等待导入完成，即可看到 `lab_device_system` 数据库及相关表

### 3. 配置数据库连接

复制配置文件模板并修改：

```bash
cp api/_config.example.php api/_config.php
cp admin/api/_config.example.php admin/api/_config.php
```

编辑 `api/_config.php`：

```php
<?php
define('DB_DSN', 'mysql:host=127.0.0.1;dbname=lab_device_system;charset=utf8mb4');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('JWT_SECRET', 'your_jwt_secret_key_change_this');
```

### 4. Apache 环境配置

确保 `Authorization` 头传递给 PHP，在项目根目录创建或编辑 `.htaccess`：

```apache
RewriteEngine On
SetEnvIfNoCase Authorization "^(.*)$" HTTP_AUTHORIZATION=$1
CGIPassAuth On
```

并确认 Apache 站点配置中 `<Directory>` 的 `AllowOverride All`，配置修改后重启 Apache。

### 5. 启动测试

**使用 PHP 内置服务器（开发测试）：**

```bash
php -S 0.0.0.0:8080 -t .
```

**使用 XAMPP/WAMP（推荐）：**

将项目放入 `htdocs` 或 `www` 目录，启动 Apache 和 MySQL 服务。

**访问地址：**

| 入口 | URL |
| --- | --- |
| 用户侧 | http://localhost/lab_device_system/ |
| 管理端 | http://localhost/lab_device_system/admin/ |

---

## 运行方式与入口

| 入口文件 | 描述 |
| --- | --- |
| `index.php` | 用户侧测试页，覆盖注册、登录、设备浏览、预约、借用、缴费、个人中心等功能 |
| `admin/index.php` | 管理端测试页，覆盖管理员登录、设备管理、预约审批、借用管理、统计报表等功能 |

两个测试页均使用 Bootstrap 5.x，UI 简洁，所有请求默认 JSON 格式，前端自动附带 `Authorization: Bearer <token>`。

---

## 原型设计参考

项目包含完整的 HTML 原型，位于 `prototype/` 目录，使用 Bootstrap 5 + Font Awesome 构建，可直接在浏览器中查看。

### 原型入口

| 入口 | 路径 |
| --- | --- |
| 用户登录页 | `prototype/index.html` |
| 管理员登录页 | `prototype/admin/index.html` |

### 用户侧页面 (`prototype/user/`)

| 页面 | 文件 | 说明 |
| --- | --- | --- |
| 设备列表 | `device_list.html` | 浏览、搜索设备 |
| 设备详情 | `device_detail.html` | 查看设备详情、发起预约 |
| 我的预约 | `reservation.html` | 查看预约状态 |
| 借用记录 | `borrow.html` | 查看借用历史 |
| 缴费中心 | `payment.html` | 查看待缴费用 |
| 个人中心 | `profile.html` | 个人信息维护 |

### 管理端页面 (`prototype/admin/`)

| 页面 | 文件 | 说明 |
| --- | --- | --- |
| 仪表盘 | `dashboard.html` | 统计概览 |
| 设备管理 | `device.html` | 设备台账 CRUD |
| 预约审批 | `reservation.html` | 审批借用申请 |
| 借用管理 | `borrow.html` | 借用/归还操作 |
| 收费管理 | `payment.html` | 费用确认 |

> 💡 **提示**：原型数据由 `prototype/js/data.js` 提供模拟，无需后端即可预览交互效果。

---

## API 公共库说明

### 用户侧 (`api/`)

| 文件 | 说明 |
| --- | --- |
| `_init.php` | 公共初始化，加载配置、设置 CORS、错误处理 |
| `_config.php` | 数据库配置（不提交到 Git） |
| `_config.example.php` | 配置文件模板 |
| `_db.php` | PDO 数据库连接（单例模式） |
| `_resp.php` | 统一 JSON 响应函数 |
| `_auth.php` | 用户 Token 鉴权 |

### 管理端 (`admin/api/`)

| 文件 | 说明 |
| --- | --- |
| `_init.php` | 公共初始化 |
| `_config.php` | 数据库配置（不提交到 Git） |
| `_db.php` | PDO 数据库连接 |
| `_resp.php` | 统一 JSON 响应函数 |
| `_auth.php` | 管理员 Token 鉴权、角色检查 |
| `_util.php` | 工具函数（日志记录、分页处理） |

---

## API 文档

| 文档 | 描述 |
| --- | --- |
| `docs/user_side.md` | 用户侧 API 文档 |
| `docs/admin_side.md` | 管理端 API 文档 |

---

## 关键业务约束（注意事项）

1. **设备状态**：设备具有 `可用`/`借出`/`维护`/`报废` 等状态，借用前需检查可用性
2. **预约流程**：`待审核` → `已批准`/`已驳回` → `借用中` → `已归还`
3. **借用时长**：根据用户类型（教师/学生/校外）设置不同的最大借用天数
4. **收费标准**：按设备类型、借用时长计费，超期需额外收费
5. **鉴权机制**：所有需要身份验证的接口必须携带 `Authorization: Bearer <token>`
6. **CORS 配置**：`_init.php` 默认设置 `Access-Control-Allow-Origin: *`，生产环境应改为白名单
7. **编码规范**：所有文件使用 UTF-8 无 BOM，`_resp.php` 统一 JSON 输出
8. **数据库名**：本项目数据库统一为 `lab_device_system`
9. **配置文件**：`_config.php` 包含敏感信息，已被 `.gitignore` 排除，使用 `_config.example.php` 作为模板

---

## 常见问题（FAQ）

### 登录后访问接口仍返回 401？

1. 确认请求头带有 `Authorization: Bearer <token>`
2. Apache 需要配置 `.htaccess` 传递该头
3. 确认 token 未过期，数据库 `user_tokens` 表存在记录

### 浏览器直接打开 API 返回 401？

直接在地址栏访问不会带自定义头，请使用测试页或 Postman/cURL。

### 如何模拟支付？

调用 `api/payment.php?action=confirm&order_no=...` 即视为支付成功，系统将更新支付状态。

### Font Awesome 图标不显示？

1. 确认已下载并解压 fontawesome.zip 到 `css/fontawesome/` 目录
2. 检查页面是否正确引入 `css/fontawesome/css/all.min.css`

---

## 技术与性能要求

| 指标 | 要求 |
| --- | --- |
| 并发用户 | ≥ 1000 |
| 查询响应时间 | ≤ 20 秒 |
| 数据保留 | 连续 3 年不丢失 |
| 客户端支持 | Windows |
| 服务端支持 | Linux |

---

## 开发建议

- **安全性**：优先使用 PDO 预处理防止 SQL 注入
- **数据校验**：对所有写操作进行基础校验（非空、范围、枚举）
- **操作日志**：管理端接口建议记录操作日志（可使用 `_util.php` 中的 `logAdminAction` 函数）
- **生产部署**：
  - 关闭 `admin/api/register.php` 与 `reset_password.php`
  - 限制 `Access-Control-Allow-Origin` 为可信域名
  - 为静态资源配置缓存头
  - 为关键接口增加速率限制（Rate Limiting）
  - 关闭 PHP 错误显示 (`display_errors = Off`)

---

## 版本信息

| 组件 | 版本 |
| --- | --- |
| PHP | 8.2.4 |
| MariaDB | 10.4.28 |
| Bootstrap | 5.x |
| Font Awesome | 6.x Pro |
| 数据库名 | lab_device_system |

---

## 项目背景

本项目为 **江南大学软件过程与项目管理** 课程综合实践项目，旨在通过实验室设备管理系统的开发，实践以下内容：

1. 面向对象的需求分析、系统分析与系统设计
2. 软件系统原型的设计及实现
3. Scrum 敏捷过程模型在项目开发中的应用

---

## 贡献指南

1. Fork 本仓库
2. 创建功能分支：`git checkout -b feature/your-feature`
3. 提交更改：`git commit -m 'Add some feature'`
4. 推送分支：`git push origin feature/your-feature`
5. 提交 Pull Request

---

祝开发顺利！🎉
