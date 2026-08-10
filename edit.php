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
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 360px;
            max-width: 90%;
        }
        .login-box h1 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
            font-size: 1.5rem;
        }
        .login-box p {
            text-align: center;
            color: #888;
            margin-bottom: 25px;
            font-size: 0.9rem;
        }
        .login-box input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 1rem;
            margin-bottom: 15px;
        }
        .login-box input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        .login-box button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            font-weight: 500;
        }
        .login-box button:hover {
            opacity: 0.9;
        }
        .error-msg {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        .hint {
            text-align: center;
            color: #aaa;
            margin-top: 15px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>管理员登录</h1>
        <p>请输入管理密码进入设置页面</p>
        <?php if ($loginError): ?>
            <div class="error-msg"><?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="password" name="password" placeholder="请输入管理密码" required autofocus>
            <button type="submit">登录</button>
        </form>
        <div class="hint">
            <a href="index.php" style="color: #667eea; text-decoration: none;">返回首页</a>
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #00a761 0%, #00d4aa 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.8rem;
        }

        .header a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 5px;
            transition: background 0.3s;
        }

        .header a:hover {
            background: rgba(255,255,255,0.3);
        }

        /* 标签页 */
        .tabs {
            display: flex;
            background: #f5f5f5;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            border: none;
            background: transparent;
            font-size: 1rem;
            color: #666;
            transition: all 0.3s;
            font-weight: 500;
        }

        .tab:hover {
            background: #e8e8e8;
        }

        .tab.active {
            background: white;
            color: #00a761;
            border-bottom: 3px solid #00a761;
        }

        /* 表单区域 */
        .form-container {
            padding: 30px;
            display: none;
        }

        .form-container.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="date"],
        .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #00a761;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #888;
            font-size: 0.85rem;
        }

        .color-inputs,
        .font-section {
            display: flex;
            gap: 20px;
        }

        .color-inputs .form-group,
        .font-section .form-group {
            flex: 1;
        }

        .form-group input[type="color"] {
            width: 100%;
            height: 40px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            cursor: pointer;
            padding: 2px;
        }

        /* 保存按钮 */
        .btn-save {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #00a761 0%, #00d4aa 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,167,97,0.3);
        }

        .btn-save:active {
            transform: translateY(0);
        }

        /* 分区标题 */
        .section-title {
            background: #f8f9fa;
            padding: 10px 15px;
            margin: 20px -30px 20px -30px;
            font-weight: 600;
            color: #333;
            border-left: 4px solid #00a761;
        }

        /* 同步设置区域 */
        .sync-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .sync-section h3 {
            margin-bottom: 15px;
            color: #333;
            font-size: 1.1rem;
        }

        .sync-fields {
            display: flex;
            gap: 20px;
        }

        .sync-fields .form-group {
            flex: 1;
        }

        /* 模态框（保存成功提示） */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px 40px;
            border-radius: 10px;
            text-align: center;
            animation: scaleIn 0.3s ease;
            max-width: 400px;
        }

        @keyframes scaleIn {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .modal-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .modal-title {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 10px;
        }

        .modal-message {
            color: #666;
            margin-bottom: 20px;
        }

        .modal-tip {
            background: #fff8e1;
            color: #8a6d1a;
            border: 1px solid #f0e0a0;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.85rem;
            margin-bottom: 16px;
            text-align: left;
            line-height: 1.5;
        }

        .modal-tip.green {
            background: #e8f5e9;
            color: #2e7d32;
            border-color: #c8e6c9;
        }

        .modal-btn {
            padding: 10px 30px;
            background: #00a761;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s;
        }

        .modal-btn:hover {
            background: #00d4aa;
        }

        /* 响应式 */
        @media (max-width: 768px) {
            .color-inputs,
            .font-section {
                flex-direction: column;
                gap: 15px;
            }

            .sync-fields {
                flex-direction: column;
                gap: 15px;
            }

            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .section-title {
                margin: 20px -15px 20px -15px;
                padding: 10px 15px;
            }

            .form-container {
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>倒计时设置</h1>
            <a href="index.php">返回首页</a>
            <a href="admin/review.php">审核名言</a>
            <a href="edit.php?logout=1">退出登录</a>
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
        <div id="main-form" class="form-container active">
            <form onsubmit="saveConfig(event)">
                <div class="form-group">
                    <label for="main_target_date">高考日期</label>
                    <input type="date" id="main_target_date" name="target_date" required>
                </div>

                <!-- 字体设置分区 -->
                <div class="section-title">字体设置</div>

                <!-- 标题字体 -->
                <div class="font-section">
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

                <!-- 标题字体URL（云字体） -->
                <div class="form-group">
                    <label for="main_title_font_url">标题字体URL (可选 - Google Font等)</label>
                    <input type="text" id="main_title_font_url" name="title_font_url"
                           placeholder="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;700&display=swap"
                           style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 5px;">
                    <small>填入Google Font等CDN链接，会自动加载</small>
                </div>

                <!-- 倒计时数字字体 -->
                <div class="font-section">
                    <div class="form-group">
                        <label for="main_countdown_font_size">倒计时数字字体大小 (px)</label>
                        <input type="number" id="main_countdown_font_size" name="countdown_font_size" min="20" max="400" required>
                        <small>建议值: 40-80</small>
                    </div>
                    <div class="form-group">
                        <label for="main_countdown_font_color">倒计时数字颜色</label>
                        <input type="color" id="main_countdown_font_color" name="countdown_font_color" value="#00a761">
                    </div>
                    <div class="form-group">
                        <label for="main_countdown_font_family">倒计时数字字体（CSS字体栈）</label>
                        <input type="text" id="main_countdown_font_family" name="countdown_font_family"
                               value="&quot;Courier New&quot;, monospace"
                               placeholder="如: Courier New, monospace">
                        <small>建议使用等宽字体</small>
                    </div>
                </div>

                <!-- 倒计时字体URL（云字体） -->
                <div class="form-group">
                    <label for="main_countdown_font_url">倒计时字体URL (可选 - Google Font等)</label>
                    <input type="text" id="main_countdown_font_url" name="countdown_font_url"
                           placeholder="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;700&display=swap"
                           style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 5px;">
                    <small>填入字体CDN链接，用于显示数字</small>
                </div>

                <!-- 励志话语字体 -->
                <div class="font-section">
                    <div class="form-group">
                        <label for="main_message_font_size">励志话语字体大小 (px)</label>
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

                <!-- 名言字体URL（云字体） -->
                <div class="form-group">
                    <label for="main_message_font_url">名言字体URL (可选 - Google Font等)</label>
                    <input type="text" id="main_message_font_url" name="message_font_url"
                           placeholder="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;700&display=swap"
                           style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 5px;">
                    <small>填入字体CDN链接，用于显示励志话语</small>
                </div>

                <!-- 背景设置分区 -->
                <div class="section-title">背景设置</div>

                <div class="color-inputs">
                    <div class="form-group">
                        <label for="main_bg_color">背景颜色</label>
                        <input type="color" id="main_bg_color" name="bg_color" value="#1a3a4e">
                    </div>
                </div>

                <div class="form-group">
                    <label for="main_bg_image">背景图片URL (可选)</label>
                    <input type="text" id="main_bg_image" name="bg_image" placeholder="https://example.com/image.jpg">
                    <small>留空则使用背景颜色，填写URL则显示背景图片</small>
                </div>

                <div class="form-group">
                    <label for="main_bg_image_mode">背景图片显示方式</label>
                    <select id="main_bg_image_mode" name="bg_image_mode" style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 5px;">
                        <option value="cover">覆盖 (Cover) - 图片填满屏幕，可能裁剪</option>
                        <option value="contain">包含 (Contain) - 完整显示图片，可能有黑边</option>
                    </select>
                    <small>4K高清图片建议使用"包含"模式，避免模糊</small>
                </div>

                <!-- 内容设置分区 -->
                <div class="section-title">内容设置</div>

                <div class="form-group">
                    <label for="main_messages">励志话语（多条用 | 分隔）</label>
                    <textarea id="main_messages" name="messages" rows="4" required></textarea>
                    <small>多条话语用竖线 | 分隔，例如：话语1|话语2|话语3</small>
                </div>

                <div class="font-section">
                    <div class="form-group">
                        <label for="main_message_container_width">名言容器宽度</label>
                        <input type="text" id="main_message_container_width" name="message_container_width"
                               placeholder="例如: 90%, 800px, 60rem" value="90%"
                               style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 5px;">
                        <small>控制名言显示区域的宽度，可以是百分比(90%)或固定值(800px)</small>
                    </div>
                    <div class="form-group">
                        <label for="main_message_interval">名言翻页间隔 (毫秒)</label>
                        <input type="number" id="main_message_interval" name="message_interval"
                               min="1000" max="60000" step="500" value="5000"
                               style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 5px;">
                        <small>每多少毫秒切换一条名言，1000毫秒=1秒</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="main_motivation_gap">名言与倒计时间距 (px)</label>
                    <input type="number" id="main_motivation_gap" name="motivation_gap"
                           min="0" max="200" step="1" value="4"
                           style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 5px;">
                    <small>控制名言和倒计时之间的距离，0-200像素</small>
                </div>

                <!-- 当前时间设置 -->
                <div class="section-title">当前时间设置</div>

                <div class="font-section">
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
            </form>
        </div>

    </div>

    <script>
        // 认证token由PHP生成，保存接口必须携带（2026-08-10 安全加固）
        const AUTH_TOKEN = '<?= htmlspecialchars($authToken ?? '', ENT_QUOTES, 'UTF-8') ?>';

        let mainConfig = {};

        // 页面加载时获取配置
        document.addEventListener('DOMContentLoaded', async () => {
            await loadConfig();
        });

        // 加载配置（主页面与秒数页面共用一套配置）
        async function loadConfig() {
            try {
                const res = await fetch('api/get_config.php');
                mainConfig = await res.json();
                fillForm(mainConfig);
            } catch (error) {
                showMessage('加载配置失败: ' + error.message, 'error');
            }
        }

        // 填充表单
        function fillForm(config) {
            const prefix = 'main_';

            // 基础字段
            document.getElementById(prefix + 'target_date').value = config.target_date || '2027-06-07';

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
