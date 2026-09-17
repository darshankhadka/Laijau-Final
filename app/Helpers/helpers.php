<?php

declare(strict_types=1);

use App\Helpers\NepaliNumberHelper;

if (!function_exists('nepali_number')) {
    /**
     * Format a number according to the Nepali numbering system (e.g. 100000 => "1,00,000").
     */
    function nepali_number($number, ?int $decimals = null): string
    {
        return NepaliNumberHelper::format($number, $decimals);
    }
}

if (!function_exists('nepali_currency')) {
    /**
     * Format an amount in Nepali currency (e.g. 100000 => "Rs. 1,00,000").
     */
    function nepali_currency($amount, string $prefix = 'Rs. ', ?int $decimals = null): string
    {
        return NepaliNumberHelper::formatCurrency($amount, $prefix, $decimals);
    }
}

if (!function_exists('nepali_lakh_crore')) {
    /**
     * Format an amount in Nepali Lakh/Crore notation (e.g. 36472322.78 => "Rs. 3.65 crore", 500000 => "Rs. 5 lakh").
     */
    function nepali_lakh_crore($amount, string $prefix = 'Rs. ', int $decimals = 2): string
    {
        return NepaliNumberHelper::formatLakhCrore($amount, $prefix, $decimals);
    }
}

if (!function_exists('clean_html')) {
    /**
     * Sanitize user-controlled or rich-text HTML against XSS by removing prohibited tags,
     * dangerous inline attributes (on* handlers), and psNpdo-protocols (javascript:, data:).
     */
    function clean_html(?string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // 1. Completely remove script, style, and iframe elements including their inner content
        $cleaned = (string) preg_replace('/<(script|style|iframe)[^>]*?>.*?<\/\1>/si', '', $html);

        // 2. Allow only safe typographical and structural tags
        $allowed = '<p><br><strong><b><i><em><ul><ol><li><span><div><h1><h2><h3><h4><h5><h6><blockquote><code><pre><hr><table><thead><tbody><tr><th><td>';
        $cleaned = strip_tags($cleaned, $allowed);

        // 3. Strip dangerous event handler attributes (e.g. onload, onerror, onclick, onmouseover)
        $cleaned = (string) preg_replace('/\s+on[a-zA-Z0-9_-]+\s*=\s*(?:([\'"])(?:(?!\1).)*\1|[^\s>]+)/si', '', $cleaned);

        // 4. Strip dangerous psNpdo-protocols in href/src/style attributes
        $cleaned = (string) preg_replace('/(?:\bhref|\bsrc|\bstyle)\s*=\s*(?:([\'"])\s*(?:javascript|vbscript|data):(?:(?!\1).)*\1|[^\s>]+)/si', '', $cleaned);

        return $cleaned;
    }
}
