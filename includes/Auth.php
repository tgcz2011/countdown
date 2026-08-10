<?php
/**
 * 认证辅助类
 * 使用 session + token 替代明文密码传输
 *
 * 安全加固（2026-08-10）：
 * 1. 移除兜底默认密码 admin888：未配置 ADMIN_PASSWORD 时拒绝一切登录（fail closed）
 * 2. 空密码校验防御：ADMIN_PASSWORD 为空时同样拒绝登录
 * 3. 登录失败限流：连续失败 5 次锁定 15 分钟（按 IP，DB 持久化）
 * 4. Session 加固：HttpOnly + SameSite=Lax + HTTPS 下 Secure；登录成功后 regenerate_id
 */
require_once __DIR__ . '/Database.php';

class Auth {
    const LOCKOUT_THRESHOLD = 5;      // 连续失败次数
    const LOCKOUT_MINUTES   = 15;     // 锁定分钟数

    private static $instance = null;
    private $adminPassword;
    private $sessionTimeout;
    private $lastError = null;        // ['code' => ..., 'message' => ..., 'remaining' => ...]

    private function __construct() {
        if (!EnvLoader::isLoaded()) {
            EnvLoader::load();
        }
        $this->adminPassword = (string)EnvLoader::get('ADMIN_PASSWORD', '');
        $this->sessionTimeout = (int)EnvLoader::get('ADMIN_SESSION_TIMEOUT', 86400);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 安全地启动 session（避免重复启动告警）
     */
    private function ensureSession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /**
     * 获取客户端 IP（用于限流）
     */
    private function clientIp() {
        // 只信任 REMOTE_ADDR，避免 X-Forwarded-For 伪造
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * 验证登录密码
     */
    public function verifyPassword($password) {
        if ($this->adminPassword === '') {
            return false; // 未配置密码：拒绝一切登录
        }
        if (!is_string($password) || $password === '') {
            return false;
        }
        return hash_equals($this->adminPassword, $password);
    }

    /**
     * 创建管理会话
     * @return bool 登录是否成功；失败详情通过 getLastError() 获取
     */
    public function login($password) {
        $ip = $this->clientIp();
        $db = Database::getInstance();

        // 限流检查：DB 不可用时保守拒绝（站点本身依赖数据库）
        if (!$db->getConnection()) {
            $this->lastError = ['code' => 'db_unavailable', 'message' => '数据库不可用，无法验证登录'];
            error_log('Auth::login 数据库不可用，拒绝登录 IP=' . $ip);
            return false;
        }

        $remaining = $db->checkLoginLocked($ip);
        if ($remaining > 0) {
            $this->lastError = [
                'code'      => 'locked',
                'message'   => '尝试次数过多，请稍后再试',
                'remaining' => $remaining,
            ];
            return false;
        }

        if (!$this->verifyPassword($password)) {
            $db->recordLoginFailure($ip);
            $this->lastError = ['code' => 'password', 'message' => '密码错误'];
            return false;
        }

        $db->clearLoginFailures($ip);

        $this->ensureSession();
        session_regenerate_id(true); // 防会话固定
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['auth_token'] = bin2hex(random_bytes(32));
        session_write_close();

        $this->lastError = null;
        return true;
    }

    /**
     * 检查当前请求是否已认证（用于API端点）
     * 优先检查PHP session，然后检查X-Auth-Token header
     */
    public function isAuthenticated() {
        $this->ensureSession();

        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) < $this->sessionTimeout) {
                session_write_close();
                return true;
            }
        }

        // 方法2：检查X-Auth-Token header（用于JS fetch请求）
        $authToken = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
        if (!empty($authToken) && isset($_SESSION['auth_token'])) {
            if (hash_equals($_SESSION['auth_token'], $authToken)) {
                if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) < $this->sessionTimeout) {
                    session_write_close();
                    return true;
                }
            }
        }

        session_write_close();
        return false;
    }

    /**
     * 获取认证token（用于传递给前端JS）
     */
    public function getAuthToken() {
        $this->ensureSession();
        $token = $_SESSION['auth_token'] ?? null;
        session_write_close();
        return $token;
    }

    /**
     * 登出
     */
    public function logout() {
        $this->ensureSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    /**
     * 获取最近一次登录操作的错误详情
     * @return array|null ['code' => 'locked|password|db_unavailable', 'message' => ..., 'remaining' => 秒]
     */
    public function getLastError() {
        return $this->lastError;
    }

    /**
     * 发送未授权响应
     */
    public static function sendUnauthorized($message = '未授权访问') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    /**
     * 初始化API认证检查（用于API端点）
     * 如果未认证则自动返回403
     */
    public function requireAuth() {
        if (!$this->isAuthenticated()) {
            self::sendUnauthorized();
        }
    }
}
