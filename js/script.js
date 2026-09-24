/**
 * 倒计时和轮播脚本
 */

// 服务器时间偏移量（毫秒），null 表示尚未完成首次同步
let serverTimeOffset = null;
let serverTimeSyncInterval = null;
let lastKnownTime = 0;
let isSyncing = false;          // 互斥锁，防止并发同步
let offsetHistory = [];          // offset 历史采样，用于平滑计算
const OFFSET_HISTORY_SIZE = 5;   // 保留最近 N 次采样

/**
 * 获取服务器时间偏移（带互斥锁 + 加权平滑 + 首次保护）
 */
async function syncServerTime() {
    // 竞态保护：如果正在同步中，跳过本次调用
    if (isSyncing) {
        console.warn('时间同步正在进行中，跳过本次调用');
        return;
    }
    isSyncing = true;

    try {
        const sendTime = Date.now();
        const response = await fetch('api/get_server_time.php?_=' + sendTime);
        const receiveTime = Date.now();
        const data = await response.json();

        if (!data.timestamp) {
            throw new Error('Invalid server response');
        }

        const rtt = receiveTime - sendTime;
        const serverTime = data.timestamp * 1000; // PHP time() 返回秒，转毫秒
        const estimatedServerTime = serverTime + Math.round(rtt / 2);
        const newOffset = estimatedServerTime - receiveTime;

        // 首次同步：直接接受，不做异常检测
        if (serverTimeOffset === null) {
            serverTimeOffset = newOffset;
            lastKnownTime = Date.now();
            offsetHistory = [newOffset];
            console.log('首次服务器时间偏移(ms):', serverTimeOffset, 'RTT(ms):', rtt);
            return;
        }

        // 异常检测：如果偏离超过 5 分钟，忽略本次
        const timeDiff = Math.abs(newOffset - serverTimeOffset);
        if (timeDiff > 5 * 60 * 1000) {
            console.warn('服务器时间同步异常（偏离', Math.round(timeDiff / 1000), '秒），忽略本次同步');
            return;
        }

        // 滑动窗口加权平滑：新样本权重更高，抵抗单次 RTT 波动
        offsetHistory.push(newOffset);
        if (offsetHistory.length > OFFSET_HISTORY_SIZE) {
            offsetHistory.shift();
        }
        const totalWeight = offsetHistory.reduce((sum, _, i) => sum + (i + 1), 0);
        const weightedSum = offsetHistory.reduce((sum, off, i) => sum + off * (i + 1), 0);
        serverTimeOffset = weightedSum / totalWeight;

        lastKnownTime = Date.now();
        console.log('服务器时间偏移(ms):', Math.round(serverTimeOffset), 'RTT(ms):', rtt,
            '平滑窗口:', offsetHistory.length);
    } catch (error) {
        console.error('同步服务器时间失败，使用已有偏移:', error);
    } finally {
        isSyncing = false;
    }
}

/**
 * 定期重新同步服务器时间（每3分钟），防止客户端漂移
 */
function startPeriodicTimeSync() {
    if (serverTimeSyncInterval) {
        clearInterval(serverTimeSyncInterval);
    }
    // 首次立即同步
    syncServerTime();
    // 之后每3分钟同步一次
    serverTimeSyncInterval = setInterval(syncServerTime, 3 * 60 * 1000);
}

/**
 * 获取校正后的当前时间（使用服务器时间偏移）
 * 首次同步前返回本地时间，避免倒计时倒退
 */
function getNow() {
    if (serverTimeOffset === null) {
        // 首次同步前，使用本地时间（避免倒退）
        return Date.now();
    }
    return Date.now() + serverTimeOffset;
}

/**
 * 安全的HTML净化（白名单方案）
 *
 * 只保留 b/i/u/em/strong/span/br 标签：
 * - span 仅保留过滤后的 style 属性（白名单 CSS 属性，可单独控制每条名言的字体大小/颜色等）
 * - 所有事件属性、脚本、伪协议一律丢弃
 * 返回 DocumentFragment，由调用方 appendChild 渲染，全程不使用 innerHTML 写入用户内容。
 */
const ALLOWED_TAGS = new Set(['B', 'I', 'U', 'EM', 'STRONG', 'SPAN', 'BR']);
const ALLOWED_CSS_PROPS = new Set([
    'color', 'background-color', 'font-size', 'font-family', 'font-weight',
    'font-style', 'text-decoration', 'text-align', 'line-height',
    'letter-spacing', 'text-shadow',
]);
const DANGER_CSS_VALUES = [
    'url(', 'expression', 'javascript:', 'vbscript:', 'behavior',
    '-moz-binding', '@import', 'position', 'z-index', 'content', '\\',
];

/**
 * 过滤 CSS 声明串（与后端 HtmlSanitizer::filterCss 同规则）
 */
function filterStyle(styleStr) {
    if (!styleStr || styleStr.length > 500) return '';
    const decls = [];
    String(styleStr).split(';').forEach(decl => {
        const idx = decl.indexOf(':');
        if (idx < 0) return;
        const prop = decl.slice(0, idx).trim().toLowerCase();
        const value = decl.slice(idx + 1).trim();
        if (!ALLOWED_CSS_PROPS.has(prop)) return;
        if (value.length > 100) return;
        const lower = value.toLowerCase();
        if (DANGER_CSS_VALUES.some(d => lower.includes(d))) return;
        decls.push(prop + ': ' + value);
    });
    return decls.join('; ');
}

function sanitizeHtml(text) {
    const frag = document.createDocumentFragment();
    if (!text || typeof text !== 'string') return frag;
    if (text.length > 10000) {
        text = text.substring(0, 10000);
        console.warn('sanitizeHtml: 输入超过10000字符，已截断');
    }

    let doc;
    try {
        doc = new DOMParser().parseFromString('<body>' + text + '</body>', 'text/html');
    } catch (e) {
        // DOMParser 不可用时退化为纯文本
        frag.appendChild(document.createTextNode(text));
        return frag;
    }

    // 递归：文本节点保留；白名单标签重建（span 仅保留过滤后的 style）；其他标签只保留其文本
    const walk = (node, target) => {
        node.childNodes.forEach(child => {
            if (child.nodeType === Node.TEXT_NODE) {
                target.appendChild(document.createTextNode(child.nodeValue));
            } else if (child.nodeType === Node.ELEMENT_NODE) {
                if (ALLOWED_TAGS.has(child.tagName)) {
                    const el = document.createElement(child.tagName);
                    if (child.tagName === 'SPAN') {
                        const st = filterStyle(child.getAttribute('style') || '');
                        if (st) el.setAttribute('style', st);
                    }
                    walk(child, el);
                    target.appendChild(el);
                } else {
                    walk(child, target);
                }
            }
        });
    };

    walk(doc.body, frag);
    return frag;
}

/**
 * 更新当前时间显示
 */
function updateCurrentTime() {
    const el = document.querySelector('.current-time');
    if (!el) return;
    const now = new Date(getNow());
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    const h = String(now.getHours()).padStart(2, '0');
    const min = String(now.getMinutes()).padStart(2, '0');
    const s = String(now.getSeconds()).padStart(2, '0');
    el.textContent = y + '-' + m + '-' + d + ' ' + h + ':' + min + ':' + s;
}

class CountdownApp {
    constructor() {
        this.config = null;
        this.messages = [];
        this.currentMsgIndex = 0;
        this.countdownInterval = null;
        this.messageInterval = null;
        this.timeInterval = null;
        this.isRefreshing = false;     // 互斥锁，防止并发刷新
        this.showTimeout = null;       // 用于取消前一个 showMessage 的 setTimeout
        this.contentPool = null;       // 多模块内容池（get_content.php）：[{module_key,module_name,weight,items:[...]}]
        this.poolCursors = {};         // 各模块播放游标
        this.poolTotalWeight = 0;      // 模块权重总和
    }

    /**
     * 初始化应用
     */
    async init() {
        await syncServerTime();
        startPeriodicTimeSync();
        await this.loadConfig();

        setTimeout(() => {
            this.applyStyles();
            this.initMessages();
            this.startCountdown();
            this.startMessageRotation();
            this.startTimeDisplay();
            this.startAutoRefresh();
            this.loadContent();
        }, 100);
    }

    /**
     * 启动自动刷新（每小时拉取最新配置，支持后台标签恢复和断网重连）
     */
    startAutoRefresh() {
        // 每小时静默拉取一次最新配置；切回标签页/断网恢复时仍会立即拉取
        this.refreshInterval = setInterval(() => this.doRefresh(), 60 * 60 * 1000);

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) this.doRefresh();
        });

        window.addEventListener('online', () => this.doRefresh());
    }

    /**
     * 动态刷新（静默拉取配置，不打断倒计时和轮播）
     * 带互斥锁防止并发调用；获取失败时保留当前配置，绝不回退
     */
    async doRefresh() {
        if (this.isRefreshing) return;
        this.isRefreshing = true;
        try {
            const isFirstLoad = (this.config === null);
            const oldMessages = this.config ? this.config.messages : null;
            const oldInterval = this.config ? this.config.message_interval : null;

            // loadConfig 内部保证：拉取/解析失败时 this.config 仍是旧配置（首次则为内置默认配置）
            const success = await this.loadConfig();

            // 获取失败时保留当前配置，不做任何视觉变更
            if (!success) return;

            // 渲染与定时器对齐单独保护：任何一步异常都不能影响旧画面，更不能让页面崩溃
            try {
                if (isFirstLoad) {
                    // 首次加载成功，初始化所有模块
                    this.applyStyles();
                    this.initMessages();
                } else {
                    // 后续刷新：只在配置实际变化时更新
                    if (oldInterval !== this.config.message_interval) {
                        this.startMessageRotation();
                    }
                    if (oldMessages !== this.config.messages) {
                        const parsed = (this.config.messages || '').split('|').filter(msg => msg.trim());
                        this.messages = parsed.length ? parsed : ['坚持到底，永不放弃。'];
                        if (this.currentMsgIndex >= this.messages.length) {
                            this.currentMsgIndex = 0;
                        }
                        this.showMessage(this.currentMsgIndex);
                    }
                    this.applyStyles();
                }
                // 按最新目标时间对齐倒计时（修复"时间到"后改日期无法自动恢复）
                this.resyncTimers();
                // 内容池静默刷新：失败自动回退本地轮播，不影响任何现有画面
                this.loadContent();
            } catch (renderError) {
                console.warn('CountdownApp 应用新配置失败，保留当前画面:', renderError && renderError.message);
            }
        } catch (error) {
            console.warn('CountdownApp 刷新失败，保留当前配置:', error.message);
        } finally {
            this.isRefreshing = false;
        }
    }

    /**
     * 根据最新配置对齐倒计时与消息轮播状态（幂等，可反复调用）
     * - 目标在未来：定时器若已停止（如刚从"时间到"恢复）则重启，运行中则不打断
     * - 目标已过期/非法：停止定时器并显示"时间到"
     */
    resyncTimers() {
        const target = Number(this.config.target_timestamp);
        const inFuture = Number.isFinite(target) && target > getNow();

        if (inFuture) {
            if (!this.countdownInterval) {
                this.startCountdown();
            }
            if (!this.messageInterval) {
                this.startMessageRotation();
            }
        } else {
            this.showTimeUp();
        }
    }

    /**
     * 获取默认配置（当API失败时使用）
     */
    getDefaultConfig() {
        const defaultDate = new Date('2026-06-26T00:00:00+08:00');
        return {
            target_date: '2026-06-26',
            target_timestamp: defaultDate.getTime(),
            title_font_size: '32',
            title_font_color: '#ffffff',
            title_font_family: 'Arial, "Microsoft YaHei", sans-serif',
            title_font_url: '',
            countdown_font_size: '55',
            countdown_font_color: '#00a761',
            countdown_font_family: '"Courier New", monospace',
            countdown_font_url: '',
            bg_color: '#1a3a4e',
            bg_image: '',
            bg_image_mode: 'cover',
            message_font_size: '20',
            message_font_color: '#ffffff',
            message_font_family: 'Arial, "Microsoft YaHei", sans-serif',
            message_font_url: '',
            message_container_width: '90%',
            message_interval: '5000',
            motivation_gap: '4',
            messages: '奋斗不息，成功必将到来。|不要等待机会，而要创造机会。|坚持到底，永不放弃。|付出总有回报，梦想终会实现。'
        };
    }

    /**
     * 从服务器加载配置
     * @returns {boolean} 是否成功加载了新配置
     */
    async loadConfig() {
        // 回退原则：任何失败都不覆盖已有配置；仅首次（尚无配置）时使用内置默认配置，保证页面可用
        const fallback = (msg, err) => {
            if (this.config === null) {
                console.warn('首次加载配置失败，使用内置默认配置:', msg || (err && err.message));
                this.config = this.getDefaultConfig();
            } else {
                console.warn('加载配置失败，保留当前配置:', msg || (err && err.message));
            }
            return false;
        };
        try {
            const response = await fetch('api/get_config.php');
            if (!response || !response.ok) {
                return fallback('HTTP ' + (response ? response.status : '无响应'));
            }
            let data;
            try {
                data = await response.json();
            } catch (parseErr) {
                // 盾页/网关错误页等非 JSON 响应
                return fallback('响应不是合法JSON', parseErr);
            }
            if (!data || typeof data !== 'object' || Array.isArray(data) || data.error) {
                return fallback(data && data.message ? data.message : '配置内容无效');
            }
            this.config = data;
            return true;
        } catch (error) {
            return fallback(null, error);
        }
    }

    /**
     * 应用样式
     */
    applyStyles() {
        // 加载字体（如果配置了URL）
        this.loadFont('title');
        this.loadFont('countdown');
        this.loadFont('message');

        // 应用背景
        const bgLayer = document.querySelector('.background-layer');
        if (bgLayer) {
            if (this.config.bg_image) {
                bgLayer.style.backgroundImage = `url(${this.config.bg_image})`;
                bgLayer.classList.add('image-mode');
                bgLayer.style.backgroundSize = this.config.bg_image_mode || 'cover';
            } else {
                bgLayer.style.backgroundImage = 'none';
                bgLayer.style.backgroundColor = this.config.bg_color;
                bgLayer.classList.remove('image-mode');
            }
        }

        // 应用标题字体样式
        const titleElement = document.querySelector('.countdown-title');
        if (titleElement) {
            const size = this.config.title_font_size || '32';
            titleElement.style.fontSize = size + 'px';
            titleElement.style.color = this.config.title_font_color;
            titleElement.style.fontFamily = this.config.title_font_family;
        }

        // 应用倒计时数字字体样式
        const countdownDisplay = document.querySelector('.countdown-display');
        if (countdownDisplay) {
            const size = this.config.countdown_font_size || '55';
            countdownDisplay.style.fontSize = size + 'px';
            countdownDisplay.style.color = this.config.countdown_font_color;
            countdownDisplay.style.fontFamily = this.config.countdown_font_family;
        }

        // 应用励志话语字体样式和容器宽度
        const messageElement = document.querySelector('.motivation-text');
        if (messageElement) {
            const size = this.config.message_font_size || '20';
            messageElement.style.fontSize = size + 'px';
            messageElement.style.color = this.config.message_font_color;
            messageElement.style.fontFamily = this.config.message_font_family;
        }
        const messageContainer = document.querySelector('.motivation-container');
        if (messageContainer) {
            if (this.config.message_container_width) {
                messageContainer.style.maxWidth = this.config.message_container_width;
            }
            messageContainer.style.marginTop = (this.config.motivation_gap || '4') + 'px';
        }

        // 应用当前时间字体样式和位置
        const timeElement = document.querySelector('.current-time');
        if (timeElement) {
            timeElement.style.fontSize = (this.config.time_font_size || '13') + 'px';
            timeElement.style.color = this.config.time_font_color || 'rgba(255,255,255,0.6)';
            timeElement.style.fontFamily = this.config.time_font_family || '"Courier New", monospace';
            timeElement.style.bottom = (this.config.time_bottom || '12') + 'px';
        }
    }

    /**
     * 动态加载字体（防止重复添加导致内存泄漏）
     */
    loadFont(type) {
        const fontUrl = this.config[type + '_font_url'];
        if (fontUrl && fontUrl.trim()) {
            // 检查是否已存在相同 href 的 link 标签（避免选择器注入，改用属性比较）
            const existingLinks = Array.from(document.querySelectorAll('link'))
                .filter(l => l.getAttribute('href') === fontUrl);
            if (existingLinks.length > 0) {
                console.log(`${type}字体已加载:`, fontUrl);
                return;
            }
            
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = fontUrl;
            document.head.appendChild(link);
            console.log(`加载${type}字体:`, fontUrl);
        }
    }

    /**
     * 初始化励志话语
     */
    initMessages() {
        const raw = (this.config && this.config.messages) ? this.config.messages : '';
        this.messages = raw.split('|').filter(msg => msg.trim());
        if (this.messages.length === 0) {
            this.messages = ['坚持到底，永不放弃。'];
        }
        this.showMessage(0);
    }

    /**
     * 加载多模块内容池（get_content.php）
     * 回退铁律：任何失败都不启用内容池，继续沿用本地 messages 轮播，页面绝不崩溃
     * @returns {boolean} 是否成功加载
     */
    async loadContent() {
        try {
            const response = await fetch('api/get_content.php?_=' + Date.now());
            if (!response || !response.ok) {
                console.warn('内容池加载失败: HTTP ' + (response ? response.status : '无响应'));
                return false;
            }
            let data;
            try {
                data = await response.json();
            } catch (parseErr) {
                console.warn('内容池响应不是合法JSON:', parseErr && parseErr.message);
                return false;
            }
            if (!data || typeof data !== 'object' || Array.isArray(data) || data.error || !Array.isArray(data.modules)) {
                console.warn('内容池数据无效');
                return false;
            }
            const pool = data.modules.filter(m => m && Array.isArray(m.items) && m.items.length > 0);
            if (pool.length === 0) {
                console.warn('内容池无可用模块，沿用本地轮播');
                return false;
            }
            this.contentPool = pool;
            this.poolCursors = {};
            this.poolTotalWeight = 0;
            pool.forEach(m => {
                this.poolCursors[m.module_key] = 0;
                this.poolTotalWeight += (Number(m.weight) > 0 ? Number(m.weight) : 1);
            });
            console.log('内容池已加载:', pool.length, '个模块,', pool.reduce((s, m) => s + m.items.length, 0), '条内容');
            this.showNextContent();
            return true;
        } catch (error) {
            console.warn('内容池加载异常，沿用本地轮播:', error && error.message);
            return false;
        }
    }

    /**
     * 加权随机抽取下一帧内容（权重越大出现越频繁；模块内顺序播放）
     */
    showNextContent() {
        if (!this.contentPool || this.contentPool.length === 0) return;
        let r = Math.random() * this.poolTotalWeight;
        let chosen = this.contentPool[0];
        for (const m of this.contentPool) {
            r -= (Number(m.weight) > 0 ? Number(m.weight) : 1);
            if (r <= 0) { chosen = m; break; }
        }
        const items = chosen.items;
        let idx = this.poolCursors[chosen.module_key] || 0;
        if (idx >= items.length) idx = 0;
        this.poolCursors[chosen.module_key] = idx + 1;
        this.renderContentItem(chosen, items[idx]);
    }

    /**
     * 渲染单条内容（考点模块用标签+要点卡片，其余模块用大字文本）
     */
    renderContentItem(module, item) {
        const element = document.querySelector('.motivation-text');
        if (!element || !item) return;

        if (this.showTimeout) {
            clearTimeout(this.showTimeout);
        }

        element.classList.add('fade-out');

        this.showTimeout = setTimeout(() => {
            element.innerHTML = '';
            if (module.module_key === 'knowledge') {
                const card = document.createElement('div');
                card.className = 'knowledge-card';
                if (item.tag) {
                    const tag = document.createElement('span');
                    tag.className = 'knowledge-tag';
                    tag.textContent = item.tag;
                    card.appendChild(tag);
                }
                const body = document.createElement('div');
                body.className = 'knowledge-content';
                body.appendChild(sanitizeHtml(item.content || ''));
                card.appendChild(body);
                if (item.detail) {
                    const detail = document.createElement('div');
                    detail.className = 'knowledge-detail';
                    detail.appendChild(sanitizeHtml(item.detail));
                    card.appendChild(detail);
                }
                element.appendChild(card);
            } else {
                element.appendChild(sanitizeHtml(item.content || ''));
            }
            element.classList.remove('fade-out');
            this.showTimeout = null;
        }, 800);
    }

    /**
     * 显示指定索引的励志话语（支持HTML渲染）
     */
    showMessage(index) {
        const element = document.querySelector('.motivation-text');
        if (!element) return;

        if (this.showTimeout) {
            clearTimeout(this.showTimeout);
        }

        element.classList.add('fade-out');

        this.showTimeout = setTimeout(() => {
            element.innerHTML = '';
            element.appendChild(sanitizeHtml(this.messages[index]));
            element.classList.remove('fade-out');
            this.showTimeout = null;
        }, 800);
    }

    /**
     * 启动励志话语轮播
     */
    startMessageRotation() {
        if (this.messageInterval) {
            clearInterval(this.messageInterval);
        }
        if (!this.messages || this.messages.length === 0) {
            this.initMessages();
        }
        const interval = parseInt(this.config && this.config.message_interval) || 5000;
        this.messageInterval = setInterval(() => {
            try {
                if (this.contentPool && this.contentPool.length > 0) {
                    this.showNextContent();
                } else {
                    this.currentMsgIndex = (this.currentMsgIndex + 1) % this.messages.length;
                    this.showMessage(this.currentMsgIndex);
                }
            } catch (tickError) {
                console.warn('名言轮播单帧异常已隔离:', tickError && tickError.message);
            }
        }, interval);
        console.log('主页面名言翻页间隔:', interval, '毫秒');
    }

    /**
     * 启动倒计时（使用服务器时间）
     */
    startCountdown() {
        // 幂等：已在运行则先清理，避免重复创建多个定时器
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
            this.countdownInterval = null;
        }
        this.safeTick();
        this.countdownInterval = setInterval(() => this.safeTick(), 1000);
    }

    // 单帧渲染隔离：某一帧渲染异常不影响后续计时，绝不让定时器中断或抛到全局
    safeTick() {
        try {
            this.updateCountdown();
        } catch (err) {
            console.warn('倒计时单帧渲染异常，已跳过本帧:', err && err.message);
        }
    }

    /**
     * 启动当前时间显示
     */
    startTimeDisplay() {
        updateCurrentTime();
        this.timeInterval = setInterval(updateCurrentTime, 1000);
    }

    /**
     * 更新倒计时显示（主页面格式：天:时:分:秒，使用服务器时间）
     */
    updateCountdown() {
        const targetTime = Number(this.config.target_timestamp);
        const now = getNow();
        // 目标时间戳缺失/非法时按"时间到"安全降级，避免 NaN 渲染
        if (!Number.isFinite(targetTime)) {
            this.showTimeUp();
            return;
        }
        const diff = targetTime - now;

        if (diff <= 0) {
            this.showTimeUp();
            return;
        }

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);

        const display = this.formatCountdown(days, hours, minutes, seconds);
        const el = document.querySelector('.countdown-display');
        if (el) {
            el.textContent = display;
        }
    }

    /**
     * 格式化倒计时显示
     */
    formatCountdown(days, hours, minutes, seconds) {
        const pad = (num) => num.toString().padStart(2, '0');
        return `${pad(days)}:${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
    }

    /**
     * 显示时间到
     */
    showTimeUp() {
        clearInterval(this.countdownInterval);
        clearInterval(this.messageInterval);
        // 显式置空，供 resyncTimers 判断"定时器已停止、需要重启"
        this.countdownInterval = null;
        this.messageInterval = null;
        const cdEl = document.querySelector('.countdown-display');
        if (cdEl) cdEl.textContent = '时间到！';
        const mtEl = document.querySelector('.motivation-text');
        if (mtEl) mtEl.textContent = '中考加油！';
    }
}

/**
 * 秒数倒计时类
 */
class SecondsCountdownApp {
    constructor() {
        this.config = null;
        this.messages = [];
        this.currentMsgIndex = 0;
        this.countdownInterval = null;
        this.messageInterval = null;
        this.timeInterval = null;
        this.isRefreshing = false;
        this.showTimeout = null;
        this.contentPool = null;       // 多模块内容池（get_content.php）
        this.poolCursors = {};         // 各模块播放游标
        this.poolTotalWeight = 0;      // 模块权重总和
    }

    async init() {
        await syncServerTime();
        startPeriodicTimeSync();
        await this.loadConfig();

        setTimeout(() => {
            this.applyStyles();
            this.initMessages();
            this.startCountdown();
            this.startMessageRotation();
            this.startTimeDisplay();
            this.startAutoRefresh();
            this.loadContent();
        }, 100);
    }

    /**
     * 启动自动刷新（每小时拉取最新配置，支持后台标签恢复和断网重连）
     */
    startAutoRefresh() {
        // 每小时静默拉取一次最新配置；切回标签页/断网恢复时仍会立即拉取
        this.refreshInterval = setInterval(() => this.doRefresh(), 60 * 60 * 1000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) this.doRefresh();
        });
        window.addEventListener('online', () => this.doRefresh());
    }

    /**
     * 动态刷新（静默拉取配置，不打断倒计时和轮播）
     * 带互斥锁防止并发调用；获取失败时保留当前配置，绝不回退
     */
    async doRefresh() {
        if (this.isRefreshing) return;
        this.isRefreshing = true;
        try {
            const isFirstLoad = (this.config === null);
            const oldMessages = this.config ? this.config.messages : null;
            const oldInterval = this.config ? this.config.message_interval : null;

            // loadConfig 内部保证：拉取/解析失败时 this.config 仍是旧配置（首次则为内置默认配置）
            const success = await this.loadConfig();

            // 获取失败时保留当前配置，不做任何视觉变更
            if (!success) return;

            // 渲染与定时器对齐单独保护：任何一步异常都不能影响旧画面，更不能让页面崩溃
            try {
                if (isFirstLoad) {
                    this.applyStyles();
                    this.initMessages();
                } else {
                    if (oldInterval !== this.config.message_interval) {
                        this.startMessageRotation();
                    }
                    if (oldMessages !== this.config.messages) {
                        const parsed = (this.config.messages || '').split('|').filter(msg => msg.trim());
                        this.messages = parsed.length ? parsed : ['坚持到底，永不放弃。'];
                        if (this.currentMsgIndex >= this.messages.length) {
                            this.currentMsgIndex = 0;
                        }
                        this.showMessage(this.currentMsgIndex);
                    }
                    this.applyStyles();
                }
                // 按最新目标时间对齐倒计时（修复"时间到"后改日期无法自动恢复）
                this.resyncTimers();
                // 内容池静默刷新：失败自动回退本地轮播，不影响任何现有画面
                this.loadContent();
            } catch (renderError) {
                console.warn('SecondsCountdownApp 应用新配置失败，保留当前画面:', renderError && renderError.message);
            }
        } catch (error) {
            console.warn('SecondsCountdownApp 刷新失败，保留当前配置:', error.message);
        } finally {
            this.isRefreshing = false;
        }
    }

    /**
     * 根据最新配置对齐倒计时与消息轮播状态（幂等，可反复调用）
     * - 目标在未来：定时器若已停止（如刚从"时间到"恢复）则重启，运行中则不打断
     * - 目标已过期/非法：停止定时器并显示"时间到"
     */
    resyncTimers() {
        const target = Number(this.config.target_timestamp);
        const inFuture = Number.isFinite(target) && target > getNow();

        if (inFuture) {
            if (!this.countdownInterval) {
                this.startCountdown();
            }
            if (!this.messageInterval) {
                this.startMessageRotation();
            }
        } else {
            this.showTimeUp();
        }
    }

    /**
     * 从服务器加载配置
     * @returns {boolean} 是否成功加载了新配置
     */
    async loadConfig() {
        // 回退原则：任何失败都不覆盖已有配置；仅首次（尚无配置）时使用内置默认配置，保证页面可用
        const fallback = (msg, err) => {
            if (this.config === null) {
                console.warn('首次加载配置失败，使用内置默认配置:', msg || (err && err.message));
                this.config = this.getDefaultConfig();
            } else {
                console.warn('加载配置失败，保留当前配置:', msg || (err && err.message));
            }
            return false;
        };
        try {
            const response = await fetch('api/get_config.php');
            if (!response || !response.ok) {
                return fallback('HTTP ' + (response ? response.status : '无响应'));
            }
            let data;
            try {
                data = await response.json();
            } catch (parseErr) {
                // 盾页/网关错误页等非 JSON 响应
                return fallback('响应不是合法JSON', parseErr);
            }
            if (!data || typeof data !== 'object' || Array.isArray(data) || data.error) {
                return fallback(data && data.message ? data.message : '配置内容无效');
            }
            this.config = data;
            return true;
        } catch (error) {
            return fallback(null, error);
        }
    }

    getDefaultConfig() {
        const defaultDate = new Date('2026-06-26T00:00:00+08:00');
        return {
            target_date: '2026-06-26',
            target_timestamp: defaultDate.getTime(),
            title_font_size: '28',
            title_font_color: '#ffffff',
            title_font_family: 'Arial, "Microsoft YaHei", sans-serif',
            title_font_url: '',
            countdown_font_size: '50',
            countdown_font_color: '#2b7a05',
            countdown_font_family: '"Courier New", monospace',
            countdown_font_url: '',
            bg_color: '#222bdf',
            bg_image: '',
            bg_image_mode: 'cover',
            message_font_size: '18',
            message_font_color: '#ffffff',
            message_font_family: 'Arial, "Microsoft YaHei", sans-serif',
            message_font_url: '',
            message_container_width: '90%',
            message_interval: '5000',
            motivation_gap: '4',
            messages: '奋斗不息，成功必将到来。|不要等待机会，而要创造机会。|坚持到底，永不放弃。|付出总有回报，梦想终会实现。'
        };
    }

    applyStyles() {
        // 加载字体
        this.loadFont('title');
        this.loadFont('countdown');
        this.loadFont('message');

        const bgLayer = document.querySelector('.background-layer');
        if (bgLayer) {
            if (this.config.bg_image) {
                bgLayer.style.backgroundImage = `url(${this.config.bg_image})`;
                bgLayer.classList.add('image-mode');
                bgLayer.style.backgroundSize = this.config.bg_image_mode || 'cover';
            } else {
                bgLayer.style.backgroundImage = 'none';
                bgLayer.style.backgroundColor = this.config.bg_color;
                bgLayer.classList.remove('image-mode');
            }
        }

        // 应用标题字体样式
        const titleElement = document.querySelector('.countdown-title');
        if (titleElement) {
            const size = this.config.title_font_size || '28';
            titleElement.style.fontSize = size + 'px';
            titleElement.style.color = this.config.title_font_color;
            titleElement.style.fontFamily = this.config.title_font_family;
        }

        // 应用倒计时数字字体样式
        const countdownDisplay = document.querySelector('.countdown-display');
        if (countdownDisplay) {
            const size = this.config.countdown_font_size || '50';
            countdownDisplay.style.fontSize = size + 'px';
            countdownDisplay.style.color = this.config.countdown_font_color;
            countdownDisplay.style.fontFamily = this.config.countdown_font_family;
        }

        // 应用励志话语字体样式和容器宽度
        const messageElement = document.querySelector('.motivation-text');
        if (messageElement) {
            const size = this.config.message_font_size || '18';
            messageElement.style.fontSize = size + 'px';
            messageElement.style.color = this.config.message_font_color;
            messageElement.style.fontFamily = this.config.message_font_family;
        }
        const messageContainer = document.querySelector('.motivation-container');
        if (messageContainer) {
            if (this.config.message_container_width) {
                messageContainer.style.maxWidth = this.config.message_container_width;
            }
            messageContainer.style.marginTop = (this.config.motivation_gap || '4') + 'px';
        }

        // 应用当前时间字体样式和位置
        const timeElement = document.querySelector('.current-time');
        if (timeElement) {
            timeElement.style.fontSize = (this.config.time_font_size || '13') + 'px';
            timeElement.style.color = this.config.time_font_color || 'rgba(255,255,255,0.6)';
            timeElement.style.fontFamily = this.config.time_font_family || '"Courier New", monospace';
            timeElement.style.bottom = (this.config.time_bottom || '12') + 'px';
        }
    }

    loadFont(type) {
        const fontUrl = this.config[type + '_font_url'];
        if (fontUrl && fontUrl.trim()) {
            // 检查是否已存在相同 href 的 link 标签（避免选择器注入，改用属性比较）
            const existingLinks = Array.from(document.querySelectorAll('link'))
                .filter(l => l.getAttribute('href') === fontUrl);
            if (existingLinks.length > 0) {
                console.log(`秒数页面${type}字体已加载:`, fontUrl);
                return;
            }

            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = fontUrl;
            document.head.appendChild(link);
            console.log(`秒数页面加载${type}字体:`, fontUrl);
        }
    }

    initMessages() {
        const raw = (this.config && this.config.messages) ? this.config.messages : '';
        this.messages = raw.split('|').filter(msg => msg.trim());
        if (this.messages.length === 0) {
            this.messages = ['坚持到底，永不放弃。'];
        }
        this.showMessage(0);
    }

    /**
     * 加载多模块内容池（get_content.php）
     * 回退铁律：任何失败都不启用内容池，继续沿用本地 messages 轮播，页面绝不崩溃
     * @returns {boolean} 是否成功加载
     */
    async loadContent() {
        try {
            const response = await fetch('api/get_content.php?_=' + Date.now());
            if (!response || !response.ok) {
                console.warn('内容池加载失败: HTTP ' + (response ? response.status : '无响应'));
                return false;
            }
            let data;
            try {
                data = await response.json();
            } catch (parseErr) {
                console.warn('内容池响应不是合法JSON:', parseErr && parseErr.message);
                return false;
            }
            if (!data || typeof data !== 'object' || Array.isArray(data) || data.error || !Array.isArray(data.modules)) {
                console.warn('内容池数据无效');
                return false;
            }
            const pool = data.modules.filter(m => m && Array.isArray(m.items) && m.items.length > 0);
            if (pool.length === 0) {
                console.warn('内容池无可用模块，沿用本地轮播');
                return false;
            }
            this.contentPool = pool;
            this.poolCursors = {};
            this.poolTotalWeight = 0;
            pool.forEach(m => {
                this.poolCursors[m.module_key] = 0;
                this.poolTotalWeight += (Number(m.weight) > 0 ? Number(m.weight) : 1);
            });
            console.log('秒数页面内容池已加载:', pool.length, '个模块,', pool.reduce((s, m) => s + m.items.length, 0), '条内容');
            this.showNextContent();
            return true;
        } catch (error) {
            console.warn('内容池加载异常，沿用本地轮播:', error && error.message);
            return false;
        }
    }

    /**
     * 加权随机抽取下一帧内容（权重越大出现越频繁；模块内顺序播放）
     */
    showNextContent() {
        if (!this.contentPool || this.contentPool.length === 0) return;
        let r = Math.random() * this.poolTotalWeight;
        let chosen = this.contentPool[0];
        for (const m of this.contentPool) {
            r -= (Number(m.weight) > 0 ? Number(m.weight) : 1);
            if (r <= 0) { chosen = m; break; }
        }
        const items = chosen.items;
        let idx = this.poolCursors[chosen.module_key] || 0;
        if (idx >= items.length) idx = 0;
        this.poolCursors[chosen.module_key] = idx + 1;
        this.renderContentItem(chosen, items[idx]);
    }

    /**
     * 渲染单条内容（考点模块用标签+要点卡片，其余模块用大字文本）
     */
    renderContentItem(module, item) {
        const element = document.querySelector('.motivation-text');
        if (!element || !item) return;

        if (this.showTimeout) {
            clearTimeout(this.showTimeout);
        }

        element.classList.add('fade-out');

        this.showTimeout = setTimeout(() => {
            element.innerHTML = '';
            if (module.module_key === 'knowledge') {
                const card = document.createElement('div');
                card.className = 'knowledge-card';
                if (item.tag) {
                    const tag = document.createElement('span');
                    tag.className = 'knowledge-tag';
                    tag.textContent = item.tag;
                    card.appendChild(tag);
                }
                const body = document.createElement('div');
                body.className = 'knowledge-content';
                body.appendChild(sanitizeHtml(item.content || ''));
                card.appendChild(body);
                if (item.detail) {
                    const detail = document.createElement('div');
                    detail.className = 'knowledge-detail';
                    detail.appendChild(sanitizeHtml(item.detail));
                    card.appendChild(detail);
                }
                element.appendChild(card);
            } else {
                element.appendChild(sanitizeHtml(item.content || ''));
            }
            element.classList.remove('fade-out');
            this.showTimeout = null;
        }, 800);
    }

    showMessage(index) {
        const element = document.querySelector('.motivation-text');
        if (!element) return;

        if (this.showTimeout) {
            clearTimeout(this.showTimeout);
        }

        element.classList.add('fade-out');

        this.showTimeout = setTimeout(() => {
            element.innerHTML = '';
            element.appendChild(sanitizeHtml(this.messages[index]));
            element.classList.remove('fade-out');
            this.showTimeout = null;
        }, 800);
    }

    startMessageRotation() {
        if (this.messageInterval) {
            clearInterval(this.messageInterval);
        }
        if (!this.messages || this.messages.length === 0) {
            this.initMessages();
        }
        const interval = parseInt(this.config && this.config.message_interval) || 5000;
        this.messageInterval = setInterval(() => {
            try {
                if (this.contentPool && this.contentPool.length > 0) {
                    this.showNextContent();
                } else {
                    this.currentMsgIndex = (this.currentMsgIndex + 1) % this.messages.length;
                    this.showMessage(this.currentMsgIndex);
                }
            } catch (tickError) {
                console.warn('名言轮播单帧异常已隔离:', tickError && tickError.message);
            }
        }, interval);
        console.log('秒数页面名言翻页间隔:', interval, '毫秒');
    }

    startCountdown() {
        // 幂等：已在运行则先清理，避免重复创建多个定时器
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
            this.countdownInterval = null;
        }
        this.safeTick();
        this.countdownInterval = setInterval(() => this.safeTick(), 1000);
    }

    // 单帧渲染隔离：某一帧渲染异常不影响后续计时，绝不让定时器中断或抛到全局
    safeTick() {
        try {
            this.updateCountdown();
        } catch (err) {
            console.warn('倒计时单帧渲染异常，已跳过本帧:', err && err.message);
        }
    }

    /**
     * 启动当前时间显示
     */
    startTimeDisplay() {
        updateCurrentTime();
        this.timeInterval = setInterval(updateCurrentTime, 1000);
    }

    updateCountdown() {
        const targetTime = Number(this.config.target_timestamp);
        const now = getNow();
        // 目标时间戳缺失/非法时按"时间到"安全降级，避免 NaN 渲染
        if (!Number.isFinite(targetTime)) {
            this.showTimeUp();
            return;
        }
        const diff = targetTime - now;

        if (diff <= 0) {
            this.showTimeUp();
            return;
        }

        const totalSeconds = Math.floor(diff / 1000);
        const el = document.querySelector('.countdown-display');
        if (el) {
            // 数字与单位分离：单位"秒"使用标题字体渲染（2026-08-10）
            el.textContent = '';
            el.appendChild(document.createTextNode(totalSeconds.toLocaleString() + ' '));
            const unit = document.createElement('span');
            unit.className = 'countdown-unit';
            unit.textContent = '秒';
            unit.style.fontFamily = this.config.title_font_family || 'Arial, "Microsoft YaHei", sans-serif';
            el.appendChild(unit);
        }
    }

    showTimeUp() {
        clearInterval(this.countdownInterval);
        clearInterval(this.messageInterval);
        // 显式置空，供 resyncTimers 判断"定时器已停止、需要重启"
        this.countdownInterval = null;
        this.messageInterval = null;
        const cdEl = document.querySelector('.countdown-display');
        if (cdEl) cdEl.textContent = '时间到！';
        const mtEl = document.querySelector('.motivation-text');
        if (mtEl) mtEl.textContent = '中考加油！';
    }
}

// 根据页面类型初始化
document.addEventListener('DOMContentLoaded', () => {
    const pageType = document.body.dataset.pageType;
    if (pageType === 'seconds') {
        const app = new SecondsCountdownApp();
        app.init();
    } else {
        const app = new CountdownApp();
        app.init();
    }
});
