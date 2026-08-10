<?php
/**
 * 保存配置API
 */
// 清除所有输出缓冲，确保只返回JSON
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

    // 只接受POST请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => '仅支持POST请求']);
        exit;
    }

    // 获取原始POST数据
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '无效的数据格式']);
        exit;
    }

    // 验证必要字段
    $requiredFields = ['page_type', 'config_key', 'config_value'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "缺少必要字段: $field"]);
            exit;
        }
    }

    $pageType = $data['page_type'];
    $configKey = $data['config_key'];
    $configValue = $data['config_value'];

    // 验证页面类型
    if (!in_array($pageType, ['main', 'seconds'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '无效的页面类型']);
        exit;
    }

    // 服务端白名单净化 messages（2026-08-10 安全加固）
    require_once __DIR__ . '/../includes/HtmlSanitizer.php';
    if ($configKey === 'messages') {
        $configValue = HtmlSanitizer::sanitize($configValue);
    }
    if (mb_strlen($configValue) > 20000) {
        $configValue = mb_substr($configValue, 0, 20000);
    }

    // 保存配置
    $success = $database->updateConfig($pageType, $configKey, $configValue);

    if ($success) {
        echo json_encode(['success' => true, 'message' => '保存成功']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '保存失败']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器错误: ' . $e->getMessage()]);
}
