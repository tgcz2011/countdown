<?php
/**
 * 批量保存配置API（事务保护，全部成功或全部回滚）
 *
 * 请求体 JSON:
 * {
 *     "page_type": "main" | "seconds",
 *     "config": {
 *         "target_date": "2027-06-07",
 *         "messages": "...",
 *         ...
 *     }
 * }
 */
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

// 鉴权：只有登录管理员才能修改配置（2026-08-10 安全加固）
require_once __DIR__ . '/../includes/EnvLoader.php';
require_once __DIR__ . '/../includes/Auth.php';

$auth = Auth::getInstance();
if (!$auth->isAuthenticated()) {
    Auth::sendUnauthorized('未授权访问：请先登录管理后台');
}

require_once __DIR__ . '/../includes/Database.php';

try {
    $database = Database::getInstance();

    // 只接受 POST 请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => '仅支持POST请求']);
        exit;
    }

    // 获取原始 POST 数据
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '无效的数据格式']);
        exit;
    }

    // 验证必要字段
    if (!isset($data['page_type']) || !isset($data['config'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少必要字段: page_type 和 config']);
        exit;
    }

    $pageType = $data['page_type'];
    $config = $data['config'];

    // 验证页面类型
    if (!in_array($pageType, ['main', 'seconds'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '无效的页面类型']);
        exit;
    }

    // 验证 config 是有效的键值对
    if (!is_array($config) || empty($config)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'config 必须是非空对象']);
        exit;
    }

    // 服务端白名单净化 messages（防止存储型 XSS；2026-08-10 安全加固）
    require_once __DIR__ . '/../includes/HtmlSanitizer.php';
    if (isset($config['messages'])) {
        $config['messages'] = HtmlSanitizer::sanitize($config['messages']);
    }
    // 限制单个字段长度，防止超长值写入
    foreach ($config as $key => $value) {
        if (is_string($value) && mb_strlen($value) > 20000) {
            $config[$key] = mb_substr($value, 0, 20000);
        }
    }

    // 批量保存（事务保护）
    $success = $database->saveConfigBatch($pageType, $config);

    if ($success) {
        echo json_encode(['success' => true, 'message' => '所有配置已保存']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '保存失败，数据库操作被回滚，请重试']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器错误: ' . $e->getMessage()]);
}