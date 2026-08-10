<?php
/**
 * 数据库连接类（支持自动初始化，完全容错）
 */
require_once __DIR__ . '/HtmlSanitizer.php';

class Database {
    private $connection = null;
    private static $instance = null;
    private $config;
    private $initError = null;

    private function __construct() {
        try {
            $this->config = require __DIR__ . '/../config.php';
            $this->connectAndInit();
        } catch (Throwable $e) {
            $this->initError = $e->getMessage();
            error_log('数据库初始化失败: ' . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 连接数据库并自动初始化
     */
    private function connectAndInit() {
        try {
            // 解析主机和端口（支持 host:port 格式）
            $host = $this->config['host'];
            $port = null;

            if (strpos($host, ':') !== false) {
                [$host, $port] = explode(':', $host, 2);
            }

            // 构建DSN
            $dsn = "mysql:host={$host};charset={$this->config['charset']}";
            if ($port) {
                $dsn .= ";port={$port}";
            }

            // 首先尝试直接连接目标数据库
            $dsnWithDb = $dsn . ";dbname={$this->config['database']}";
            $this->connection = new PDO($dsnWithDb, $this->config['username'], $this->config['password']);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // 检查并初始化表结构
            $this->ensureTables();
            $this->ensureRateLimitTables();
        } catch (PDOException $e) {
            // 如果连接失败，尝试创建数据库
            $this->createDatabaseAndConnect();
        }
    }

    /**
     * 创建数据库并重新连接
     */
    private function createDatabaseAndConnect() {
        try {
            // 解析主机和端口
            $host = $this->config['host'];
            $port = null;
            if (strpos($host, ':') !== false) {
                [$host, $port] = explode(':', $host, 2);
            }

            // 连接到MySQL服务器（不指定数据库）
            $dsn = "mysql:host={$host};charset={$this->config['charset']}";
            if ($port) {
                $dsn .= ";port={$port}";
            }

            $pdo = new PDO($dsn, $this->config['username'], $this->config['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // 创建数据库
            $dbName = $this->config['database'];
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET {$this->config['charset']} COLLATE {$this->config['charset']}_unicode_ci");

            // 连接到新创建的数据库
            $dsnWithDb = $dsn . ";dbname={$dbName}";
            $this->connection = new PDO($dsnWithDb, $this->config['username'], $this->config['password']);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // 创建表结构
            $this->createTables();
        } catch (PDOException $e) {
            // 创建失败，抛出异常
            throw new PDOException('数据库初始化失败: ' . $e->getMessage());
        }
    }

    /**
     * 确保表存在
     */
    private function ensureTables() {
        try {
            if (!$this->connection) {
                throw new PDOException('数据库连接不可用');
            }
            $stmt = $this->connection->query("SHOW TABLES LIKE 'countdown_config'");
            if ($stmt->rowCount() == 0) {
                $this->createTables();
            }
        } catch (PDOException $e) {
            $this->createTables();
        }
    }

    /**
     * 创建表结构和默认数据
     */
    private function createTables() {
        if (!$this->connection) {
            throw new PDOException('数据库连接不可用，无法创建表');
        }

        $sql = "
        CREATE TABLE IF NOT EXISTS countdown_config (
            id INT PRIMARY KEY AUTO_INCREMENT,
            page_type VARCHAR(20) NOT NULL COMMENT '页面类型: main, seconds',
            config_key VARCHAR(50) NOT NULL COMMENT '配置键',
            config_value TEXT COMMENT '配置值(base64编码)',
            UNIQUE KEY uk_page_key (page_type, config_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        $this->connection->exec($sql);

        // 创建名言投稿表
        $sql2 = "
        CREATE TABLE IF NOT EXISTS quote_submissions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            content TEXT NOT NULL COMMENT '投稿内容',
            submitter_name VARCHAR(100) DEFAULT '' COMMENT '投稿人',
            status ENUM('pending','approved','rejected') DEFAULT 'pending' COMMENT '状态',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '投稿时间',
            reviewed_at DATETIME DEFAULT NULL COMMENT '审核时间',
            review_notes VARCHAR(255) DEFAULT '' COMMENT '审核备注'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        $this->connection->exec($sql2);

        // 插入默认配置（如果不存在）
        $this->insertDefaultConfig('main');
        $this->insertDefaultConfig('seconds');
    }

    /**
     * 插入默认配置
     */
    private function insertDefaultConfig($pageType) {
        if ($pageType === 'main') {
            $defaults = [
                'target_date' => '2026-06-26',
                'title_font_size' => '32',
                'title_font_color' => '#ffffff',
                'title_font_family' => 'Arial, "Microsoft YaHei", sans-serif',
                'title_font_url' => '',
                'countdown_font_size' => '55',
                'countdown_font_color' => '#00a761',
                'countdown_font_family' => '"Courier New", monospace',
                'countdown_font_url' => '',
                'bg_color' => '#1a3a4e',
                'bg_image' => '',
                'bg_image_mode' => 'cover',
                'message_font_size' => '20',
                'message_font_color' => '#ffffff',
                'message_font_family' => 'Arial, "Microsoft YaHei", sans-serif',
                'message_font_url' => '',
                'message_container_width' => '90%',
                'message_interval' => '5000',
                'time_font_size' => '13',
                'time_font_color' => '#ffffff',
                'time_font_family' => '"Courier New", monospace',
                'time_bottom' => '12',
                'motivation_gap' => '4',
                'messages' => '奋斗不息，成功必将到来。|不要等待机会，而要创造机会。|坚持到底，永不放弃。|付出总有回报，梦想终会实现。'
            ];
        } else {
            $defaults = [
                'target_date' => '2026-06-26',
                'title_font_size' => '28',
                'title_font_color' => '#ffffff',
                'title_font_family' => 'Arial, "Microsoft YaHei", sans-serif',
                'title_font_url' => '',
                'countdown_font_size' => '50',
                'countdown_font_color' => '#2b7a05',
                'countdown_font_family' => '"Courier New", monospace',
                'countdown_font_url' => '',
                'bg_color' => '#222bdf',
                'bg_image' => '',
                'bg_image_mode' => 'cover',
                'message_font_size' => '18',
                'message_font_color' => '#ffffff',
                'message_font_family' => 'Arial, "Microsoft YaHei", sans-serif',
                'message_font_url' => '',
                'message_container_width' => '90%',
                'message_interval' => '5000',
                'time_font_size' => '13',
                'time_font_color' => '#ffffff',
                'time_font_family' => '"Courier New", monospace',
                'time_bottom' => '12',
                'motivation_gap' => '4',
                'messages' => '奋斗不息，成功必将到来。|不要等待机会，而要创造机会。|坚持到底，永不放弃。|付出总有回报，梦想终会实现。'
            ];
        }

        foreach ($defaults as $key => $value) {
            $encoded = $this->encodeValue($value);
            $sql = "INSERT IGNORE INTO countdown_config (page_type, config_key, config_value)
                    VALUES (?, ?, ?)";
            try {
                $stmt = $this->connection->prepare($sql);
                $stmt->execute([$pageType, $key, $encoded]);
            } catch (PDOException $e) {
                error_log('插入默认配置失败: ' . $e->getMessage());
            }
        }
    }

    public function getConnection() {
        return $this->connection;
    }

    /**
     * 获取指定页面的所有配置
     */
    public function getConfig($pageType) {
        // 如果数据库不可用，直接返回默认配置
        if (!$this->connection) {
            return $this->getDefaultConfig($pageType);
        }

        try {
            $this->ensureTableExists();

            $stmt = $this->connection->prepare(
                "SELECT config_key, config_value FROM countdown_config WHERE page_type = ?"
            );
            $stmt->execute([$pageType]);
            $result = $stmt->fetchAll();

            $config = [];
            foreach ($result as $row) {
                $key = $this->mapConfigKey($row['config_key']);
                $value = $this->decodeValue($row['config_value']);

                // 只保存非空值
                if ($value !== null && $value !== '') {
                    $config[$key] = $value;
                }
            }

            // 获取默认配置并合并
            $defaultConfig = $this->getDefaultConfig($pageType);
            $config = array_merge($defaultConfig, $config);

            // 确保关键字段存在
            if (!isset($config['target_date']) || empty($config['target_date'])) {
                $config['target_date'] = '2026-06-26';
            }

            return $config;
        } catch (Throwable $e) {
            $this->initError = $e->getMessage();
            error_log('获取配置失败: ' . $e->getMessage());
            return $this->getDefaultConfig($pageType);
        }
    }

     /**
      * 字段名映射（兼容旧版本数据）
      */
     private function mapConfigKey($key) {
         $map = [
             // 旧字段名 => 新字段名
             'font_size' => 'countdown_font_size',
             'font_color' => 'countdown_font_color',
             'font_family' => 'countdown_font_family',
         ];

         return $map[$key] ?? $key;
     }

    /**
     * 获取默认配置
     */
    private function getDefaultConfig($pageType) {
        if ($pageType === 'main') {
            return [
                'target_date' => '2026-06-26',
                'title_font_size' => '32',
                'title_font_color' => '#ffffff',
                'title_font_family' => 'Arial, "Microsoft YaHei", sans-serif',
                'title_font_url' => '',
                'countdown_font_size' => '55',
                'countdown_font_color' => '#00a761',
                'countdown_font_family' => '"Courier New", monospace',
                'countdown_font_url' => '',
                'bg_color' => '#1a3a4e',
                'bg_image' => '',
                'bg_image_mode' => 'cover',
                'message_font_size' => '20',
                'message_font_color' => '#ffffff',
                'message_font_family' => 'Arial, "Microsoft YaHei", sans-serif',
                'message_font_url' => '',
                'message_container_width' => '90%',
                'message_interval' => '5000',
                'time_font_size' => '13',
                'time_font_color' => '#ffffff',
                'time_font_family' => '"Courier New", monospace',
                'time_bottom' => '12',
                'motivation_gap' => '4',
                'messages' => '奋斗不息，成功必将到来。|不要等待机会，而要创造机会。|坚持到底，永不放弃。|付出总有回报，梦想终会实现。'
            ];
        } else {
            return [
                'target_date' => '2026-06-26',
                'title_font_size' => '28',
                'title_font_color' => '#ffffff',
                'title_font_family' => 'Arial, "Microsoft YaHei", sans-serif',
                'title_font_url' => '',
                'countdown_font_size' => '50',
                'countdown_font_color' => '#2b7a05',
                'countdown_font_family' => '"Courier New", monospace',
                'countdown_font_url' => '',
                'bg_color' => '#222bdf',
                'bg_image' => '',
                'bg_image_mode' => 'cover',
                'message_font_size' => '18',
                'message_font_color' => '#ffffff',
                'message_font_family' => 'Arial, "Microsoft YaHei", sans-serif',
                'message_font_url' => '',
                'message_container_width' => '90%',
                'message_interval' => '5000',
                'time_font_size' => '13',
                'time_font_color' => '#ffffff',
                'time_font_family' => '"Courier New", monospace',
                'time_bottom' => '12',
                'motivation_gap' => '4',
                'messages' => '奋斗不息，成功必将到来。|不要等待机会，而要创造机会。|坚持到底，永不放弃。|付出总有回报，梦想终会实现。'
            ];
        }
    }

    /**
     * 确保表存在（供外部调用）
     */
    public function ensureTableExists() {
        if (!$this->connection) {
            return;
        }
        try {
            $stmt = $this->connection->query("SHOW TABLES LIKE 'countdown_config'");
            if ($stmt->rowCount() == 0) {
                $this->createTables();
            }
        } catch (Throwable $e) {
            // 忽略错误
        }
    }

    /**
     * 确保名言投稿表存在（兼容已有数据库的升级）
     */
    public function ensureSubmissionTableExists() {
        if (!$this->connection) return;
        try {
            $stmt = $this->connection->query("SHOW TABLES LIKE 'quote_submissions'");
            if ($stmt->rowCount() == 0) {
                $sql = "
                CREATE TABLE IF NOT EXISTS quote_submissions (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    content TEXT NOT NULL COMMENT '投稿内容',
                    submitter_name VARCHAR(100) DEFAULT '' COMMENT '投稿人',
                    status ENUM('pending','approved','rejected') DEFAULT 'pending' COMMENT '状态',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '投稿时间',
                    reviewed_at DATETIME DEFAULT NULL COMMENT '审核时间',
                    review_notes VARCHAR(255) DEFAULT '' COMMENT '审核备注'
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ";
                $this->connection->exec($sql);
            }
        } catch (Throwable $e) {
            error_log('确保投稿表存在失败: ' . $e->getMessage());
        }
    }

    /**
     * 更新配置值
     */
    public function updateConfig($pageType, $key, $value) {
        if (!$this->connection) {
            return false;
        }

        $this->ensureTableExists();
        $encodedValue = $this->encodeValue($value);

        try {
            $stmt = $this->connection->prepare(
                "INSERT INTO countdown_config (page_type, config_key, config_value)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE config_value = ?"
            );
            return $stmt->execute([$pageType, $key, $encodedValue, $encodedValue]);
        } catch (Throwable $e) {
            error_log('保存配置失败: ' . $e->getMessage() . ' - Key: ' . $key . ', PageType: ' . $pageType);
            return false;
        }
    }

    /**
     * 批量更新配置（事务保护，全部成功或全部回滚）
     * @param string $pageType 页面类型（main/seconds）
     * @param array $config 配置键值对，如 ['target_date' => '2026-06-26', 'messages' => '...']
     * @return bool 全部成功返回 true，任一失败自动回滚并返回 false
     */
    public function saveConfigBatch($pageType, $config) {
        if (!$this->connection) {
            return false;
        }

        if (!is_array($config) || empty($config)) {
            error_log('批量保存配置失败: config 为空或非数组');
            return false;
        }

        $this->ensureTableExists();

        try {
            $this->connection->beginTransaction();

            $stmt = $this->connection->prepare(
                "INSERT INTO countdown_config (page_type, config_key, config_value)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE config_value = ?"
            );

            foreach ($config as $key => $value) {
                $encodedValue = $this->encodeValue((string)$value);
                $success = $stmt->execute([$pageType, $key, $encodedValue, $encodedValue]);
                if (!$success) {
                    throw new RuntimeException("写入配置 '$key' 失败");
                }
            }

            $this->connection->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            error_log('批量保存配置失败（已回滚）: ' . $e->getMessage() . ' - PageType: ' . $pageType);
            return false;
        }
    }

    /**
     * 确保限流表存在（登录锁定 + 投稿限流共用）
     */
    public function ensureRateLimitTables() {
        if (!$this->connection) return;
        try {
            $this->connection->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                ip VARCHAR(45) PRIMARY KEY,
                failed_count INT NOT NULL DEFAULT 0,
                locked_until DATETIME DEFAULT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            $this->connection->exec("
            CREATE TABLE IF NOT EXISTS rate_limits (
                ip VARCHAR(45) NOT NULL,
                action VARCHAR(40) NOT NULL,
                count INT NOT NULL DEFAULT 0,
                window_start DATETIME NOT NULL,
                PRIMARY KEY (ip, action)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } catch (Throwable $e) {
            error_log('确保限流表存在失败: ' . $e->getMessage());
        }
    }

    // ========== 登录限流 ==========

    /**
     * 检查 IP 是否处于登录锁定中
     * @return int 剩余锁定秒数（0 表示未锁定）
     */
    public function checkLoginLocked($ip) {
        if (!$this->connection) return 0;
        $this->ensureRateLimitTables();
        try {
            $stmt = $this->connection->prepare(
                "SELECT locked_until FROM login_attempts WHERE ip = ?"
            );
            $stmt->execute([$ip]);
            $row = $stmt->fetch();
            if (!$row || empty($row['locked_until'])) return 0;
            $remaining = strtotime($row['locked_until']) - time();
            return $remaining > 0 ? $remaining : 0;
        } catch (Throwable $e) {
            error_log('checkLoginLocked 失败: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 记录一次登录失败；连续失败达到阈值后锁定
     */
    public function recordLoginFailure($ip) {
        if (!$this->connection) return;
        $this->ensureRateLimitTables();
        // 与 Auth::LOCKOUT_THRESHOLD / LOCKOUT_MINUTES 保持一致（避免循环依赖，不直接引用）
        $threshold = 5;
        $minutes = 15;
        try {
            $stmt = $this->connection->prepare(
                "INSERT INTO login_attempts (ip, failed_count, locked_until)
                 VALUES (?, 1, NULL)
                 ON DUPLICATE KEY UPDATE
                     failed_count = LEAST(failed_count + 1, 1000),
                     locked_until = IF(failed_count + 1 >= {$threshold}, DATE_ADD(NOW(), INTERVAL {$minutes} MINUTE), locked_until)"
            );
            $stmt->execute([$ip]);
        } catch (Throwable $e) {
            error_log('recordLoginFailure 失败: ' . $e->getMessage());
        }
    }

    /**
     * 登录成功后清除失败记录
     */
    public function clearLoginFailures($ip) {
        if (!$this->connection) return;
        $this->ensureRateLimitTables();
        try {
            $stmt = $this->connection->prepare("DELETE FROM login_attempts WHERE ip = ?");
            $stmt->execute([$ip]);
        } catch (Throwable $e) {
            error_log('clearLoginFailures 失败: ' . $e->getMessage());
        }
    }

    // ========== 通用限流（投稿等公开接口） ==========

    /**
     * 滑动窗口限流：
     * - 窗口内未超限：计数 +1，返回 true（允许）
     * - 窗口内已超限：返回 false（拒绝）
     * - 窗口过期：重置计数，返回 true
     * @param string $ip 客户端 IP
     * @param string $action 动作标识，如 submit_quote
     * @param int $max 窗口内最大次数
     * @param int $windowSec 窗口时长（秒）
     * @return bool 是否允许本次请求
     */
    public function checkRateLimit($ip, $action, $max, $windowSec) {
        if (!$this->connection) return true; // 数据库不可用时放行，避免误伤正常用户
        $this->ensureRateLimitTables();
        try {
            $stmt = $this->connection->prepare(
                "SELECT count, window_start FROM rate_limits WHERE ip = ? AND action = ?"
            );
            $stmt->execute([$ip, $action]);
            $row = $stmt->fetch();
            $now = time();

            if (!$row) {
                $stmt = $this->connection->prepare(
                    "INSERT INTO rate_limits (ip, action, count, window_start) VALUES (?, ?, 1, FROM_UNIXTIME(?))"
                );
                $stmt->execute([$ip, $action, $now]);
                return true;
            }

            $windowStart = strtotime($row['window_start']);
            if ($now - $windowStart >= $windowSec) {
                // 窗口已过期：重置
                $stmt = $this->connection->prepare(
                    "UPDATE rate_limits SET count = 1, window_start = FROM_UNIXTIME(?) WHERE ip = ? AND action = ?"
                );
                $stmt->execute([$now, $ip, $action]);
                return true;
            }

            if ((int)$row['count'] >= $max) {
                return false; // 超限
            }

            $stmt = $this->connection->prepare(
                "UPDATE rate_limits SET count = count + 1 WHERE ip = ? AND action = ?"
            );
            $stmt->execute([$ip, $action]);
            return true;
        } catch (Throwable $e) {
            error_log('checkRateLimit 失败: ' . $e->getMessage());
            return true; // 限流组件异常时不阻塞正常用户
        }
    }

    /**
     * Base64编码
     */
    private function encodeValue($value) {
        return base64_encode($value);
    }

    /**
     * Base64解码
     */
    private function decodeValue($value) {
        return base64_decode($value);
    }

    // ========== 名言投稿系统 ==========

    /**
     * 添加投稿
     */
    public function addQuoteSubmission($content, $submitterName = '') {
        if (!$this->connection) return false;
        $this->ensureSubmissionTableExists();
        try {
            $stmt = $this->connection->prepare(
                "INSERT INTO quote_submissions (content, submitter_name) VALUES (?, ?)"
            );
            return $stmt->execute([$content, $submitterName]);
        } catch (Throwable $e) {
            error_log('添加投稿失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取最后插入的ID
     */
    public function getLastInsertId() {
        if (!$this->connection) return 0;
        try {
            return (int)$this->connection->lastInsertId();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * 获取待审核投稿
     */
    public function getSubmissions($status = 'pending') {
        if (!$this->connection) return [];
        $this->ensureSubmissionTableExists();
        try {
            $stmt = $this->connection->prepare(
                "SELECT id, content, submitter_name, status, created_at FROM quote_submissions WHERE status = ? ORDER BY created_at DESC"
            );
            $stmt->execute([$status]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            error_log('获取投稿失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取所有投稿（用于审核列表）
     */
    public function getAllSubmissions() {
        if (!$this->connection) return [];
        $this->ensureSubmissionTableExists();
        try {
            $stmt = $this->connection->query(
                "SELECT id, content, submitter_name, status, created_at, reviewed_at FROM quote_submissions ORDER BY FIELD(status,'pending','approved','rejected'), created_at DESC"
            );
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            error_log('获取所有投稿失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 审核投稿（通过/拒绝）
     */
    public function reviewSubmission($id, $status) {
        if (!$this->connection) return false;
        $this->ensureSubmissionTableExists();
        try {
            $stmt = $this->connection->prepare(
                "UPDATE quote_submissions SET status = ?, reviewed_at = NOW() WHERE id = ?"
            );
            return $stmt->execute([$status, $id]);
        } catch (Throwable $e) {
            error_log('审核投稿失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 将通过的名言合并到指定页面的励志话语中
     * 只合并未合并到当前页面的名言，每条名言每个页面只合并一次
     */
    public function mergeApprovedQuotes($pageType) {
        if (!$this->connection) return false;
        $this->ensureSubmissionTableExists();
        try {
            // 只获取已通过、但尚未合并到当前页面的投稿
            // review_notes 格式: "merged:main;merged:seconds;" ，通过 LIKE 精确判断
            $mergeMarker = "%merged:{$pageType};%";
            $stmt = $this->connection->prepare(
                "SELECT id, content FROM quote_submissions 
                 WHERE status = 'approved' 
                 AND reviewed_at IS NOT NULL 
                 AND (review_notes NOT LIKE ? OR review_notes = '')
                 ORDER BY id ASC"
            );
            $stmt->execute([$mergeMarker]);
            $approvedQuotes = $stmt->fetchAll();
            if (empty($approvedQuotes)) return true;

            // 获取当前messages配置
            $config = $this->getConfig($pageType);
            $existingMessages = explode('|', $config['messages'] ?? '');
            $existingMessages = array_map('trim', $existingMessages);
            $existingSet = array_flip($existingMessages);

            $newQuotes = [];
            $mergedIds = [];
            foreach ($approvedQuotes as $quote) {
                // 服务端白名单净化：即使历史数据含恶意 HTML 也不会进入页面
                $content = trim(HtmlSanitizer::sanitize($quote['content']));
                if ($content === '') {
                    continue;
                }
                // 内容级去重：避免因 review_notes 异常导致重复
                if (isset($existingSet[$content])) {
                    // 内容已存在但未标记，补标记
                    $mergedIds[] = $quote['id'];
                    continue;
                }
                $newQuotes[] = $content;
                $existingSet[$content] = true;
                $mergedIds[] = $quote['id'];
            }

            // 有新增内容才写 messages
            if (!empty($newQuotes)) {
                $mergedMessages = array_merge($existingMessages, $newQuotes);
                $mergedStr = implode('|', array_filter($mergedMessages));
                $encoded = $this->encodeValue($mergedStr);

                $stmt2 = $this->connection->prepare(
                    "INSERT INTO countdown_config (page_type, config_key, config_value)
                     VALUES (?, 'messages', ?)
                     ON DUPLICATE KEY UPDATE config_value = ?"
                );
                $stmt2->execute([$pageType, $encoded, $encoded]);
            }

            // 标记这些投稿为已合并到当前页面
            if (!empty($mergedIds)) {
                $placeholders = implode(',', array_fill(0, count($mergedIds), '?'));
                $stmt3 = $this->connection->prepare(
                    "UPDATE quote_submissions SET review_notes = CONCAT(IFNULL(review_notes,''), 'merged:{$pageType};') WHERE id IN ($placeholders)"
                );
                $stmt3->execute($mergedIds);
            }

            return true;
        } catch (Throwable $e) {
            error_log('合并名言失败: ' . $e->getMessage());
            return false;
        }
    }
}
