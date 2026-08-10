<?php
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');

// 使用Auth类进行session/token认证
require_once __DIR__ . '/../includes/EnvLoader.php';
require_once __DIR__ . '/../includes/Auth.php';

$auth = Auth::getInstance();
if (!$auth->isAuthenticated()) {
    Auth::sendUnauthorized();
}

require_once __DIR__ . '/../includes/Database.php';

try {
    $database = Database::getInstance();
    $status = $_GET['status'] ?? 'all';

    if ($status === 'all') {
        $submissions = $database->getAllSubmissions();
    } else {
        $submissions = $database->getSubmissions($status);
    }

    echo json_encode(['success' => true, 'data' => $submissions], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器错误: ' . $e->getMessage()]);
}
