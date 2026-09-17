<?php

namespace Tests\Unit;

use Tests\TestCase;

class XssProtectionTest extends TestCase
{
    /**
     * Test clean_html strips script tags and dangerous event handlers.
     */
    public function test_clean_html_strips_scripts_and_events(): void
    {
        $payload1 = '<p>Normal text <script>alert("XSS")</script></p>';
        $cleaned1 = clean_html($payload1);
        $this->assertStringNotContainsString('<script>', $cleaned1);
        $this->assertStringNotContainsString('alert("XSS")', $cleaned1);
        $this->assertStringContainsString('<p>Normal text </p>', $cleaned1);

        $payload2 = '<span onmouseover="alert(\'XSS\')" onclick="evil()">Hover me</span>';
        $cleaned2 = clean_html($payload2);
        $this->assertStringNotContainsString('onmouseover', $cleaned2);
        $this->assertStringNotContainsString('onclick', $cleaned2);
        $this->assertStringContainsString('<span>Hover me</span>', $cleaned2);

        $payload3 = '<a href="javascript:alert(1)">Click me</a>';
        $cleaned3 = clean_html($payload3);
        $this->assertStringNotContainsString('javascript:', $cleaned3);
    }

    /**
     * Test clean_html preserves safe formatting HTML.
     */
    public function test_clean_html_preserves_safe_html(): void
    {
        $safeHtml = '<p><strong>Luxury Pashmina</strong></p><ul><li>100% Cashmere</li><li>Hand-spun</li></ul>';
        $cleaned = clean_html($safeHtml);
        $this->assertEquals($safeHtml, $cleaned);
    }
}
