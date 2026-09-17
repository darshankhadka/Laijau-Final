<?php

declare(strict_types=1);

namespace App\Services\Operational;

class UrlSecurityValidator
{
    /**
     * Prohibited private, loopback, link-local, carrier-grade NAT, and reserved IPv4 CIDR blocks.
     */
    protected const BLOCKED_IPV4_CIDRS = [
        '0.0.0.0/8',          // Current network
        '10.0.0.0/8',         // RFC1918 Private Class A
        '100.64.0.0/10',      // RFC6598 Shared Address Space / Carrier-Grade NAT
        '127.0.0.0/8',        // Loopback
        '169.254.0.0/16',     // Link-Local / Cloud Instance Metadata (169.254.169.254)
        '172.16.0.0/12',      // RFC1918 Private Class B
        '192.0.0.0/24',       // IETF Protocol Assignments
        '192.0.2.0/24',       // TEST-NET-1
        '192.168.0.0/16',     // RFC1918 Private Class C
        '198.18.0.0/15',      // Network Interconnect Device Benchmark
        '198.51.100.0/24',    // TEST-NET-2
        '203.0.113.0/24',     // TEST-NET-3
        '224.0.0.0/4',        // Multicast
        '240.0.0.0/4',        // Reserved / Future use
        '255.255.255.255/32', // Broadcast
    ];

    /**
     * Prohibited IPv6 prefixes and representations.
     */
    protected const BLOCKED_IPV6_PREFIXES = [
        '::1',                // IPv6 Loopback
        '::',                 // IPv6 Unspecified
        'fe80:',              // IPv6 Link-Local
        'fc00:',              // IPv6 Unique Local Address (ULA)
        'fd00:',              // IPv6 Unique Local Address (ULA)
        '::ffff:',            // IPv4-mapped IPv6
        '64:ff9b:',           // IPv4/IPv6 Translation
    ];

    /**
     * Standard allowed ports for web traffic.
     */
    protected const ALLOWED_PORTS = [80, 443];
    protected const ALLOWED_DEV_PORTS = [80, 443, 3000, 8000, 8080];

    /**
     * Assert that a URL is safe from SSRF attacks (public HTTP/HTTPS only).
     *
     * @throws \InvalidArgumentException If URL is malformed, uses an unsafe scheme, contains credentials, or resolves to internal/private IP.
     */
    public static function assertSafeUrl(string $url, bool $allowLocal = false): void
    {
        if (empty(trim($url))) {
            throw new \InvalidArgumentException("URL cannot be empty.");
        }

        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException("Invalid or malformed URL: {$url}");
        }

        // 1. Strict scheme enforcement
        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException("Disallowed URL scheme '{$scheme}'. Only HTTP and HTTPS are permitted.");
        }

        // 2. Reject embedded credentials (user:password@host)
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException("SSRF Protection: Embedded credentials in URLs are prohibited.");
        }

        // 3. Port restrictions
        if (isset($parts['port'])) {
            $port = (int) $parts['port'];
            $allowedPorts = $allowLocal ? self::ALLOWED_DEV_PORTS : self::ALLOWED_PORTS;
            if (!in_array($port, $allowedPorts, true)) {
                throw new \InvalidArgumentException("SSRF Protection: Port {$port} is not permitted.");
            }
        }

        $host = strtolower($parts['host']);

        // 4. Strip IPv6 brackets if present
        $hostClean = trim($host, '[]');

        // 5. Block reserved and cloud metadata hostnames
        $blockedHosts = [
            'localhost',
            'localhost.localdomain',
            'ip6-localhost',
            'ip6-loopback',
            'metadata.google.internal',
            'instance-data',
            'metadata.packet.net',
            '169.254.169.254',
        ];

        if (in_array($hostClean, $blockedHosts, true)) {
            if (!$allowLocal) {
                throw new \InvalidArgumentException("Access to internal host '{$host}' is prohibited.");
            }
        }

        // 6. Resolve host to IP
        $ips = filter_var($hostClean, FILTER_VALIDATE_IP) ? [$hostClean] : self::resolveHostIps($hostClean);

        if (empty($ips)) {
            throw new \InvalidArgumentException("Could not resolve IP address for host: {$host}");
        }

        // 7. Validate each resolved IP against blocked CIDR blocks
        foreach ($ips as $ip) {
            if (self::isBlockedIp($ip, $allowLocal)) {
                throw new \InvalidArgumentException("SSRF Protection: Host '{$host}' resolves to prohibited private/internal IP: {$ip}");
            }
        }
    }

    /**
     * Check whether a URL is safe.
     */
    public static function isSafeUrl(string $url, bool $allowLocal = false): bool
    {
        try {
            self::assertSafeUrl($url, $allowLocal);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Resolves IPv4 and IPv6 addresses for a hostname.
     */
    protected static function resolveHostIps(string $host): array
    {
        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $rec) {
                if (isset($rec['ip'])) {
                    $ips[] = $rec['ip'];
                } elseif (isset($rec['ipv6'])) {
                    $ips[] = $rec['ipv6'];
                }
            }
        }

        // Fallback to gethostbyname
        if (empty($ips)) {
            $fallback = @gethostbyname($host);
            if ($fallback && $fallback !== $host) {
                $ips[] = $fallback;
            }
        }

        return array_unique($ips);
    }

    /**
     * Determines whether an IP is within a blocked CIDR or private range.
     */
    protected static function isBlockedIp(string $ip, bool $allowLocal = false): bool
    {
        $cleanIp = trim($ip, '[]');

        if ($allowLocal && in_array($cleanIp, ['127.0.0.1', '::1'], true)) {
            return false;
        }

        // IPv4 check
        if (filter_var($cleanIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($cleanIp);
            if ($ipLong === false) {
                return true;
            }

            foreach (self::BLOCKED_IPV4_CIDRS as $cidr) {
                [$subnet, $maskBits] = explode('/', $cidr);
                $subnetLong = ip2long($subnet);
                $mask = ~((1 << (32 - (int)$maskBits)) - 1);

                if (($ipLong & $mask) === ($subnetLong & $mask)) {
                    return true;
                }
            }
            return false;
        }

        // IPv6 check
        if (filter_var($cleanIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $normalized = strtolower($cleanIp);
            foreach (self::BLOCKED_IPV6_PREFIXES as $prefix) {
                if (str_starts_with($normalized, $prefix)) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }
}
