<?php
/**
 * 内容池管理读取API（仅登录管理员）
 * 返回：模块全量配置 + 本地模块条目列表
 */
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/EnvLoader.php';
require_once __DIR__ . '/../includes/Auth.php';

$auth = Auth::getInstance();
if (!$auth->isAuthenticated()) {
    Auth::sendUnauthorized('未授权访问：请先登录管理后台');
}

require_once __DIR__ . '/../includes/Database.php';

try {
    $database = Database::getInstance();
    $pdo = $database->getConnection();
    if (!$pdo) {
        throw new RuntimeException('数据库不可用');
    }

    // 模块全量（含禁用）
    $modules = $pdo->query(
        "SELECT module_key, module_name, module_type, weight, enabled, source_url, source_type, ttl_seconds, sort_order
         FROM content_modules ORDER BY sort_order"
    )->fetchAll();

    // 本地模块条目（motivation 也返回，兼容后续管理；quote/knowledge 为正文内容）
    $items = [];
    $stmt = $pdo->prepare(
        "SELECT id, module_key, content, tag, detail, status FROM content_items WHERE module_key = ? ORDER BY id ASC"
    );
    foreach ($modules as $m) {
        if ($m['module_type'] !== 'local') continue;
        $stmt->execute([$m['module_key']]);
        $items[$m['module_key']] = $stmt->fetchAll();
    }

    echo json_encode(['modules' => $modules, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log('get_content_admin.php 错误: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => '服务器错误，请稍后重试'], JSON_UNESCAPED_UNICODE);
}
