<?php
/**
 * 获取配置API
 */
// 清除所有输出缓冲
while (ob_get_level()) {
    ob_end_clean();
}

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 设置时区为北京时间
date_default_timezone_set('Asia/Shanghai');

// 错误处理：只把致命错误转为异常，警告/弃用提示仅记日志（避免 500）
set_error_handler(function($severity, $message, $file, $line) {
    $fatal = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
    if (in_array($severity, $fatal, true)) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    error_log('get_config.php 警告: ' . $message . ' in ' . $file . ':' . $line);
    return true;
});

try {
    require_once __DIR__ . '/../includes/Database.php';

    $page = $_GET['page'] ?? 'main';
    if (!in_array($page, ['main', 'seconds'])) {
        $page = 'main';
    }

    $database = Database::getInstance();
    $config = $database->getConfig($page);

    // 确保必要字段存在
    $defaults = [
        'target_date' => '2026-06-26',
        'title_font_size' => $page === 'main' ? '32' : '28',
        'title_font_color' => '#ffffff',
        'title_font_family' => 'Arial, "Microsoft YaHei", sans-serif',
        'title_font_url' => '',
        'countdown_font_size' => $page === 'main' ? '55' : '50',
        'countdown_font_color' => $page === 'main' ? '#00a761' : '#2b7a05',
        'countdown_font_family' => '"Courier New", monospace',
        'countdown_font_url' => '',
        'bg_color' => $page === 'main' ? '#1a3a4e' : '#222bdf',
        'bg_image' => '',
        'bg_image_mode' => 'cover',
        'message_font_size' => $page === 'main' ? '20' : '18',
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

    foreach ($defaults as $key => $value) {
        if (!isset($config[$key]) || $config[$key] === '') {
            $config[$key] = $value;
        }
    }

    // 计算目标日期的时间戳（北京时间当天的0点）
    if (!empty($config['target_date'])) {
        $targetDateTime = DateTime::createFromFormat('Y-m-d', $config['target_date'], new DateTimeZone('Asia/Shanghai'));
        if ($targetDateTime !== false) {
            $targetDateTime->setTime(0, 0, 0);
            $config['target_timestamp'] = $targetDateTime->getTimestamp() * 1000; // 转为毫秒
        } else {
            error_log('get_config.php: 无效的目标日期格式: ' . $config['target_date']);
            $config['target_timestamp'] = 0;
        }
    } else {
        $config['target_timestamp'] = 0;
    }

    // 输出时净化 messages：即使数据库里已有历史恶意数据也不会进入页面（2026-08-10 安全加固）
    require_once __DIR__ . '/../includes/HtmlSanitizer.php';
    if (isset($config['messages'])) {
        $config['messages'] = HtmlSanitizer::sanitize($config['messages']);
    }

    // 输出JSON
    $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        throw new RuntimeException('JSON编码失败: ' . json_last_error_msg());
    }

    echo $json;
} catch (Throwable $e) {
    // 记录错误到服务器日志
    error_log('get_config.php 错误: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    // 返回错误JSON（不暴露服务器内部路径与行号）
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => '服务器错误，请稍后重试'
    ], JSON_UNESCAPED_UNICODE);
}
