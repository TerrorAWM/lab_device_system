# 开发规范文档

本文档规定项目的开发流程、分支管理和代码规范。

## 分支管理

### 分支结构

```
main        ← 稳定版本，仅通过PR合并
  └─ dev    ← 开发主分支，日常开发在此进行
      └─ feature/xxx  ← 功能分支，开发完成后合并到dev
      └─ fix/xxx      ← 修复分支，修复完成后合并到dev
```

### 分支说明

| 分支 | 用途 | 权限 |
| --- | --- | --- |
| `main` | 稳定发布版本 | 禁止直接push，仅通过PR合并 |
| `dev` | 开发主分支 | 日常开发，可直接push |
| `feature/*` | 功能开发 | 从dev创建，合并回dev |
| `fix/*` | Bug修复 | 从dev创建，合并回dev |

### 分支操作规范

**禁止操作:**
- 禁止使用 `git push -f` 强制推送
- 禁止直接向 `main` 分支push

**开发新功能:**
```bash
# 1. 确保在dev分支
git checkout dev
git pull origin dev

# 2. 创建功能分支
git checkout -b feature/功能名称

# 3. 开发完成后提交
git add .
git commit -m "feat: 功能描述"
git push origin feature/功能名称

# 4. 合并到dev
git checkout dev
git merge feature/功能名称
git push origin dev

# 5. 删除功能分支(可选)
git branch -d feature/功能名称
```

**修复Bug:**
```bash
# 1. 从dev创建修复分支
git checkout dev
git checkout -b fix/问题描述

# 2. 修复后合并回dev
git checkout dev
git merge fix/问题描述
git push origin dev
```

---

## 提交规范

### Commit Message格式

```
<类型>: <简短描述>

[可选的详细描述]
```

### 类型说明

| 类型 | 说明 |
| --- | --- |
| `feat` | 新功能 |
| `fix` | Bug修复 |
| `docs` | 文档变更 |
| `style` | 代码格式(不影响功能) |
| `refactor` | 重构(不增加功能也不修复Bug) |
| `test` | 测试相关 |
| `chore` | 构建过程或辅助工具变动 |

### 示例

```bash
git commit -m "feat: 添加设备预约2小时时段功能"
git commit -m "fix: 修复财务审批按钮不显示问题"
git commit -m "docs: 更新原型使用说明文档"
```

---

## 代码规范

### PHP规范
- 使用 PDO 预处理语句，防止SQL注入
- 统一使用 `_resp.php` 返回JSON响应
- 敏感配置放在 `_config.php`，不提交到Git

### JavaScript规范
- 使用 `const` 和 `let`，避免 `var`
- 函数命名使用小驼峰 (camelCase)
- 事件处理函数命名使用 `onXxx` 或 `handleXxx`

### HTML/CSS规范
- 使用语义化HTML标签
- class命名使用kebab-case (短横线连接)
- 优先使用Bootstrap组件和工具类

---

## 发布流程

```
1. dev分支测试通过
2. 创建PR: dev → main
3. 代码审查通过
4. 合并到main
5. 创建版本Tag
```

---

## 常用命令速查

```bash
# 查看当前分支
git branch

# 切换到dev分支
git checkout dev

# 拉取最新代码
git pull origin dev

# 查看提交历史
git log --oneline -10

# 查看文件状态
git status

# 暂存所有修改
git add .

# 提交
git commit -m "类型: 描述"

# 推送
git push origin 分支名
```
