<?php
require_once __DIR__ . '/../includes/EnvLoader.php';
require_once __DIR__ . '/../includes/Auth.php';

$auth = Auth::getInstance();

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($auth->login($_POST['password'])) {
        // 登录成功，刷新页面以加载认证token
        header('Location: review.php');
        exit;
    } else {
        $err = $auth->getLastError();
        if ($err && $err['code'] === 'locked') {
            $minutes = (int)ceil(($err['remaining'] ?? 900) / 60);
            $loginError = '尝试次数过多，请 ' . max(1, $minutes) . ' 分钟后再试';
        } elseif ($err && $err['code'] === 'db_unavailable') {
            $loginError = '系统暂时不可用，请稍后再试';
        } elseif ($err && $err['code'] === 'password') {
            $loginError = '密码错误';
        } else {
            $loginError = '系统未配置管理员密码，请联系部署者';
        }
    }
}

if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: review.php');
    exit;
}

// 使用Auth类统一检查登录状态
$isLoggedIn = $auth->isAuthenticated();
$authToken = $isLoggedIn ? $auth->getAuthToken() : '';

if (!$isLoggedIn):
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>审核登录</title>
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
        .login-box h1 { text-align: center; color: #333; margin-bottom: 25px; }
        .login-box input[type="password"] {
            width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0;
            border-radius: 5px; font-size: 1rem; margin-bottom: 15px;
        }
        .login-box input[type="password"]:focus { outline: none; border-color: #667eea; }
        .login-box button {
            width: 100%; padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; border-radius: 5px; font-size: 1rem; cursor: pointer;
        }
        .error-msg { color: #e74c3c; text-align: center; margin-bottom: 15px; }
        .hint { text-align: center; color: #aaa; margin-top: 15px; font-size: 0.8rem; }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>名言审核登录</h1>
        <?php if ($loginError): ?>
            <div class="error-msg"><?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="password" name="password" placeholder="请输入管理密码" required autofocus>
            <button type="submit">登录</button>
        </form>
        <div class="hint"><a href="../index.php" style="color: #667eea; text-decoration: none;">返回首页</a></div>
    </div>
</body>
</html>
<?php exit; endif; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>名言审核</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Microsoft YaHei', 'PingFang SC', Arial, sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 1.5rem; }
        .header .links a {
            color: white; text-decoration: none;
            padding: 8px 16px; background: rgba(255,255,255,0.2);
            border-radius: 5px; margin-left: 10px;
        }
        .header .links a:hover { background: rgba(255,255,255,0.3); }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .tab-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.95rem;
            background: white;
            color: #666;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .tab-btn.active {
            background: #667eea;
            color: white;
        }
        .tab-btn .count {
            display: inline-block;
            background: #e74c3c;
            color: white;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            margin-left: 5px;
        }
        .tab-btn.active .count { background: rgba(255,255,255,0.3); }

        .quote-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .quote-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .quote-card.pending {
            border-left: 4px solid #f39c12;
        }
        .quote-card.approved {
            border-left: 4px solid #27ae60;
        }
        .quote-card.rejected {
            border-left: 4px solid #e74c3c;
            opacity: 0.6;
        }
        .quote-content {
            font-size: 1.2rem;
            color: #333;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        .quote-meta {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 15px;
        }
        .quote-actions {
            display: flex;
            gap: 10px;
        }
        .btn-approve, .btn-reject {
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .btn-approve {
            background: #27ae60;
            color: white;
        }
        .btn-approve:hover { background: #2ecc71; }
        .btn-reject {
            background: #e74c3c;
            color: white;
        }
        .btn-reject:hover { background: #c0392b; }
        .no-data {
            text-align: center;
            color: #888;
            padding: 40px;
            font-size: 1.1rem;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #888;
        }
        .empty-state .icon { font-size: 3rem; margin-bottom: 15px; }
        .message {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: none;
        }
        .message.success { display: block; background: #d4edda; color: #155724; }
        .message.error { display: block; background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="header">
        <h1>名言审核管理</h1>
        <div class="links">
            <a href="../index.php">返回首页</a>
            <a href="../edit.php">设置页面</a>
            <a href="?logout=1">退出登录</a>
        </div>
    </div>

    <div id="message" class="message"></div>

    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('pending')">
            待审核 <span id="pendingCount" class="count">0</span>
        </button>
        <button class="tab-btn" onclick="switchTab('approved')">已通过</button>
        <button class="tab-btn" onclick="switchTab('rejected')">已拒绝</button>
        <button class="tab-btn" onclick="switchTab('all')">全部</button>
    </div>

    <div id="quoteList" class="quote-list">
        <div class="empty-state">
            <div class="icon">📝</div>
            <p>加载中...</p>
        </div>
    </div>

    <script>
        // 认证token由PHP生成，不再使用明文密码
        const AUTH_TOKEN = '<?= htmlspecialchars($authToken, ENT_QUOTES, 'UTF-8') ?>';
        let allQuotes = [];

        async function loadQuotes() {
            try {
                const res = await fetch('../api/get_pending_quotes.php?status=all', {
                    headers: {'X-Auth-Token': AUTH_TOKEN}
                });
                const data = await res.json();
                if (data.success) {
                    allQuotes = data.data;
                    updatePendingCount();
                    switchTab('pending');
                } else {
                    showMessage('加载失败: ' + data.message, 'error');
                }
            } catch (err) {
                showMessage('加载失败: ' + err.message, 'error');
            }
        }

        function updatePendingCount() {
            const count = allQuotes.filter(q => q.status === 'pending').length;
            document.getElementById('pendingCount').textContent = count;
        }

        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            // 找到对应标签页的按钮并激活
            const buttons = document.querySelectorAll('.tab-btn');
            const tabMap = { 'pending': 0, 'approved': 1, 'rejected': 2, 'all': 3 };
            const idx = tabMap[tab] || 0;
            if (buttons[idx]) buttons[idx].classList.add('active');

            let filtered = allQuotes;
            if (tab !== 'all') {
                filtered = allQuotes.filter(q => q.status === tab);
            }

            const container = document.getElementById('quoteList');
            if (filtered.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="icon">📭</div><p>暂无数据</p></div>';
                return;
            }

            container.innerHTML = filtered.map(q => `
                <div class="quote-card ${q.status}">
                    <div class="quote-content">${escapeHtml(q.content)}</div>
                    <div class="quote-meta">
                        投稿人: ${q.submitter_name || '匿名'} | 时间: ${q.created_at}
                        ${q.reviewed_at ? ' | 审核于: ' + q.reviewed_at : ''}
                    </div>
                    ${q.status === 'pending' ? `
                    <div class="quote-actions">
                        <button class="btn-approve" onclick="reviewQuote(${q.id}, 'approve')">通过</button>
                        <button class="btn-reject" onclick="reviewQuote(${q.id}, 'reject')">拒绝</button>
                    </div>` : `
                    <div style="font-size:0.85rem; color:#888;">
                        ${q.status === 'approved' ? '已通过' : '已拒绝'}
                    </div>`}
                </div>
            `).join('');
        }

        async function reviewQuote(id, action) {
            try {
                const res = await fetch('../api/approve_quote.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Auth-Token': AUTH_TOKEN
                    },
                    body: JSON.stringify({id, action})
                });
                const data = await res.json();
                if (data.success) {
                    showMessage(action === 'approve' ? '已通过并合并到页面' : '已拒绝', 'success');
                    await loadQuotes();
                } else {
                    showMessage('操作失败: ' + data.message, 'error');
                }
            } catch (err) {
                showMessage('操作失败: ' + err.message, 'error');
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showMessage(text, type) {
            const el = document.getElementById('message');
            el.textContent = text;
            el.className = 'message ' + type;
            setTimeout(() => { el.style.display = 'none'; }, 3000);
        }

        document.addEventListener('DOMContentLoaded', loadQuotes);
    </script>
</body>
</html>
