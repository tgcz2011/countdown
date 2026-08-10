<?php
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/Database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => '仅支持POST请求']);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!$data || empty($data['content'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '请输入名言内容']);
        exit;
    }

    $content = trim($data['content']);
    $submitterName = trim($data['submitter_name'] ?? '');

    if (mb_strlen($content) < 2) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '名言内容至少2个字符']);
        exit;
    }
    if (mb_strlen($content) > 500) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '名言内容不能超过500个字符']);
        exit;
    }
    if (mb_strlen($submitterName) > 50) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '昵称不能超过50个字符']);
        exit;
    }

    // 服务端白名单净化：只保留 b/i/u/em/strong 纯文本标签（2026-08-10 安全加固）
    require_once __DIR__ . '/../includes/HtmlSanitizer.php';
    $content = HtmlSanitizer::sanitize($content);
    $submitterName = htmlspecialchars($submitterName, ENT_QUOTES, 'UTF-8');

    if ($content === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '名言内容无效，请重新输入']);
        exit;
    }

    $database = Database::getInstance();

    // 限流：同一 IP 每 10 分钟最多 5 次投稿（2026-08-10 安全加固）
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!$database->checkRateLimit($ip, 'submit_quote', 5, 600)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => '投稿太频繁，请 10 分钟后再试']);
        exit;
    }

    $result = $database->addQuoteSubmission($content, $submitterName);

    if ($result) {
        $newId = $database->getLastInsertId();
        echo json_encode([
            'success' => true,
            'message' => '投稿成功！审核通过后将会显示在页面上。',
            'id' => $newId
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '投稿失败，请稍后重试']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器错误: ' . $e->getMessage()]);
}
