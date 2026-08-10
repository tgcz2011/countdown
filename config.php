<?php
/**
 * 数据库配置文件
 *
 * 首次访问会自动创建数据库和表
 * 现在使用环境变量，避免密码硬编码
 *
 * 连接失败排查：
 * 1. host: 如果连接失败，尝试不同的主机地址
 *    - localhost (Unix socket)
 *    - 127.0.0.1 (TCP)
 *    - mysql.yourdomain.com (某些主机)
 * 2. port: 如果主机包含端口，格式如 '127.0.0.1:3307'
 * 3. 查看主机控制台的"数据库信息"获取正确连接参数
 */

// 加载环境变量
require_once __DIR__ . '/includes/EnvLoader.php';
EnvLoader::load();

return [
    // 数据库主机（从 .env 读取；无默认值，避免泄露真实凭据）
    'host' => EnvLoader::get('DB_HOST', ''),

    // 数据库用户名
    'username' => EnvLoader::get('DB_USERNAME', ''),

    // 数据库密码（从环境变量获取）
    'password' => EnvLoader::get('DB_PASSWORD', ''),

    // 数据库名称
    'database' => EnvLoader::get('DB_NAME', ''),

    // 字符集
    'charset' => EnvLoader::get('DB_CHARSET', 'utf8mb4')
];


