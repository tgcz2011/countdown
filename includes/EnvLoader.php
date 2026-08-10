<?php
/**
 * 环境变量加载器
 * 从 .env 文件中加载配置，避免密码硬编码
 */
class EnvLoader {
    private static $loaded = false;
    private static $envPath = null;
    
    /**
     * 加载 .env 文件
     * @param string|null $path .env 文件路径，默认自动检测
     */
    public static function load($path = null) {
        if (self::$loaded) {
            return;
        }
        
        if ($path === null) {
            // 标准位置：项目根目录（includes/ 的上一级）；找不到再回退到 includes/ 同级
            $path = dirname(__DIR__) . '/.env';
            if (!file_exists($path)) {
                $path = __DIR__ . '/.env';
            }
        }

        if (!file_exists($path)) {
            // 不抛异常：让调用方使用默认值/拒绝服务，避免整站白屏
            error_log('EnvLoader: .env 文件未找到（' . $path . '）');
            self::$loaded = true;
            return;
        }
        
        self::$envPath = $path;
        
        // 解析 .env 文件
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            
            // 跳过注释和空行
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            // 解析 KEY=VALUE
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            
            // 移除引号
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            
            // 设置环境变量（不覆盖已存在的）
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }
            putenv("{$key}={$value}");
        }
        
        self::$loaded = true;
    }
    
    /**
     * 获取环境变量值
     * @param string $key 变量名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }
        
        // 优先从 $_ENV 获取，然后从 getenv 获取
        $value = $_ENV[$key] ?? getenv($key);
        
        if ($value === false || $value === null) {
            return $default;
        }
        
        return $value;
    }
    
    /**
     * 检查是否已加载
     * @return bool
     */
    public static function isLoaded() {
        return self::$loaded;
    }
    
    /**
     * 获取 .env 文件路径
     * @return string|null
     */
    public static function getPath() {
        return self::$envPath;
    }
}