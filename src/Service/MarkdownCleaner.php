<?php

namespace App\Service;

class MarkdownCleaner
{
    private const array PATTERNS = [
        '/!\[([^\]]*)\]\([^)]*\)/' => '',
        '/!\[([^\]]*)\]\[[^\]]*\]/' => '',
        '/\[([^\]]+)\]\([^)]*\)/' => '$1',
        '/\[([^\]]+)\]\[[^\]]*\]/' => '$1',
        '/^\s*\[[^\]]+\]:\s*.*$/m' => '',
        '/^#{1,6}\s+(.+)$/m' => '$1',
        '/`([^`]+)`/' => '$1',
        '/```[\s\S]*?```/' => '',
        '/~~~[\s\S]*?~~~/' => '',
        '/^(?:    |\t).*$/m' => '',
        '/\*\*\*([^*]+)\*\*\*/' => '$1',
        '/\*\*([^*]+)\*\*/' => '$1',
        '/\*([^*]+)\*/' => '$1',
        '/___([^_]+)___/' => '$1',
        '/__([^_]+)__/' => '$1',
        '/_([^_]+)_/' => '$1',
        '/^>\s*(.*)$/m' => '$1',
        '/^\s*[-*+]\s+(.+)$/m' => '$1',
        '/^\s*\d+\.\s+(.+)$/m' => '$1',
        '/\|/' => ' ',
        '/^[-\s|:]+$/m' => '',
        '/^[-*_]{3,}$/m' => '',
        '/<[^>]*>/' => '',
        '/<!--[\s\S]*?-->/' => '',
        '/[ \t]+/' => ' ',
        '/\n\s*\n\s*\n/' => "\n\n",
    ];

    public static function clean(string $content): string
    {
        if (empty(mb_trim($content))) {
            return '';
        }

        foreach (self::PATTERNS as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        $content = preg_replace('/^[ \t]+|[ \t]+$/m', '', $content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);

        return mb_trim($content);
    }
}
