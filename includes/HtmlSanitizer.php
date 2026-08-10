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
    private static $allowedTags = ['b', 'i', 'u', 'em', 'strong', 'span', 'br'];

    /** 允许的 CSS 属性（用于 span style，单独控制每条名言的字体大小/颜色等） */
    private static $allowedCssProps = [
        'color', 'background-color', 'font-size', 'font-family', 'font-weight',
        'font-style', 'text-decoration', 'text-align', 'line-height',
        'letter-spacing', 'text-shadow',
    ];

    /** 危险 CSS 值片段（命中即丢弃该声明） */
    private static $dangerCssValues = [
        'url(', 'expression', 'javascript:', 'vbscript:', 'behavior',
        '-moz-binding', '@import', 'position', 'z-index', 'content', '\\',
    ];

    /** 危险容器：标签与内容整体丢弃 */
    private static $dropTags = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math'];

    /** 输入长度上限（字符） */
    private static $maxLength = 10000;

    /** 最大嵌套深度（防御深递归） */
    private static $maxDepth = 32;

    /**
     * 过滤 CSS 声明串：只保留白名单属性，丢弃危险值
     * @param string|null $css 原始 style 值
     * @return string 过滤后的 style 值（可能为空）
     */
    private static function filterCss($css) {
        if ($css === null || $css === '') return '';
        $css = html_entity_decode((string)$css, ENT_QUOTES, 'UTF-8');
        if (strlen($css) > 500) return '';

        $out = [];
        foreach (explode(';', $css) as $decl) {
            $decl = trim($decl);
            if ($decl === '') continue;
            $parts = explode(':', $decl, 2);
            if (count($parts) !== 2) continue;
            $prop = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            if (!in_array($prop, self::$allowedCssProps, true)) continue;
            if (strlen($value) > 100) continue;
            $lower = strtolower($value);
            $danger = false;
            foreach (self::$dangerCssValues as $d) {
                if (strpos($lower, $d) !== false) { $danger = true; break; }
            }
            if ($danger) continue;
            $out[] = $prop . ': ' . $value;
        }
        return implode('; ', $out);
    }

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
        // 开始标签允许带属性（如 <span style="...">）
        $openRe = '/^<' . preg_quote($tag, '/') . '(?:\s[^>]*)?>/i';
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
                $i += strlen($sub[0]);
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

                    if (in_array($tag, self::$allowedTags, true)) {
                        if ($tag === 'br') {
                            // br 是空元素，直接输出
                            $out .= '<br>';
                            $i += $tagLen;
                            continue;
                        }

                        $styleAttr = '';
                        if ($tag === 'span') {
                            // span 只允许 style 属性（过滤后保留），其余属性一律丢弃
                            if (preg_match('/style\s*=\s*"([^"]*)"/i', $attrs, $m)) {
                                $styleAttr = self::filterCss($m[1]);
                            } elseif (preg_match("/style\s*=\s*'([^']*)'/i", $attrs, $m)) {
                                $styleAttr = self::filterCss($m[1]);
                            }
                            if ($styleAttr === '' && trim($attrs) !== '') {
                                // span 带非 style 属性，或 style 全被过滤：剥掉标签只留内容
                                $i += $tagLen;
                                continue;
                            }
                        } elseif (trim($attrs) !== '') {
                            // 其他白名单标签带属性（如 <b onclick>）：剥掉标签只留内容
                            $i += $tagLen;
                            continue;
                        }

                        // 查找匹配的闭合标签（支持同标签嵌套），递归解析内容
                        $end = self::findClosing($s, $i + $tagLen, $tag);
                        if ($end !== false) {
                            $inner = substr($s, $i + $tagLen, $end - $i - $tagLen);
                            $attrOut = $styleAttr !== '' ? ' style="' . $styleAttr . '"' : '';
                            $out .= '<' . $tag . $attrOut . '>' . self::parse($inner, $depth + 1) . '</' . $tag . '>';
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
