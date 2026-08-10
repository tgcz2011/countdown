<?php
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/Database.php';

try {
    $idsInput = $_GET['ids'] ?? '';
    if (!$idsInput) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    $ids = array_map('intval', explode(',', $idsInput));
    $ids = array_filter($ids, fn($id) => $id > 0);
    if (empty($ids)) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    $database = Database::getInstance();
    $conn = $database->getConnection();
    if (!$conn) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    // 未鉴权接口只返回 id 和 status，不返回内容（2026-08-10 安全加固）
    $stmt = $conn->prepare(
        "SELECT id, status FROM quote_submissions WHERE id IN ($placeholders) ORDER BY id DESC"
    );
    $stmt->execute(array_values($ids));
    $results = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $results], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器错误: ' . $e->getMessage()]);
}
