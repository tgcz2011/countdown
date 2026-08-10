<?php
/**
 * 获取服务器时间API
 * 返回当前PHP服务器时间戳，用于校准客户端时间
 * 使用北京时间确保与中国用户一致
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// 设置时区为北京时间
date_default_timezone_set('Asia/Shanghai');

// 返回北京时间戳（秒）
echo json_encode([
    'timestamp' => time(),
    'timezone' => 'Asia/Shanghai',
    'server_time' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE);
