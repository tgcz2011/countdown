<?php
/**
 * 内容池管理保存API（仅登录管理员，事务保护）
 *
 * 请求体 JSON:
 * {
 *   "modules": [   // 模块全量配置（缺失的字段保持原值）
 *     {"module_key":"quote","weight":20,"enabled":1,"source_url":null,"source_type":null,"ttl_seconds":86400}, ...
 *   ],
 *   "items": {     // 本地模块内容全量替换（key=module_key, value=条目数组）
 *     "quote":     [{"content":"...","tag":"...","detail":"..."}, ...],
 *     "knowledge": [{"content":"...","tag":"...","detail":"..."}, ...]
 *   }
 * }
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
require_once __DIR__ . '/../includes/HtmlSanitizer.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => '仅支持POST请求']);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '无效的数据格式']);
        exit;
    }

    $database = Database::getInstance();
    $pdo = $database->getConnection();
    if (!$pdo) {
        throw new RuntimeException('数据库不可用');
    }

    $pdo->beginTransaction();

    // ---------- 1. 模块配置 ----------
    if (isset($data['modules']) && is_array($data['modules'])) {
        $known = [];
        $rows = $pdo->query("SELECT module_key FROM content_modules")->fetchAll(PDO::FETCH_COLUMN);
        $known = array_flip($rows);

        $updModule = $pdo->prepare(
            "UPDATE content_modules SET weight = ?, enabled = ?, source_url = ?, source_type = ?, ttl_seconds = ? WHERE module_key = ?"
        );
        foreach ($data['modules'] as $m) {
            if (!is_array($m) || empty($m['module_key']) || !isset($known[$m['module_key']])) continue;
            $weight = max(1, min(100, (int)($m['weight'] ?? 10)));
            $enabled = !empty($m['enabled']) ? 1 : 0;
            $sourceUrl = isset($m['source_url']) ? trim((string)$m['source_url']) : '';
            if ($sourceUrl !== '' && !preg_match('#^https?://#i', $sourceUrl)) {
                $sourceUrl = '';
            }
            if (mb_strlen($sourceUrl) > 500) $sourceUrl = mb_substr($sourceUrl, 0, 500);
            $sourceType = isset($m['source_type']) ? trim((string)$m['source_type']) : '';
            if (mb_strlen($sourceType) > 50) $sourceType = mb_substr($sourceType, 0, 50);
            $ttl = max(60, min(604800, (int)($m['ttl_seconds'] ?? 86400)));
            $updModule->execute([$weight, $enabled, $sourceUrl !== '' ? $sourceUrl : null, $sourceType !== '' ? $sourceType : null, $ttl, $m['module_key']]);
        }
    }

    // ---------- 2. 本地模块内容全量替换 ----------
    if (isset($data['items']) && is_array($data['items'])) {
        $delItem = $pdo->prepare("DELETE FROM content_items WHERE module_key = ?");
        $insItem = $pdo->prepare(
            "INSERT INTO content_items (module_key, content, tag, detail, status, source) VALUES (?, ?, ?, ?, 'active', 'admin')"
        );
        foreach ($data['items'] as $moduleKey => $list) {
            if (!is_string($moduleKey) || !is_array($list)) continue;
            // 仅允许本地模块
            $type = $pdo->prepare("SELECT module_type FROM content_modules WHERE module_key = ?");
            $type->execute([$moduleKey]);
            $mt = $type->fetchColumn();
            if ($mt !== 'local') continue;

            $delItem->execute([$moduleKey]);
            $seen = [];
            foreach ($list as $it) {
                if (!is_array($it)) continue;
                $content = HtmlSanitizer::sanitize(trim((string)($it['content'] ?? '')));
                if ($content === '' || isset($seen[$content])) continue;
                $seen[$content] = true;
                $tag = isset($it['tag']) ? HtmlSanitizer::sanitize(trim((string)$it['tag'])) : '';
                $detail = isset($it['detail']) ? HtmlSanitizer::sanitize(trim((string)$it['detail'])) : '';
                if (mb_strlen($tag) > 100) $tag = mb_substr($tag, 0, 100);
                if (mb_strlen($content) > 2000) $content = mb_substr($content, 0, 2000);
                if (mb_strlen($detail) > 3000) $detail = mb_substr($detail, 0, 3000);
                $insItem->execute([$moduleKey, $content, $tag !== '' ? $tag : null, $detail !== '' ? $detail : null]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => '内容池已保存'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        try { $pdo->rollBack(); } catch (Throwable $ignored) {}
    }
    error_log('save_content.php 错误: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器错误，请稍后重试'], JSON_UNESCAPED_UNICODE);
}
