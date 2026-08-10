<?php
/**
 * HTML 白名单净化器（服务端）
 *
 * 只允许 <b> <i> <u> <em> <strong> 五个纯文本标签（且不允许任何属性），
 * 其余所有标签一律剥除（script/style 等危险容器连内容一起丢弃），
 * 裸 < > & 按文本转义，合法 HTML 实体保留。
 * 手写扫描解析，不依赖 libxml 的解析怪癖，无黑名单绕过问题。
 */
class HtmlSanitizer {

    /** 允许保留的标签（白名单，全小写） */
    private static $allowedTags = ['b', 'i', 'u', 'em', 'strong'];

    /** 危险容器：标签与内容整体丢弃 */
    private static $dropTags = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math'];

    /** 输入长度上限（字符） */
    private static $maxLength = 10000;

    /** 最大嵌套深度（防御深递归） */
    private static $maxDepth = 32;

    /**
     * 净化 HTML 字符串
     * @param string|null $html 原始输入
     * @return string 净化后的安全 HTML
     */
    public static function sanitize($html) {
        if ($html === null || $html === '') {
            return '';
        }
        $html = (string)$html;
        if (mb_strlen($html) > self::$maxLength) {
            $html = mb_substr($html, 0, self::$maxLength);
        }
        return self::parse($html, 0);
    }

    /**
     * 查找与 <tag> 匹配的 </tag> 位置（支持同标签嵌套计数）
     * 只识别精确的 <tag> / </tag>（白名单分支仅对无属性标签调用）
     * @return int|false 闭合标签起始位置
     */
    private static function findClosing($s, $start, $tag) {
        $len = strlen($s);
        $depth = 1;
        $openRe = '/^<' . preg_quote($tag, '/') . '>/i';
        $closeRe = '/^<\/' . preg_quote($tag, '/') . '>/i';
        $i = $start;
        while ($i < $len) {
            if ($s[$i] !== '<') {
                $i++;
                continue;
            }
            $sub = substr($s, $i);
            if (preg_match($closeRe, $sub)) {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
                $i += strlen($closeTag = '</' . $tag . '>');
                continue;
            }
            if (preg_match($openRe, $sub)) {
                $depth++;
                $i += strlen('<' . $tag . '>');
                continue;
            }
            $i++;
        }
        return false;
    }

    /**
     * 递归解析片段
     * @param string $s 输入
     * @param int $depth 当前递归深度
     * @return string 安全 HTML
     */
    private static function parse($s, $depth) {
        if ($depth > self::$maxDepth) {
            // 超深嵌套：视为异常输入，整体丢弃（防栈溢出/输出膨胀）
            return '';
        }

        $out = '';
        $len = strlen($s);
        $i = 0;
        while ($i < $len) {
            $c = $s[$i];

            if ($c === '<') {
                // 尝试匹配任意标签 <tag 属性...> 或 </tag>
                if (preg_match('/^<(\/)?([a-zA-Z][a-zA-Z0-9]*)([^>]*)>/', substr($s, $i), $m)) {
                    $isClose = $m[1] === '/';
                    $tag = strtolower($m[2]);
                    $attrs = $m[3];
                    $tagLen = strlen($m[0]);
                    $closeTag = '</' . $tag . '>';

                    if ($isClose) {
                        // 孤立闭合标签：忽略
                        $i += $tagLen;
                        continue;
                    }

                    if (in_array($tag, self::$dropTags, true)) {
                        // 危险容器：整段丢弃（含内容），直到闭合标签或结尾
                        $end = stripos($s, '</' . $tag . '>', $i + $tagLen);
                        $i = $end === false ? $len : $end + strlen($closeTag);
                        continue;
                    }

                    if (in_array($tag, self::$allowedTags, true) && trim($attrs) === '') {
                        // 白名单标签且无属性：查找匹配的闭合标签（支持同标签嵌套），递归解析内容
                        $end = self::findClosing($s, $i + $tagLen, $tag);
                        if ($end !== false) {
                            $inner = substr($s, $i + $tagLen, $end - $i - $tagLen);
                            $out .= '<' . $tag . '>' . self::parse($inner, $depth + 1) . '</' . $tag . '>';
                            $i = $end + strlen($closeTag);
                            continue;
                        }
                        // 无闭合标签：剥掉标签本身，内容继续解析
                        $i += $tagLen;
                        continue;
                    }

                    // 其他任何标签（含带属性的白名单标签）：剥掉标签，内容继续解析
                    $i += $tagLen;
                    continue;
                }

                // 不是合法标签：转义 <
                $out .= '&lt;';
                $i++;
                continue;
            }

            if ($c === '&') {
                // 合法实体（&#123; &#x1F; &amp; 等）原样保留；非法裸 & 转义
                if (preg_match('/^&(?:#[0-9]{1,7}|#[xX][0-9a-fA-F]{1,6}|[a-zA-Z][a-zA-Z0-9]{1,31});/', substr($s, $i), $m)) {
                    $out .= $m[0];
                    $i += strlen($m[0]);
                } else {
                    $out .= '&amp;';
                    $i++;
                }
                continue;
            }

            $out .= $c;
            $i++;
        }
        return $out;
    }
}
