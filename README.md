# 中考倒计时项目

一个简洁的PHP倒计时网站，支持两种显示模式（天:时:分:秒 和 纯秒数），可在线编辑配置并实时生效，支持名言投稿与管理员审核。

> **版本说明**：本分支为**中考版**（默认目标 2026-06-26，可在编辑页面修改）。
> 高考版在主分支 `main`（默认目标 2027-06-07）：`git checkout main`。

## 项目结构

```
countdown/
├── config.php              # 数据库配置（从 .env 读取）
├── index.php               # 主页面（天:时:分:秒格式）
├── seconds.php             # 秒数页面（纯秒数格式）
├── edit.php                # 配置编辑页面（管理员）
├── submit.php              # 名言投稿页面
├── admin/
│   └── review.php          # 名言审核后台（管理员）
├── api/
│   ├── get_config.php      # 获取配置API（公开只读）
│   ├── save_config.php     # 保存配置API（需登录）
│   ├── save_config_batch.php # 批量保存配置API（需登录）
│   ├── submit_quote.php    # 名言投稿API（限流）
│   ├── approve_quote.php   # 审核API（需登录）
│   ├── get_pending_quotes.php # 获取投稿列表API（需登录）
│   ├── check_quotes.php    # 投稿状态查询API（仅返回状态）
│   └── get_server_time.php # 服务器时间API
├── includes/
│   ├── EnvLoader.php       # .env 加载器
│   ├── Database.php        # 数据库连接类（自动建表）
│   ├── Auth.php            # 登录认证 + 登录限流
│   └── HtmlSanitizer.php   # HTML 白名单净化器
├── css/
│   └── style.css
└── js/
    └── script.js           # 倒计时和轮播逻辑
```

## 安装步骤

### 1. 配置环境变量（唯一必要步骤）

复制 `.env.example` 为 `.env`，填写真实值：

```bash
cp .env.example .env
# 编辑 .env：数据库连接信息 + 管理员密码
```

`.env` 已被 `.gitignore` 忽略，**严禁提交到版本控制**。

### 2. 部署文件

将整个项目文件夹上传到您的Web服务器（`api/` 目录需要有读写权限）。

### 3. 访问页面

浏览器打开任意页面，系统自动完成建库建表：

- **主页面**: `index.php` - 天:时:分:秒 格式倒计时
- **秒数页面**: `seconds.php` - 纯秒数倒计时
- **投稿页面**: `submit.php` - 投稿励志名言
- **编辑页面**: `edit.php` - 配置编辑（右上角齿轮进入）
- **审核后台**: `admin/review.php` - 名言审核

## 功能说明

### 主页面 / 秒数页面
- 实时倒计时（服务器时间校准，防客户端改时间作弊）
- 励志话语轮播（每5秒切换，支持 `|` 分隔多条）
- 字体、颜色、背景均可在线配置

### 编辑页面 (edit.php)
- **统一配置**：主页面与秒数页面共用一套配置（目标日期、励志话语、字体、背景），改一处两页同时生效
- **字体设置**：标题 / 倒计时数字 / 励志话语，各自独立设置大小、颜色、字体族
- **背景设置**：背景颜色 / 背景图片URL
- 保存需管理员登录（session + token 认证）

### 投稿与审核
- 用户通过 `submit.php` 投稿名言
- 管理员在 `admin/review.php` 审核，通过后自动合并进页面轮播
- 投稿内容仅支持 `b/i/u/em/strong` 等纯文本标签，其余 HTML 一律剥除

## 安全说明（2026-08-10 加固）

- **凭据管理**：所有敏感信息在 `.env`，不提交仓库；未配置管理员密码时拒绝一切登录（无默认密码）
- **接口鉴权**：配置写入 / 审核接口均需登录 token；公开接口只读或限流
- **XSS 防护**：服务端 HTML 白名单净化（`HtmlSanitizer`）+ 前端 DOMParser 白名单渲染，双重保险
- **登录限流**：连续失败 5 次锁定 15 分钟（按 IP）；投稿接口每 IP 10 分钟限 5 次
- **Session 加固**：HttpOnly + SameSite=Lax + 登录后 regenerate_id
- **错误处理**：对外响应不暴露服务器路径与代码行号

## 注意事项

1. 编辑页面入口：倒计时页面右上角齿轮图标（⚙️）
2. 数据库需支持 utf8mb4；首次访问自动建库建表
3. 字体族需有效 CSS 字体栈，否则回退默认字体
4. 曾公开泄露过凭据的部署，请务必先轮换数据库/FTP/管理员密码

## License

MIT License
