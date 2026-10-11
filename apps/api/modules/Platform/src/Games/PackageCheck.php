<?php

namespace Modules\Platform\Games;

/**
 * What a game page must be before it is published (docs/architecture/03,
 * section 11, "games"): one self-contained HTML document of at most
 * MAX_BYTES that plays through the bridge (LMS.onStart, LMS.answer,
 * LMS.finish) and reaches nothing outside itself: no network, no other
 * page, no storage, no code built from strings. The page also runs in a
 * sandboxed frame under a policy that blocks all of this (GamePage); the
 * check says so before a person plays it, in words the team can send back
 * to the designer.
 */
final class PackageCheck
{
    public const MAX_BYTES = 300_000;

    /** rule => list of patterns; any match is an issue. */
    private const FORBIDDEN = [
        'network' => [
            '/\bfetch\s*\(/', '/\bXMLHttpRequest\b/', '/\bWebSocket\b/', '/\bEventSource\b/', '/\bsendBeacon\b/',
            '/\bimportScripts\b/', '/\bimport\s*\(/', '/\bnew\s+(Shared)?Worker\b/', '/\bserviceWorker\b/', '/\bRTCPeerConnection\b/',
        ],
        'outside' => [
            '/<script\b[^>]*\bsrc\s*=/i', '/<link\b/i', '/@import\b/i', '/url\(\s*[\'"]?\s*(https?:)?\/\//i',
            '/\b(src|href|action|poster|srcset)\s*=\s*[\'"]?\s*(https?:)?\/\//i',
        ],
        'elements' => ['/<(iframe|frame|object|embed|base|form|portal)\b/i', '/<meta\b[^>]*http-equiv/i'],
        'storage' => ['/\b(localStorage|sessionStorage|indexedDB)\b/', '/document\s*\.\s*cookie/', '/\bcaches\s*\./'],
        'escape' => [
            '/\bwindow\s*\.\s*(parent|top|opener|open)\b/', '/\b(parent|top)\s*\.\s*(postMessage|location|document|window)\b/',
            '/\blocation\s*\.\s*(href\s*=|assign|replace)/', '/\bpostMessage\s*\(/', '/\bdocument\s*\.\s*domain\b/',
        ],
        'eval' => ['/\beval\s*\(/', '/\bnew\s+Function\s*\(/', '/\bset(Timeout|Interval)\s*\(\s*[\'"`]/'],
    ];

    /** The only addresses a page may name: the XML namespaces SVG needs. */
    private const NAMESPACES = ['http://www.w3.org/2000/svg', 'http://www.w3.org/1999/xlink', 'http://www.w3.org/1999/xhtml'];

    /** @return list<array{rule: string, detail: string}> */
    public static function issues(string $html): array
    {
        $issues = [];
        $bytes = strlen($html);
        if ($bytes > self::MAX_BYTES) {
            $issues[] = ['rule' => 'size', 'detail' => number_format($bytes).' > '.number_format(self::MAX_BYTES)];
        }
        if (! preg_match('/<html\b/i', $html) || ! preg_match('/<\/html>\s*$/i', $html)) {
            $issues[] = ['rule' => 'document', 'detail' => '<html> … </html>'];
        }
        foreach (['LMS.onStart', 'LMS.answer', 'LMS.finish'] as $call) {
            if (! str_contains($html, $call.'(')) {
                $issues[] = ['rule' => 'bridge', 'detail' => $call];
            }
        }

        $code = str_replace(self::NAMESPACES, '', $html);
        foreach (self::FORBIDDEN as $rule => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $code, $match, PREG_OFFSET_CAPTURE)) {
                    $line = substr_count(substr($code, 0, (int) $match[0][1]), "\n") + 1;
                    $issues[] = ['rule' => $rule, 'detail' => trim(mb_substr($match[0][0], 0, 60)).' (line '.$line.')'];
                }
            }
        }
        if (preg_match('/\bon[a-z]+\s*=\s*["\']/i', preg_replace('/<script\b.*?<\/script>/is', '', $html) ?? '', $match)) {
            $issues[] = ['rule' => 'handlers', 'detail' => trim($match[0])];
        }

        return $issues;
    }

    public static function passes(string $html): bool
    {
        return self::issues($html) === [];
    }
}
