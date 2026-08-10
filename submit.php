<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>投稿励志名言</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            font-family: 'Microsoft YaHei', 'PingFang SC', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
            display: flex;
            flex-direction: row;
            justify-content: center;
            align-items: flex-start;
            overflow-y: auto;
        }
        .container {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
        }
        .header h1 {
            font-size: 1.5rem;
        }
        .header p {
            font-size: 0.9rem;
            opacity: 0.85;
            margin-top: 5px;
        }
        .form-body {
            padding: 30px;
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
        .form-group textarea,
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
            font-family: inherit;
        }
        .form-group textarea:focus,
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #888;
            font-size: 0.85rem;
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .message {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
            font-size: 0.95rem;
        }
        .message.success {
            display: block;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            display: block;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .links {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 10px;
        }
        .links a:hover {
            text-decoration: underline;
        }
        .char-count {
            text-align: right;
            font-size: 0.85rem;
            color: #888;
            margin-top: 5px;
        }
        .char-count.over {
            color: #e74c3c;
        }

        /* 投稿历史 */
        .history-section {
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        .history-section h3 {
            font-size: 1.1rem;
            color: #333;
            margin-bottom: 15px;
        }
        .history-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .history-item {
            display: flex;
            align-items: flex-start;
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            gap: 12px;
        }
        .history-status {
            flex-shrink: 0;
            width: 60px;
            text-align: center;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 2px;
        }
        .history-status.pending {
            background: #fff3cd;
            color: #856404;
        }
        .history-status.approved {
            background: #d4edda;
            color: #155724;
        }
        .history-status.rejected {
            background: #f8d7da;
            color: #721c24;
        }
        .history-content {
            flex: 1;
            font-size: 0.95rem;
            color: #333;
            line-height: 1.5;
            word-break: break-word;
        }
        .history-time {
            font-size: 0.8rem;
            color: #aaa;
            margin-top: 4px;
        }
        .history-empty {
            text-align: center;
            color: #aaa;
            padding: 20px;
            font-size: 0.9rem;
        }
        @media (max-width: 480px) {
            .history-item {
                flex-direction: column;
                gap: 6px;
            }
        }
        @media (min-width: 1024px) {
            .container {
                max-width: 780px;
            }
            .form-body {
                padding: 40px 50px;
            }
            .header {
                padding: 30px 50px;
            }
            .header h1 {
                font-size: 1.8rem;
            }
        }
        @media (min-width: 1600px) {
            body {
                padding: 60px 40px;
            }
            .container {
                max-width: 900px;
            }
            .form-body {
                padding: 50px 60px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>投稿励志名言</h1>
            <p>分享你的励志话语，激励更多人！</p>
        </div>
        <div class="form-body">
            <div id="message" class="message"></div>
            <form id="submitForm" onsubmit="handleSubmit(event)">
                <div class="form-group">
                    <label for="content">名言内容 *</label>
                    <textarea id="content" name="content" maxlength="500" placeholder="输入你想分享的励志话语..." required oninput="updateCharCount()"></textarea>
                    <div class="char-count"><span id="charCount">0</span>/500</div>
                    <small>支持使用 &lt;b&gt;&lt;i&gt;&lt;u&gt;&lt;em&gt;&lt;strong&gt; 等标签，以及 &lt;span style="color:red"&gt; 单独给文字上色、&lt;br&gt; 换行</small>
                </div>
                <div class="form-group">
                    <label for="submitter_name">你的名字（可选）</label>
                    <input type="text" id="submitter_name" name="submitter_name" maxlength="50" placeholder="匿名">
                </div>
                <button type="submit" class="btn-submit" id="submitBtn">提交投稿</button>
            </form>

            <!-- 投稿历史 -->
            <div class="history-section" id="historySection" style="display:none;">
                <h3>我的投稿</h3>
                <div class="history-list" id="historyList"></div>
            </div>

            <div class="links">
                <a href="index.php">返回首页</a>
                <a href="edit.php">管理员设置</a>
            </div>
        </div>
    </div>
    <script>
        const STORAGE_KEY = 'countdown_my_submissions';

        function getLocalSubmissions() {
            try {
                return JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
            } catch { return []; }
        }

        function saveLocalSubmissions(list) {
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify(list)); } catch {}
        }

        function addLocalSubmission(id, content, submitter) {
            const list = getLocalSubmissions();
            list.unshift({
                id: id,
                content: content,
                submitter: submitter,
                status: 'pending',
                submitted_at: new Date().toISOString()
            });
            saveLocalSubmissions(list);
            return list;
        }

        function updateLocalStatuses(serverData) {
            const list = getLocalSubmissions();
            const statusMap = {};
            for (const item of serverData) {
                statusMap[item.id] = item.status;
            }
            let changed = false;
            for (const sub of list) {
                if (statusMap[sub.id] && statusMap[sub.id] !== sub.status) {
                    sub.status = statusMap[sub.id];
                    changed = true;
                }
            }
            if (changed) saveLocalSubmissions(list);
            return list;
        }

        function formatDate(isoStr) {
            try {
                const d = new Date(isoStr);
                return d.getFullYear() + '-' +
                    String(d.getMonth()+1).padStart(2,'0') + '-' +
                    String(d.getDate()).padStart(2,'0') + ' ' +
                    String(d.getHours()).padStart(2,'0') + ':' +
                    String(d.getMinutes()).padStart(2,'0');
            } catch { return isoStr; }
        }

        const statusLabels = { pending: '待审核', approved: '已通过', rejected: '已拒绝' };

        function renderHistory(list) {
            const section = document.getElementById('historySection');
            const container = document.getElementById('historyList');
            if (!list || list.length === 0) {
                section.style.display = 'none';
                return;
            }
            section.style.display = 'block';
            container.innerHTML = list.map(item => `
                <div class="history-item">
                    <div class="history-status ${item.status}">${statusLabels[item.status] || item.status}</div>
                    <div>
                        <div class="history-content">${escapeHtml(item.content)}</div>
                        <div class="history-time">${formatDate(item.submitted_at)}${item.submitter ? ' · ' + escapeHtml(item.submitter) : ''}</div>
                    </div>
                </div>
            `).join('');
        }

        async function checkAllStatuses() {
            const list = getLocalSubmissions();
            if (list.length === 0) return;
            const ids = list.map(item => item.id).filter(id => id > 0).join(',');
            if (!ids) return;
            try {
                const res = await fetch('api/check_quotes.php?ids=' + ids);
                const data = await res.json();
                if (data.success && data.data) {
                    const updated = updateLocalStatuses(data.data);
                    renderHistory(updated);
                }
            } catch (e) {
                // 静默失败，用本地缓存状态
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function updateCharCount() {
            const textarea = document.getElementById('content');
            const count = textarea.value.length;
            const charCount = document.getElementById('charCount');
            charCount.textContent = count;
            charCount.parentElement.className = 'char-count' + (count > 450 ? ' over' : '');
        }

        async function handleSubmit(event) {
            event.preventDefault();
            const btn = document.getElementById('submitBtn');
            const messageEl = document.getElementById('message');
            const content = document.getElementById('content').value.trim();
            const submitterName = document.getElementById('submitter_name').value.trim();

            if (content.length < 2) {
                showMessage('名言内容至少2个字符', 'error');
                return;
            }

            btn.disabled = true;
            btn.textContent = '提交中...';

            try {
                const res = await fetch('api/submit_quote.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({content, submitter_name: submitterName})
                });
                const data = await res.json();
                if (data.success) {
                    showMessage(data.message, 'success');
                    document.getElementById('submitForm').reset();
                    updateCharCount();
                    if (data.id) {
                        addLocalSubmission(data.id, content, submitterName);
                        renderHistory(getLocalSubmissions());
                    }
                } else {
                    showMessage(data.message, 'error');
                }
            } catch (err) {
                showMessage('提交失败，请检查网络连接', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = '提交投稿';
            }
        }

        function showMessage(text, type) {
            const el = document.getElementById('message');
            el.textContent = text;
            el.className = 'message ' + type;
        }

        // 页面加载：显示已有投稿并检查状态
        document.addEventListener('DOMContentLoaded', () => {
            const list = getLocalSubmissions();
            renderHistory(list);
            if (list.length > 0) checkAllStatuses();
            // 每15秒自动检查状态更新
            setInterval(() => {
                if (getLocalSubmissions().length > 0) checkAllStatuses();
            }, 15000);
            // 页面从后台切回时刷新
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden && getLocalSubmissions().length > 0) checkAllStatuses();
            });
            // 网络恢复时刷新
            window.addEventListener('online', () => {
                if (getLocalSubmissions().length > 0) checkAllStatuses();
            });
        });
    </script>
</body>
</html>
