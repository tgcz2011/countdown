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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => '仅支持POST请求']);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!$data || !isset($data['id']) || !isset($data['action'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '缺少必要字段']);
        exit;
    }

    $id = intval($data['id']);
    $action = $data['action'];

    if (!in_array($action, ['approve', 'reject'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '无效的操作']);
        exit;
    }

    $status = $action === 'approve' ? 'approved' : 'rejected';

    $database = Database::getInstance();

    // 更新状态
    $result = $database->reviewSubmission($id, $status);
    if (!$result) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '审核失败']);
        exit;
    }

    // 通过后合并到统一配置（主页面与秒数页面共用）
    if ($action === 'approve') {
        $mergeOk = $database->mergeApprovedQuotes();
        if (!$mergeOk) {
            error_log('合并名言到页面配置时出现问题');
        }
    }

    echo json_encode(['success' => true, 'message' => $action === 'approve' ? '已通过' : '已拒绝']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器错误: ' . $e->getMessage()]);
}
