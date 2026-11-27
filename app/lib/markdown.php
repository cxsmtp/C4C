<?php
declare(strict_types=1);

/**
 * A small, safe Markdown renderer. Everything is HTML-escaped first, so raw HTML in
 * the source is shown as text. Supports ## headings, paragraphs, - and 1. lists,
 * > quotes, ``` code blocks, **bold**, *italic*, `code` and [links](https://...).
 */
function md(?string $src): string
{
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", (string)$src));
    $out = [];
    $para = [];
    $list = null;
    $code = null;
    $flushPara = function () use (&$para, &$out) {
        if ($para) $out[] = '<p>' . md_inline(implode(' ', $para)) . '</p>';
        $para = [];
    };
    $closeList = function () use (&$list, &$out) {
        if ($list) $out[] = "</$list>";
        $list = null;
    };
    foreach ($lines as $line) {
        if ($code !== null) {
            if (preg_match('/^```/', $line)) {
                $out[] = '<pre><code>' . e(implode("\n", $code)) . '</code></pre>';
                $code = null;
            } else {
                $code[] = $line;
            }
            continue;
        }
        if (preg_match('/^```/', $line)) { $flushPara(); $closeList(); $code = []; continue; }
        if (trim($line) === '') { $flushPara(); $closeList(); continue; }
        if (preg_match('/^(#{2,4})\s+(.*)$/', $line, $m)) {
            $flushPara(); $closeList();
            $n = strlen($m[1]);
            $out[] = "<h$n>" . md_inline($m[2]) . "</h$n>";
            continue;
        }
        if (preg_match('/^\s*([-*]|\d+[.)])\s+(.*)$/', $line, $m)) {
            $flushPara();
            $tag = ctype_digit($m[1][0]) ? 'ol' : 'ul';
            if ($list !== $tag) { $closeList(); $out[] = "<$tag>"; $list = $tag; }
            $out[] = '<li>' . md_inline($m[2]) . '</li>';
            continue;
        }
        if (preg_match('/^>\s?(.*)$/', $line, $m)) {
            $flushPara(); $closeList();
            $out[] = '<blockquote><p>' . md_inline($m[1]) . '</p></blockquote>';
            continue;
        }
        $closeList();
        $para[] = trim($line);
    }
    if ($code !== null) $out[] = '<pre><code>' . e(implode("\n", $code)) . '</code></pre>';
    $flushPara();
    $closeList();
    return implode("\n", $out);
}

function md_inline(string $s): string
{
    $s = e($s);
    $codes = [];
    $s = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$codes) {
        $codes[] = '<code>' . $m[1] . '</code>';
        return "\x01" . (count($codes) - 1) . "\x01";
    }, $s);
    $s = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
        $url = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!preg_match('#^(https?://|mailto:|/(?!/)|\#)#i', $url)) return $m[1];
        $ext = preg_match('#^https?://#i', $url) && !str_contains($url, '://call4counsel.com');
        return '<a href="' . e($url) . '"' . ($ext ? ' rel="noopener noreferrer"' : '') . '>' . $m[1] . '</a>';
    }, $s);
    $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
    $s = preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/', '<em>$1</em>', $s);
    return preg_replace_callback("/\x01(\d+)\x01/", fn($m) => $codes[(int)$m[1]], $s);
}

/** Splits a textarea into a list, one entry per line. */
function lines_of(?string $s): array
{
    return array_values(array_filter(array_map('trim', explode("\n", (string)$s)), fn($l) => $l !== ''));
}
