<?php
/**
 * Class to validate and to work with IPv6 addresses - Modernisiert für PHP 8.x
 *
 * @package Requests
 * @subpackage Utilities
 */

declare(strict_types=1);

class Requests_IPv6
{
    /**
     * Uncompresses an IPv6 address
     *
     * RFC 4291 allows you to compress consecutive zero pieces in an address to
     * '::'. This method expects a valid IPv6 address and expands the '::' to
     * the required number of zero pieces.
     *
     * @param string $ip An IPv6 address
     * @return string The uncompressed IPv6 address
     */
    public static function uncompress(string $ip): string
    {
        $c1 = -1;
        $c2 = -1;
        if (substr_count($ip, '::') === 1) {
            [$ip1, $ip2] = explode('::', $ip);
            
            $c1 = ($ip1 === '') ? -1 : substr_count($ip1, ':');
            $c2 = ($ip2 === '') ? -1 : substr_count($ip2, ':');

            if (strpos($ip2, '.') !== false) {
                $c2++;
            }
            
            // ::
            if ($c1 === -1 && $c2 === -1) {
                $ip = '0:0:0:0:0:0:0:0';
            }
            // ::xxx
            elseif ($c1 === -1) {
                $fill = str_repeat('0:', 7 - $c2);
                $ip = str_replace('::', $fill, $ip);
            }
            // xxx::
            elseif ($c2 === -1) {
                $fill = str_repeat(':0', 7 - $c1);
                $ip = str_replace('::', $fill, $ip);
            }
            // xxx::xxx
            else {
                $fill = ':' . str_repeat('0:', 6 - $c2 - $c1);
                $ip = str_replace('::', $fill, $ip);
            }
        }
        return $ip;
    }

    /**
     * Compresses an IPv6 address
     *
     * RFC 4291 allows you to compress consecutive zero pieces in an address to
     * '::'. This method expects a valid IPv6 address and compresses consecutive
     * zero pieces to '::'.
     *
     * @see uncompress()
     * @param string $ip An IPv6 address
     * @return string The compressed IPv6 address
     */
    public static function compress(string $ip): string
    {
        $ip = self::uncompress($ip);
        $ip_parts = self::split_v6_v4($ip);

        // Replace all leading zeros
        $ip_parts[0] = preg_replace('/(^|:)0+([0-9])/', '\1\2', $ip_parts[0]);

        // Find bunches of zeros
        if (preg_match_all('/(?:^|:)(?:0(?::|$))+/', $ip_parts[0], $matches, PREG_OFFSET_CAPTURE)) {
            $max = 0;
            $pos = null;
            foreach ($matches[0] as $match) {
                if (strlen($match[0]) > $max) {
                    $max = strlen($match[0]);
                    $pos = $match[1];
                }
            }

            if ($pos !== null) {
                $ip_parts[0] = substr_replace($ip_parts[0], '::', $pos, $max);
            }
        }

        if ($ip_parts[1] !== '') {
            return implode(':', $ip_parts);
        } else {
            return $ip_parts[0];
        }
    }

    /**
     * Splits an IPv6 address into the IPv6 and IPv4 representation parts
     *
     * @param string $ip An IPv6 address
     * @return array [0] contains the IPv6 represented part, and [1] the IPv4 represented part
     */
    private static function split_v6_v4(string $ip): array
    {
        if (strpos($ip, '.') !== false) {
            $pos = strrpos($ip, ':');
            if ($pos !== false) {
                $ipv6_part = substr($ip, 0, $pos);
                $ipv4_part = substr($ip, $pos + 1);
                return [$ipv6_part, $ipv4_part];
            }
        }
        return [$ip, ''];
    }

    /**
     * Checks an IPv6 address
     *
     * Checks if the given IP is a valid IPv6 address
     *
     * @param string $ip An IPv6 address
     * @return bool true if $ip is a valid IPv6 address
     */
    public static function check_ipv6(string $ip): bool
    {
        $ip = self::uncompress($ip);
        [$ipv6, $ipv4] = self::split_v6_v4($ip);
        $ipv6 = explode(':', $ipv6);
        $ipv4 = explode('.', $ipv4);

        if ((count($ipv6) === 8 && count($ipv4) === 1) || (count($ipv6) === 6 && count($ipv4) === 4)) {
            foreach ($ipv6 as $ipv6_part) {
                // The section can't be empty
                if ($ipv6_part === '') {
                    return false;
                }

                // Nor can it be over four characters
                if (strlen($ipv6_part) > 4) {
                    return false;
                }

                // Remove leading zeros
                $ipv6_part = ltrim($ipv6_part, '0');
                if ($ipv6_part === '') {
                    $ipv6_part = '0';
                }

                // Check the value is valid
                $value = hexdec($ipv6_part);
                if (dechex($value) !== strtolower($ipv6_part) || $value < 0 || $value > 0xFFFF) {
                    return false;
                }
            }
            if (count($ipv4) === 4) {
                foreach ($ipv4 as $ipv4_part) {
                    $value = (int) $ipv4_part;
                    if ((string) $value !== $ipv4_part || $value < 0 || $value > 0xFF) {
                        return false;
                    }
                }
            }
            return true;
        } else {
            return false;
        }
    }
}