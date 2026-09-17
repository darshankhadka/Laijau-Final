<?php

namespace Tests\Feature;

use App\Services\Operational\UrlSecurityValidator;
use InvalidArgumentException;
use Tests\TestCase;

class SsrfProtectionTest extends TestCase
{
    /**
     * Test loopback and localhost destinations are rejected.
     */
    public function test_rejects_loopback_and_localhost(): void
    {
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('http://localhost/api/revalidate'));
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('http://127.0.0.1/api/revalidate'));
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('http://127.0.0.2:80/api/revalidate'));
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('http://[::1]/api/revalidate'));
    }

    /**
     * Test cloud instance metadata service addresses are rejected.
     */
    public function test_rejects_cloud_metadata_service(): void
    {
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('http://169.254.169.254/latest/meta-data/'));
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('http://metadata.google.internal/computeMetadata/v1/'));
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('http://instance-data/'));
    }

    /**
     * Test RFC1918 private IPv4 ranges are rejected.
     */
    public function test_rejects_rfc1918_private_ips(): void
    {
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('http://10.0.0.1/'));
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('http://10.255.255.254/'));
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('http://172.16.0.1/'));
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('http://172.31.255.254/'));
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('http://192.168.1.1/'));
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('http://192.168.100.50/'));
    }

    /**
     * Test unsupported and dangerous URI schemes are rejected.
     */
    public function test_rejects_unsupported_schemes(): void
    {
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('file:///etc/passwd'));
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('gopher://127.0.0.1:6379/_flushall'));
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('dict://127.0.0.1:11211/stat'));
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('ftp://ftp.example.com/'));
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('php://filter/read=convert.base64-encode/resource=index.php'));
    }

    /**
     * Test embedded credentials in URL are rejected.
     */
    public function test_rejects_embedded_credentials(): void
    {
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('https://admin:password@example.com/revalidate'));
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('https://user@example.com/revalidate'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Embedded credentials in URLs are prohibited');
        UrlSecurityValidator::assertSafeUrl('https://admin:password@example.com/revalidate');
    }

    /**
     * Test non-standard dangerous ports are rejected in production mode.
     */
    public function test_rejects_non_standard_ports(): void
    {
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('http://example.com:22/'));
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('http://example.com:3306/'));
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('http://example.com:6379/'));
        $this->assertFalse(UrlSecurityValidator::isSafeUrl('http://example.com:25/'));
    }

    /**
     * Test valid public URLs are accepted.
     */
    public function test_accepts_valid_public_urls(): void
    {
        $this->assertTrue(UrlSecurityValidator::isSafeUrl('https://laijau.com/api/revalidate'));
        $this->assertTrue(UrlSecurityValidator::isSafeUrl('https://www.laijau.com/api/revalidate'));
    }
}
