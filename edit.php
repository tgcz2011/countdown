<?php
/**
 * 编辑页面 - 需要密码验证
 */
require_once __DIR__ . '/includes/EnvLoader.php';
require_once __DIR__ . '/includes/Auth.php';

$auth = Auth::getInstance();

// 处理登录
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($auth->login($_POST['password'])) {
        // 登录成功，刷新页面
        header('Location: edit.php');
        exit;
    } else {
        $err = $auth->getLastError();
        if ($err && $err['code'] === 'locked') {
            $minutes = (int)ceil(($err['remaining'] ?? 900) / 60);
            $loginError = '尝试次数过多，请 ' . max(1, $minutes) . ' 分钟后再试';
        } elseif ($err && $err['code'] === 'db_unavailable') {
            $loginError = '系统暂时不可用，请稍后再试';
        } elseif ($err && $err['code'] === 'password') {
            $loginError = '密码错误，请重试';
        } else {
            $loginError = '系统未配置管理员密码，请联系部署者';
        }
    }
}

// 处理登出
if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: edit.php');
    exit;
}

// 检查是否已登录
$isLoggedIn = $auth->isAuthenticated();
$authToken = $isLoggedIn ? $auth->getAuthToken() : '';

// 如果未登录，显示登录页面
if (!$isLoggedIn):
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg: #0f1419;
            --surface: #1a2029;
            --surface-2: #232b36;
            --border: rgba(255,255,255,0.08);
            --text: #e8ecf1;
            --text-dim: #8b95a3;
            --accent: #34d399;
            --accent-dim: rgba(52,211,153,0.12);
            --warn: #fbbf24;
            --danger: #f87171;
            --mono: ui-monospace, "SF Mono", "Cascadia Code", "Courier New", monospace;
        }
        body {
            font-family: -apple-system, "PingFang SC", "Microsoft YaHei", "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        body::before {
            content: '';
            position: fixed;
            top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: radial-gradient(ellipse at 30% 20%, rgba(52,211,153,0.06) 0%, transparent 50%),
                        radial-gradient(ellipse at 70% 80%, rgba(251,191,36,0.04) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        .login-box {
            position: relative;
            z-index: 1;
            background: var(--surface);
            border: 1px solid var(--border);
            padding: 40px 36px;
            border-radius: 16px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.4);
            width: 380px;
            max-width: 100%;
        }
        .login-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--accent);
            background: var(--accent-dim);
            padding: 4px 10px;
            border-radius: 999px;
            margin-bottom: 20px;
        }
        .login-box h1 {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.01em;
        }
        .login-box p {
            color: var(--text-dim);
            margin-bottom: 28px;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .login-box input[type="password"] {
            width: 100%;
            padding: 13px 16px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 1rem;
            color: var(--text);
            margin-bottom: 16px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .login-box input[type="password"]::placeholder { color: var(--text-dim); }
        .login-box input[type="password"]:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-dim);
        }
        .login-box button {
            width: 100%;
            padding: 13px;
            background: var(--accent);
            color: #0a1f17;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }
        .login-box button:hover { background: #4ade80; }
        .login-box button:active { transform: scale(0.98); }
        .error-msg {
            color: var(--danger);
            background: rgba(248,113,113,0.1);
            border: 1px solid rgba(248,113,113,0.2);
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.88rem;
            text-align: center;
        }
        .hint {
            text-align: center;
            color: var(--text-dim);
            margin-top: 20px;
            font-size: 0.82rem;
        }
        .hint a {
            color: var(--accent);
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .hint a:hover { opacity: 0.8; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="login-badge">● 管理后台</div>
        <h1>管理员登录</h1>
        <p>请输入管理密码进入倒计时设置页面</p>
        <?php if ($loginError): ?>
            <div class="error-msg"><?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="password" name="password" placeholder="请输入管理密码" required autofocus>
            <button type="submit">登录</button>
        </form>
        <div class="hint">
            <a href="index.php">← 返回首页</a>
        </div>
    </div>
</body>
</html>
<?php
exit;
endif;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>倒计时设置</title>
    <style>
        :root {
            --bg: #0f1419;
            --surface: #1a2029;
            --surface-2: #232b36;
            --surface-3: #2c3542;
            --border: rgba(255,255,255,0.08);
            --border-strong: rgba(255,255,255,0.14);
            --text: #e8ecf1;
            --text-dim: #8b95a3;
            --text-faint: #5c6675;
            --accent: #34d399;
            --accent-hover: #4ade80;
            --accent-dim: rgba(52,211,153,0.12);
            --warn: #fbbf24;
            --danger: #f87171;
            --info: #60a5fa;
            --mono: ui-monospace, "SF Mono", "Cascadia Code", "Courier New", monospace;
            --radius: 12px;
            --radius-sm: 8px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, "PingFang SC", "Microsoft YaHei", "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 24px 16px 60px;
            line-height: 1.5;
        }
        body::before {
            content: '';
            position: fixed;
            top: -40%; left: -30%;
            width: 160%; height: 160%;
            background: radial-gradient(ellipse at 25% 15%, rgba(52,211,153,0.05) 0%, transparent 45%),
                        radial-gradient(ellipse at 75% 85%, rgba(251,191,36,0.035) 0%, transparent 45%);
            pointer-events: none;
            z-index: 0;
        }

        .container {
            position: relative;
            z-index: 1;
            max-width: 880px;
            margin: 0 auto;
        }

        /* ===== Header ===== */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 28px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius) var(--radius) 0 0;
            border-bottom: none;
            flex-wrap: wrap;
            gap: 12px;
        }
        .header-left { display: flex; align-items: center; gap: 14px; }
        .header-icon {
            width: 40px; height: 40px;
            background: var(--accent-dim);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
        }
        .header h1 {
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: -0.01em;
        }
        .header-sub {
            font-size: 0.78rem;
            color: var(--text-dim);
            margin-top: 2px;
        }
        .header-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .header-actions a {
            color: var(--text-dim);
            text-decoration: none;
            padding: 7px 14px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .header-actions a:hover {
            color: var(--text);
            background: var(--surface-3);
            border-color: var(--border-strong);
        }
        .header-actions a.logout:hover {
            color: var(--danger);
            border-color: rgba(248,113,113,0.3);
        }

        /* ===== Status Bar (signature element) ===== */
        .status-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 28px;
            background: linear-gradient(135deg, rgba(52,211,153,0.08) 0%, rgba(52,211,153,0.02) 100%);
            border-left: 3px solid var(--accent);
            border-right: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 12px;
        }
        .status-item { display: flex; flex-direction: column; gap: 2px; }
        .status-label {
            font-size: 0.72rem;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .status-value {
            font-family: var(--mono);
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--accent);
        }
        .status-value.days { font-size: 1.5rem; }
        .status-divider {
            width: 1px;
            height: 36px;
            background: var(--border);
        }

        /* ===== Cards ===== */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-top: none;
            padding: 24px 28px;
        }
        .card:last-of-type { border-radius: 0 0 var(--radius) var(--radius); }
        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
        }
        .card-icon {
            width: 32px; height: 32px;
            background: var(--accent-dim);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .card-title {
            font-size: 1.05rem;
            font-weight: 600;
        }
        .card-desc {
            font-size: 0.8rem;
            color: var(--text-dim);
            margin-top: 1px;
        }

        /* ===== Form ===== */
        .form-group { margin-bottom: 18px; }
        .form-group:last-child { margin-bottom: 0; }
        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 500;
            font-size: 0.88rem;
            color: var(--text);
        }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="date"],
        .form-group input[type="password"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.92rem;
            color: var(--text);
            font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input::placeholder,
        .form-group textarea::placeholder { color: var(--text-faint); }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-dim);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 90px;
            line-height: 1.6;
        }
        .form-group select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238b95a3' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 34px;
        }
        .form-group small {
            display: block;
            margin-top: 5px;
            color: var(--text-dim);
            font-size: 0.78rem;
            line-height: 1.4;
        }
        .form-group input[type="color"] {
            width: 100%;
            height: 42px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            cursor: pointer;
            padding: 3px;
        }
        .form-group input[type="color"]::-webkit-color-swatch-wrapper { padding: 2px; }
        .form-group input[type="color"]::-webkit-color-swatch { border: none; border-radius: 5px; }

        .row {
            display: flex;
            gap: 16px;
        }
        .row .form-group { flex: 1; }

        /* ===== Save Button ===== */
        .btn-save {
            width: 100%;
            padding: 14px;
            background: var(--accent);
            color: #0a1f17;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 8px;
            letter-spacing: 0.02em;
        }
        .btn-save:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(52,211,153,0.25);
        }
        .btn-save:active { transform: translateY(0); }

        /* ===== Modal ===== */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: var(--surface);
            border: 1px solid var(--border-strong);
            padding: 32px 36px;
            border-radius: var(--radius);
            text-align: center;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 24px 64px rgba(0,0,0,0.5);
            animation: modalIn 0.25s ease;
        }
        @keyframes modalIn {
            from { transform: scale(0.92) translateY(10px); opacity: 0; }
            to { transform: scale(1) translateY(0); opacity: 1; }
        }
        .modal-icon {
            font-size: 2.5rem;
            margin-bottom: 12px;
        }
        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .modal-message {
            color: var(--text-dim);
            margin-bottom: 18px;
            font-size: 0.9rem;
        }
        .modal-tip {
            background: var(--accent-dim);
            color: var(--accent);
            border: 1px solid rgba(52,211,153,0.2);
            border-radius: var(--radius-sm);
            padding: 10px 14px;
            font-size: 0.82rem;
            margin-bottom: 18px;
            text-align: left;
            line-height: 1.5;
        }
        .modal-btn {
            padding: 10px 32px;
            background: var(--accent);
            color: #0a1f17;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: background 0.2s;
        }
        .modal-btn:hover { background: var(--accent-hover); }

        /* ===== Toast ===== */
        .message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(-20px);
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            z-index: 10000;
            opacity: 0;
            transition: all 0.3s;
            pointer-events: none;
            max-width: 90%;
        }
        .message.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        .message.error {
            background: rgba(248,113,113,0.15);
            color: var(--danger);
            border: 1px solid rgba(248,113,113,0.3);
        }
        .message.success {
            background: var(--accent-dim);
            color: var(--accent);
            border: 1px solid rgba(52,211,153,0.3);
        }

        /* ===== Responsive ===== */
        @media (max-width: 640px) {
            body { padding: 12px 8px 40px; }
            .header { padding: 16px 18px; flex-direction: column; align-items: flex-start; }
            .header-actions { width: 100%; }
            .header-actions a { flex: 1; justify-content: center; }
            .status-bar { padding: 14px 18px; }
            .status-divider { display: none; }
            .card { padding: 18px; }
            .row { flex-direction: column; gap: 0; }
            .modal-content { padding: 24px 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <div class="header-icon">⏱</div>
                <div>
                    <h1>倒计时设置</h1>
                    <div class="header-sub">管理后台 · 配置外观与内容</div>
                </div>
            </div>
            <div class="header-actions">
                <a href="index.php">← 返回首页</a>
                <a href="admin/review.php">✎ 审核名言</a>
                <a href="edit.php?logout=1" class="logout">⏻ 退出</a>
            </div>
        </div>

        <!-- 状态条：目标日期 + 剩余天数（签名元素） -->
        <div class="status-bar">
            <div class="status-item">
                <span class="status-label">目标日期</span>
                <span class="status-value" id="statusTargetDate">—</span>
            </div>
            <div class="status-divider"></div>
            <div class="status-item">
                <span class="status-label">距目标还有</span>
                <span class="status-value days" id="statusDays">—</span>
            </div>
            <div class="status-divider"></div>
            <div class="status-item">
                <span class="status-label">配置状态</span>
                <span class="status-value" id="statusConfig" style="font-size:0.88rem;color:var(--text-dim);">加载中…</span>
            </div>
        </div>

        <!-- 模态框（保存成功提示） -->
        <div id="successModal" class="modal">
            <div class="modal-content">
                <div class="modal-icon">✅</div>
                <div class="modal-title">保存成功！</div>
                <div class="modal-message">您的设置已保存，刷新页面即可查看效果。</div>
                <div class="modal-tip" id="syncTip" style="display:none;"></div>
                <button class="modal-btn" onclick="closeModal()">确定</button>
            </div>
        </div>

        <!-- 消息提示 -->
        <div id="message" class="message"></div>



        <!-- 主页面表单 -->
        <form onsubmit="saveConfig(event)">
            <!-- Card: 基础设置 -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">📅</div>
                    <div>
                        <div class="card-title">基础设置</div>
                        <div class="card-desc">设置倒计时目标日期</div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="main_target_date">中考日期</label>
                    <input type="date" id="main_target_date" name="target_date" required>
                </div>
            </div>

            <!-- Card: 字体设置 -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">🔤</div>
                    <div>
                        <div class="card-title">字体设置</div>
                        <div class="card-desc">标题、倒计时数字、励志话语的字体样式与云字体</div>
                    </div>
                </div>

                <!-- 标题字体 -->
                <div class="row">
                    <div class="form-group">
                        <label for="main_title_font_size">标题字体大小 (px)</label>
                        <input type="number" id="main_title_font_size" name="title_font_size" min="12" max="200" required>
                        <small>建议值: 24-40</small>
                    </div>
                    <div class="form-group">
                        <label for="main_title_font_color">标题字体颜色</label>
                        <input type="color" id="main_title_font_color" name="title_font_color" value="#ffffff">
                    </div>
                    <div class="form-group">
                        <label for="main_title_font_family">标题字体（CSS字体栈）</label>
                        <input type="text" id="main_title_font_family" name="title_font_family"
                               value="Arial, &quot;Microsoft YaHei&quot;, sans-serif"
                               placeholder="如: Arial, sans-serif">
                        <small>留空使用系统默认字体</small>
                    </div>
                </div>
                <div class="form-group">
                    <label for="main_title_font_url">标题字体URL (可选 - Google Font等)</label>
                    <input type="text" id="main_title_font_url" name="title_font_url"
                           placeholder="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;700&display=swap">
                    <small>填入Google Font等CDN链接，会自动加载</small>
                </div>

                <!-- 倒计时数字字体 -->
                <div class="row">
                    <div class="form-group">
                        <label for="main_countdown_font_size">倒计时数字大小 (px)</label>
                        <input type="number" id="main_countdown_font_size" name="countdown_font_size" min="20" max="400" required>
                        <small>建议值: 40-80</small>
                    </div>
                    <div class="form-group">
                        <label for="main_countdown_font_color">倒计时数字颜色</label>
                        <input type="color" id="main_countdown_font_color" name="countdown_font_color" value="#00a761">
                    </div>
                    <div class="form-group">
                        <label for="main_countdown_font_family">倒计时字体（CSS字体栈）</label>
                        <input type="text" id="main_countdown_font_family" name="countdown_font_family"
                               value="&quot;Courier New&quot;, monospace"
                               placeholder="如: Courier New, monospace">
                        <small>建议使用等宽字体</small>
                    </div>
                </div>
                <div class="form-group">
                    <label for="main_countdown_font_url">倒计时字体URL (可选 - Google Font等)</label>
                    <input type="text" id="main_countdown_font_url" name="countdown_font_url"
                           placeholder="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;700&display=swap">
                    <small>填入字体CDN链接，用于显示数字</small>
                </div>

                <!-- 励志话语字体 -->
                <div class="row">
                    <div class="form-group">
                        <label for="main_message_font_size">励志话语大小 (px)</label>
                        <input type="number" id="main_message_font_size" name="message_font_size" min="12" max="200" required>
                        <small>建议值: 16-24</small>
                    </div>
                    <div class="form-group">
                        <label for="main_message_font_color">励志话语颜色</label>
                        <input type="color" id="main_message_font_color" name="message_font_color" value="#ffffff">
                    </div>
                    <div class="form-group">
                        <label for="main_message_font_family">励志话语字体（CSS字体栈）</label>
                        <input type="text" id="main_message_font_family" name="message_font_family"
                               value="Arial, &quot;Microsoft YaHei&quot;, sans-serif"
                               placeholder="如: Arial, sans-serif">
                        <small>建议使用易读的字体</small>
                    </div>
                </div>
                <div class="form-group">
                    <label for="main_message_font_url">名言字体URL (可选 - Google Font等)</label>
                    <input type="text" id="main_message_font_url" name="message_font_url"
                           placeholder="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;700&display=swap">
                    <small>填入字体CDN链接，用于显示励志话语</small>
                </div>
            </div>

            <!-- Card: 背景设置 -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">🎨</div>
                    <div>
                        <div class="card-title">背景设置</div>
                        <div class="card-desc">纯色背景或自定义背景图片</div>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group">
                        <label for="main_bg_color">背景颜色</label>
                        <input type="color" id="main_bg_color" name="bg_color" value="#1a3a4e">
                    </div>
                    <div class="form-group" style="flex:2;">
                        <label for="main_bg_image">背景图片URL (可选)</label>
                        <input type="text" id="main_bg_image" name="bg_image" placeholder="https://example.com/image.jpg">
                        <small>留空则使用背景颜色，填写URL则显示背景图片</small>
                    </div>
                </div>
                <div class="form-group">
                    <label for="main_bg_image_mode">背景图片显示方式</label>
                    <select id="main_bg_image_mode" name="bg_image_mode">
                        <option value="cover">覆盖 (Cover) - 图片填满屏幕，可能裁剪</option>
                        <option value="contain">包含 (Contain) - 完整显示图片，可能有黑边</option>
                    </select>
                    <small>4K高清图片建议使用"包含"模式，避免模糊</small>
                </div>
            </div>

            <!-- Card: 内容设置 -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">💬</div>
                    <div>
                        <div class="card-title">内容设置</div>
                        <div class="card-desc">励志话语、轮播节奏与布局间距</div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="main_messages">励志话语（多条用 | 分隔）</label>
                    <textarea id="main_messages" name="messages" rows="4" required></textarea>
                    <small>多条话语用竖线 | 分隔，例如：话语1|话语2|话语3</small>
                </div>
                <div class="row">
                    <div class="form-group">
                        <label for="main_message_container_width">名言容器宽度</label>
                        <input type="text" id="main_message_container_width" name="message_container_width"
                               placeholder="例如: 90%, 800px, 60rem" value="90%">
                        <small>可以是百分比(90%)或固定值(800px)</small>
                    </div>
                    <div class="form-group">
                        <label for="main_message_interval">名言翻页间隔 (毫秒)</label>
                        <input type="number" id="main_message_interval" name="message_interval"
                               min="1000" max="60000" step="500" value="5000">
                        <small>每多少毫秒切换一条名言，1000毫秒=1秒</small>
                    </div>
                    <div class="form-group">
                        <label for="main_motivation_gap">名言与倒计时间距 (px)</label>
                        <input type="number" id="main_motivation_gap" name="motivation_gap"
                               min="0" max="200" step="1" value="4">
                        <small>控制名言和倒计时之间的距离，0-200像素</small>
                    </div>
                </div>
            </div>

            <!-- Card: 当前时间设置 -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">⏰</div>
                    <div>
                        <div class="card-title">当前时间显示</div>
                        <div class="card-desc">页面右下角实时时钟的样式</div>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group">
                        <label for="main_time_font_size">时间字体大小 (px)</label>
                        <input type="number" id="main_time_font_size" name="time_font_size" min="8" max="80" value="13">
                        <small>建议值: 10-18</small>
                    </div>
                    <div class="form-group">
                        <label for="main_time_font_color">时间字体颜色</label>
                        <input type="color" id="main_time_font_color" name="time_font_color" value="#ffffff">
                    </div>
                    <div class="form-group">
                        <label for="main_time_font_family">时间字体（CSS字体栈）</label>
                        <input type="text" id="main_time_font_family" name="time_font_family"
                               value="&quot;Courier New&quot;, monospace"
                               placeholder="如: Courier New, monospace">
                        <small>建议使用等宽字体</small>
                    </div>
                </div>
                <div class="form-group">
                    <label for="main_time_bottom">时间底部距离 (px)</label>
                    <input type="number" id="main_time_bottom" name="time_bottom" min="0" max="200" value="12">
                    <small>离页面底部的像素距离，如果被遮挡可以调大此值</small>
                </div>

                <button type="submit" class="btn-save">保存设置</button>
            </div>

            <!-- Card: 内容池管理 -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">📚</div>
                    <div>
                        <div class="card-title">内容池管理</div>
                        <div class="card-desc">多模块轮播：励志语、名人名言、社会考点与在线源，按权重混合播放</div>
                    </div>
                </div>

                <div class="card-title" style="font-size:0.95rem; margin-bottom:4px;">模块配置</div>
                <small style="color:var(--text-dim); display:block; margin-bottom:6px;">权重（1-100）越大出现越频繁；在线模块可改订阅源与缓存时长。励志语内容请在「内容设置」中编辑，投稿审核通过后自动加入。</small>
                <div id="contentModules"></div>

                <div class="card-title" style="font-size:0.95rem; margin:18px 0 10px;">本地内容编辑（每行一条）</div>
                <div class="form-group">
                    <label for="content_quote">名人名言</label>
                    <textarea id="content_quote" rows="5" placeholder="每行一条，格式：[标签] 内容"></textarea>
                    <small>展示为大字轮播；支持 <code>[标签] 内容</code> 格式，标签可选</small>
                </div>
                <div class="form-group">
                    <label for="content_knowledge">社会考点</label>
                    <textarea id="content_knowledge" rows="8" placeholder="每行一条，格式：[标签] 考点 :: 要点1；要点2"></textarea>
                    <small>考点以卡片展示（标签 + 考点 + 要点），适合投影/大屏。格式：<code>[九上·道法] 改革开放的意义 :: ①强国之路 ②关键一招</code>，要点用 <code>::</code> 分隔；批量粘贴课本提纲即可，无需逐个打字</small>
                </div>

                <button type="button" class="btn-save" onclick="saveContent()">保存内容池</button>
            </div>
        </form>

    </div>

    <script>
        // 认证token由PHP生成，保存接口必须携带（2026-08-10 安全加固）
        const AUTH_TOKEN = '<?= htmlspecialchars($authToken ?? '', ENT_QUOTES, 'UTF-8') ?>';

        let mainConfig = {};

        // 页面加载时获取配置
        document.addEventListener('DOMContentLoaded', async () => {
            await loadConfig();
            loadContentAdmin();
        });

        // 加载配置（主页面与秒数页面共用一套配置）
        async function loadConfig() {
            try {
                const res = await fetch('api/get_config.php');
                mainConfig = await res.json();
                fillForm(mainConfig);
                updateStatusBar(mainConfig);
            } catch (error) {
                showMessage('加载配置失败: ' + error.message, 'error');
                updateStatusBar(null);
            }
        }

        // 填充表单
        function fillForm(config) {
            const prefix = 'main_';

            // 基础字段
            document.getElementById(prefix + 'target_date').value = config.target_date || '2026-06-26';

            // 标题字体
            document.getElementById(prefix + 'title_font_size').value = config.title_font_size || '32';
            document.getElementById(prefix + 'title_font_color').value = config.title_font_color || '#ffffff';
            document.getElementById(prefix + 'title_font_family').value = config.title_font_family || 'Arial, "Microsoft YaHei", sans-serif';
            document.getElementById(prefix + 'title_font_url').value = config.title_font_url || '';

            // 倒计时字体
            document.getElementById(prefix + 'countdown_font_size').value = config.countdown_font_size || '55';
            document.getElementById(prefix + 'countdown_font_color').value = config.countdown_font_color || '#00a761';
            document.getElementById(prefix + 'countdown_font_family').value = config.countdown_font_family || '"Courier New", monospace';
            document.getElementById(prefix + 'countdown_font_url').value = config.countdown_font_url || '';

            // 背景
            document.getElementById(prefix + 'bg_color').value = config.bg_color || '#1a3a4e';
            document.getElementById(prefix + 'bg_image').value = config.bg_image || '';
            document.getElementById(prefix + 'bg_image_mode').value = config.bg_image_mode || 'cover';

            // 消息字体
            document.getElementById(prefix + 'message_font_size').value = config.message_font_size || '20';
            document.getElementById(prefix + 'message_font_color').value = config.message_font_color || '#ffffff';
            document.getElementById(prefix + 'message_font_family').value = config.message_font_family || 'Arial, "Microsoft YaHei", sans-serif';
            document.getElementById(prefix + 'message_font_url').value = config.message_font_url || '';

            // 轮播设置
            document.getElementById(prefix + 'message_interval').value = config.message_interval || '5000';
            document.getElementById(prefix + 'message_container_width').value = config.message_container_width || '90%';
            document.getElementById(prefix + 'motivation_gap').value = config.motivation_gap || '4';
            document.getElementById(prefix + 'messages').value = config.messages || '';

            // 当前时间设置
            document.getElementById(prefix + 'time_font_size').value = config.time_font_size || '13';
            document.getElementById(prefix + 'time_font_color').value = config.time_font_color || '#ffffff';
            document.getElementById(prefix + 'time_font_family').value = config.time_font_family || '"Courier New", monospace';
            document.getElementById(prefix + 'time_bottom').value = config.time_bottom || '12';
        }

        // 更新顶部状态条（目标日期 + 剩余天数）
        function updateStatusBar(config) {
            var dateEl = document.getElementById('statusTargetDate');
            var daysEl = document.getElementById('statusDays');
            var cfgEl = document.getElementById('statusConfig');
            if (!config || !config.target_date) {
                if (dateEl) dateEl.textContent = '—';
                if (daysEl) daysEl.textContent = '—';
                if (cfgEl) { cfgEl.textContent = '加载失败'; cfgEl.style.color = 'var(--danger)'; }
                return;
            }
            if (dateEl) dateEl.textContent = config.target_date;
            if (daysEl) {
                var target = new Date(config.target_date + 'T00:00:00').getTime();
                var days = Math.ceil((target - Date.now()) / 86400000);
                daysEl.textContent = Math.max(0, days) + ' 天';
            }
            if (cfgEl) { cfgEl.textContent = '已同步'; cfgEl.style.color = 'var(--accent)'; }
        }

        // 保存配置（主页面与秒数页面共用一套配置）
        async function saveConfig(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);

            const config = {
                target_date: formData.get('target_date'),
                title_font_size: formData.get('title_font_size'),
                title_font_color: formData.get('title_font_color'),
                title_font_family: formData.get('title_font_family'),
                title_font_url: formData.get('title_font_url'),
                countdown_font_size: formData.get('countdown_font_size'),
                countdown_font_color: formData.get('countdown_font_color'),
                countdown_font_family: formData.get('countdown_font_family'),
                countdown_font_url: formData.get('countdown_font_url'),
                bg_color: formData.get('bg_color'),
                bg_image: formData.get('bg_image'),
                bg_image_mode: formData.get('bg_image_mode'),
                message_font_size: formData.get('message_font_size'),
                message_font_color: formData.get('message_font_color'),
                message_font_family: formData.get('message_font_family'),
                message_font_url: formData.get('message_font_url'),
                message_interval: formData.get('message_interval'),
                message_container_width: formData.get('message_container_width'),
                motivation_gap: formData.get('motivation_gap'),
                time_font_size: formData.get('time_font_size'),
                time_font_color: formData.get('time_font_color'),
                time_font_family: formData.get('time_font_family'),
                time_bottom: formData.get('time_bottom'),
                messages: formData.get('messages')
            };

            try {
                // 发送单个请求保存所有配置
                const response = await fetch('api/save_config_batch.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-Auth-Token': AUTH_TOKEN},
                    body: JSON.stringify({
                        page_type: 'main',
                        config: config
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // 更新本地配置
                    mainConfig = config;
                    updateStatusBar(config);

                    // 显示成功模态框
                    showSuccessModal();
                } else {
                    showMessage('保存失败: ' + result.message, 'error');
                }
            } catch (error) {
                showMessage('保存失败: ' + error.message, 'error');
            }
        }

        // 显示成功模态框
        function showSuccessModal() {
            const tip = document.getElementById('syncTip');
            if (tip) {
                tip.textContent = '主页面与秒数页面共用一套配置，均已生效。';
                tip.className = 'modal-tip green';
                tip.style.display = 'block';
            }
            const modal = document.getElementById('successModal');
            modal.classList.add('show');
        }

        // 关闭模态框
        function closeModal() {
            const modal = document.getElementById('successModal');
            modal.classList.remove('show');
        }

        // ===== 内容池管理 =====
        let contentModulesData = [];
        let contentItemsData = {};

        // 加载内容池配置（模块 + 本地条目）
        async function loadContentAdmin() {
            try {
                const res = await fetch('api/get_content_admin.php', {headers: {'X-Auth-Token': AUTH_TOKEN}});
                const data = await res.json();
                if (!data || data.error || !Array.isArray(data.modules)) {
                    throw new Error(data && data.message ? data.message : '数据无效');
                }
                contentModulesData = data.modules;
                contentItemsData = data.items || {};
                renderContentModules();
                fillContentTextarea('content_quote', 'quote');
                fillContentTextarea('content_knowledge', 'knowledge');
            } catch (error) {
                showMessage('内容池加载失败: ' + error.message, 'error');
            }
        }

        // 渲染模块配置行
        function renderContentModules() {
            const box = document.getElementById('contentModules');
            if (!box) return;
            box.innerHTML = '';
            contentModulesData.forEach(m => {
                const row = document.createElement('div');
                row.style.cssText = 'display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:10px 0;border-bottom:1px solid var(--border);';

                const nameBox = document.createElement('div');
                nameBox.style.flex = '1';
                nameBox.style.minWidth = '120px';
                const name = document.createElement('div');
                name.style.fontWeight = '600';
                name.style.fontSize = '0.92rem';
                name.textContent = m.module_name;
                const typeBadge = document.createElement('small');
                typeBadge.style.color = 'var(--text-dim)';
                typeBadge.textContent = m.module_type === 'online' ? '在线源 · 自动获取' : '本地内容';
                nameBox.appendChild(name);
                nameBox.appendChild(typeBadge);

                const wBox = document.createElement('div');
                wBox.style.textAlign = 'center';
                const wLabel = document.createElement('small');
                wLabel.textContent = '权重';
                const wInp = document.createElement('input');
                wInp.type = 'number';
                wInp.min = 1;
                wInp.max = 100;
                wInp.value = m.weight;
                wInp.id = 'w_' + m.module_key;
                wInp.style.width = '64px';
                wBox.appendChild(wLabel);
                wBox.appendChild(wInp);

                const eBox = document.createElement('div');
                eBox.style.textAlign = 'center';
                const eLabel = document.createElement('small');
                eLabel.textContent = '启用';
                const eInp = document.createElement('input');
                eInp.type = 'checkbox';
                eInp.checked = !!m.enabled;
                eInp.id = 'e_' + m.module_key;
                eBox.appendChild(eLabel);
                eBox.appendChild(eInp);

                const uBox = document.createElement('div');
                uBox.style.flex = '2';
                uBox.style.minWidth = '200px';
                const uLabel = document.createElement('small');
                uLabel.textContent = '在线源URL（仅在线模块）';
                const uInp = document.createElement('input');
                uInp.type = 'text';
                uInp.placeholder = 'https://...';
                uInp.value = m.source_url || '';
                uInp.id = 'u_' + m.module_key;
                uBox.appendChild(uLabel);
                uBox.appendChild(uInp);
                if (m.module_type !== 'online') {
                    uInp.disabled = true;
                    uInp.placeholder = '本地模块无需填';
                }

                const tBox = document.createElement('div');
                tBox.style.textAlign = 'center';
                const tLabel = document.createElement('small');
                tLabel.textContent = '缓存秒';
                const tInp = document.createElement('input');
                tInp.type = 'number';
                tInp.min = 60;
                tInp.max = 604800;
                tInp.value = m.ttl_seconds;
                tInp.id = 't_' + m.module_key;
                tInp.style.width = '76px';
                tBox.appendChild(tLabel);
                tBox.appendChild(tInp);
                if (m.module_type !== 'online') {
                    tInp.disabled = true;
                }

                row.appendChild(nameBox);
                row.appendChild(wBox);
                row.appendChild(eBox);
                row.appendChild(uBox);
                row.appendChild(tBox);
                box.appendChild(row);
            });
        }

        // 条目 -> textarea 文本
        function fillContentTextarea(elId, moduleKey) {
            const items = contentItemsData[moduleKey] || [];
            const lines = items.map(it => {
                const tag = it.tag ? '[' + it.tag + '] ' : '';
                const detail = it.detail ? ' :: ' + it.detail : '';
                return tag + (it.content || '') + detail;
            });
            const el = document.getElementById(elId);
            if (el) el.value = lines.join('\n');
        }

        // textarea 文本 -> 条目数组（每行一条：[标签] 内容 :: 要点）
        function parseContentTextarea(text) {
            return String(text || '').split('\n').map(line => line.trim()).filter(Boolean).map(line => {
                let tag = '';
                let rest = line;
                const tagMatch = rest.match(/^\[([^\]]+)\]\s*(.*)$/);
                if (tagMatch) {
                    tag = tagMatch[1].trim();
                    rest = tagMatch[2].trim();
                }
                let content = rest;
                let detail = '';
                const idx = rest.indexOf('::');
                if (idx >= 0) {
                    content = rest.slice(0, idx).trim();
                    detail = rest.slice(idx + 2).trim();
                }
                return {content: content, tag: tag || null, detail: detail || null};
            }).filter(it => it.content);
        }

        // 保存内容池
        async function saveContent() {
            const modules = contentModulesData.map(m => ({
                module_key: m.module_key,
                weight: parseInt(document.getElementById('w_' + m.module_key).value, 10) || 10,
                enabled: document.getElementById('e_' + m.module_key).checked ? 1 : 0,
                source_url: document.getElementById('u_' + m.module_key).value.trim() || null,
                source_type: m.source_type || null,
                ttl_seconds: parseInt(document.getElementById('t_' + m.module_key).value, 10) || 86400
            }));
            const items = {
                quote: parseContentTextarea(document.getElementById('content_quote').value),
                knowledge: parseContentTextarea(document.getElementById('content_knowledge').value)
            };
            try {
                const response = await fetch('api/save_content.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-Auth-Token': AUTH_TOKEN},
                    body: JSON.stringify({modules: modules, items: items})
                });
                const result = await response.json();
                if (result.success) {
                    showMessage('内容池已保存，打开倒计时页面即可生效', 'success');
                } else {
                    showMessage('保存失败: ' + result.message, 'error');
                }
            } catch (error) {
                showMessage('保存失败: ' + error.message, 'error');
            }
        }

        // 显示错误消息
        function showMessage(text, type) {
            const messageEl = document.getElementById('message');
            messageEl.textContent = text;
            messageEl.className = 'message show ' + type;

            setTimeout(() => {
                messageEl.classList.remove('show');
            }, 3000);
        }

        // 点击模态框背景关闭
        document.getElementById('successModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
