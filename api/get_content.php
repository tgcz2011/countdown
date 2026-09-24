<?php
/**
 * 内容池API：按模块权重返回轮播内容（多模块 + 在线源服务器被动缓存）
 *
 * - 本地模块：motivation 读 countdown_config.messages（含审核投稿自动合并）+ content_items 去重兜底；
 *             quote/knowledge 读 content_items（支持 tag/detail 结构化考点）
 * - 在线模块：读 content_cache；TTL 过期时尝试拉取上游，成功更新缓存、失败沿用旧缓存（并有冷却避免频繁打上游）
 * - 任何异常：返回固定 500 JSON，绝不回传内部细节
 */
while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Shanghai');

set_error_handler(function($severity, $message, $file, $line) {
    $fatal = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
    if (in_array($severity, $fatal, true)) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    error_log('get_content.php 警告: ' . $message . ' in ' . $file . ':' . $line);
    return true;
});

try {
    require_once __DIR__ . '/../includes/Database.php';
    require_once __DIR__ . '/../includes/HtmlSanitizer.php';

    $db = Database::getInstance();
    $pdo = $db->getConnection();
    if (!$pdo) {
        throw new RuntimeException('数据库不可用');
    }

    // 1. 读取启用模块（按 sort_order）
    $stmt = $pdo->query(
        "SELECT module_key, module_name, module_type, weight, source_url, source_type, ttl_seconds
         FROM content_modules WHERE enabled = 1 ORDER BY sort_order"
    );
    $modules = $stmt->fetchAll();

    // 2. 组装各模块内容
    $result = [];
    $itemStmt = $pdo->prepare(
        "SELECT module_key, content, tag, detail FROM content_items WHERE module_key = ? AND status = 'active' ORDER BY id ASC"
    );
    $config = null;

    foreach ($modules as $m) {
        $items = [];
        $seen = [];

        if ($m['module_type'] === 'local') {
            // motivation 权威来源：messages（手工编辑 + 审核投稿自动合并）
            if ($m['module_key'] === 'motivation') {
                if ($config === null) {
                    $config = $db->getConfig();
                }
                $parts = array_filter(array_map('trim', explode('|', $config['messages'] ?? '')));
                foreach ($parts as $p) {
                    $p = HtmlSanitizer::sanitize($p);
                    if ($p === '' || isset($seen[$p])) continue;
                    $seen[$p] = true;
                    $items[] = ['content' => $p, 'tag' => null, 'detail' => null];
                }
            }
            // content_items 补充（motivation 也允许，统一去重）
            $itemStmt->execute([$m['module_key']]);
            foreach ($itemStmt->fetchAll() as $r) {
                $c = HtmlSanitizer::sanitize($r['content']);
                if ($c === '' || isset($seen[$c])) continue;
                $seen[$c] = true;
                $items[] = [
                    'content' => $c,
                    'tag'     => $r['tag'] !== null ? HtmlSanitizer::sanitize($r['tag']) : null,
                    'detail'  => $r['detail'] !== null ? HtmlSanitizer::sanitize($r['detail']) : null,
                ];
            }
        } else {
            $items = fetchOnlineItems($pdo, $m);
        }

        $result[] = [
            'module_key'  => $m['module_key'],
            'module_name' => $m['module_name'],
            'weight'      => (int)$m['weight'],
            'items'       => $items,
        ];
    }

    echo json_encode(
        ['modules' => $result, 'generated_at' => date('Y-m-d H:i:s')],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
} catch (Throwable $e) {
    error_log('get_content.php 错误: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['error' => true, 'message' => '服务器错误，请稍后重试'], JSON_UNESCAPED_UNICODE);
}

/**
 * 在线模块：缓存读取 + TTL 判定 + 被动刷新（失败冷却 + 旧缓存兜底）
 */
function fetchOnlineItems(PDO $pdo, array $m): array {
    $key = $m['module_key'];
    $ttl = max(60, (int)$m['ttl_seconds']);
    $sourceUrl = (string)($m['source_url'] ?? '');
    $sourceType = (string)($m['source_type'] ?? '');

    // 读缓存
    $cached = null;
    $stmt = $pdo->prepare("SELECT payload, fetched_at FROM content_cache WHERE source_key = ?");
    $stmt->execute([$key]);
    if ($row = $stmt->fetch()) {
        $cached = ['payload' => $row['payload'], 'fetched_at' => $row['fetched_at']];
    }

    $needRefresh = true;
    if ($cached && $cached['fetched_at']) {
        $age = time() - strtotime($cached['fetched_at']);
        if ($age >= 0 && $age < $ttl) {
            $needRefresh = false;
        }
    }

    $payload = $cached ? $cached['payload'] : null;

    if ($needRefresh && $sourceUrl !== '' && $sourceType !== '') {
        $fetched = fetchFromUpstream($sourceUrl, $sourceType);
        if ($fetched !== null) {
            $payload = $fetched;
            $upsert = $pdo->prepare(
                "INSERT INTO content_cache (source_key, payload, fetched_at) VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE payload = VALUES(payload), fetched_at = NOW()"
            );
            $upsert->execute([$key, $fetched]);
        } elseif ($cached) {
            // 拉取失败：沿用旧缓存，并把 fetched_at 推到当前（冷却，避免每次访问都打上游）
            $pdo->prepare("UPDATE content_cache SET fetched_at = NOW() WHERE source_key = ?")->execute([$key]);
            error_log("get_content.php: 在线源 {$key} 拉取失败，沿用旧缓存");
        } else {
            error_log("get_content.php: 在线源 {$key} 首次拉取失败（无缓存）");
        }
    }

    if (!$payload) return [];
    $data = json_decode($payload, true);
    if (!is_array($data)) return [];
    return parseOnlineItems($sourceType, $data);
}

/**
 * 拉取上游 API（15s 超时，失败返回 null）
 */
function fetchFromUpstream(string $url, string $type): ?string {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 15,
            'header'  => "User-Agent: Mozilla/5.0 (compatible; countdown-bot/1.0)\r\nAccept: application/json\r\n",
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false || $body === '') return null;
    $data = json_decode($body, true);
    if (!is_array($data)) return null;
    return $body;
}

/**
 * 按源类型解析为统一条目结构（含内容净化）
 */
function parseOnlineItems(string $type, array $data): array {
    $items = [];
    switch ($type) {
        case 'hitokoto':
            if (!empty($data['hitokoto'])) {
                $text = (string)$data['hitokoto'];
                $src = '';
                if (!empty($data['from_who'])) $src .= (string)$data['from_who'];
                if (!empty($data['from'])) $src .= ($src !== '' ? '《' . $data['from'] . '》' : (string)$data['from']);
                $content = $text . ($src !== '' ? ' ——' . $src : '');
                $items[] = ['content' => HtmlSanitizer::sanitize($content), 'tag' => '每日一言', 'detail' => null];
            }
            break;

        case 'news60s':
            if (!empty($data['data']['news']) && is_array($data['data']['news'])) {
                $date = (string)($data['data']['date'] ?? '');
                $count = 0;
                foreach ($data['data']['news'] as $line) {
                    $line = trim((string)$line);
                    if ($line === '') continue;
                    $items[] = [
                        'content' => HtmlSanitizer::sanitize($line),
                        'tag'     => '今日要闻' . ($date !== '' ? ' · ' . $date : ''),
                        'detail'  => null,
                    ];
                    if (++$count >= 15) break;
                }
            }
            // 60s 简报附带的"每日一句"（words）也可作为励志语补充
            if (!empty($data['data']['words']) && is_string($data['data']['words'])) {
                $items[] = ['content' => HtmlSanitizer::sanitize('“' . $data['data']['words'] . '”'), 'tag' => '今日一言', 'detail' => null];
            }
            break;

        case 'history':
            // 预留：未来接入可用免费源后实现（当前无可靠 JSON 源，模块默认停用）
            break;
    }
    return $items;
}
