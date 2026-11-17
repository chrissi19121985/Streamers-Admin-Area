<?php
/**
 * IRI parser/serialiser/normaliser - Modernisiert für PHP 8.x
 *
 * @package Requests
 * @subpackage Utilities
 */

declare(strict_types=1);

#[AllowDynamicProperties]
class Requests_IRI
{
    /**
     * Scheme
     *
     * @var string|null
     */
    private ?string $scheme = null;

    /**
     * User Information
     *
     * @var string|null
     */
    private ?string $iuserinfo = null;

    /**
     * ihost
     *
     * @var string|null
     */
    private ?string $ihost = null;

    /**
     * Port
     *
     * @var int|string|null
     */
    private $port = null;

    /**
     * ipath
     *
     * @var string
     */
    private string $ipath = '';

    /**
     * iquery
     *
     * @var string|null
     */
    private ?string $iquery = null;

    /**
     * ifragment
     *
     * @var string|null
     */
    private ?string $ifragment = null;

    /**
     * Normalization database
     */
    private array $normalization = [
        'acap' => [
            'port' => 674
        ],
        'dict' => [
            'port' => 2628
        ],
        'file' => [
            'ihost' => 'localhost'
        ],
        'http' => [
            'port' => 80,
            'ipath' => '/'
        ],
        'https' => [
            'port' => 443,
            'ipath' => '/'
        ],
    ];

    /**
     * Return the entire IRI when you try and read the object as a string
     */
    public function __toString(): string
    {
        return $this->get_iri() ?: '';
    }

    /**
     * Overload __set() to provide access via properties
     */
    public function __set(string $name, $value): void
    {
        if (method_exists($this, 'set_' . $name)) {
            call_user_func([$this, 'set_' . $name], $value);
        } elseif (
               $name === 'iauthority'
            || $name === 'iuserinfo'
            || $name === 'ihost'
            || $name === 'ipath'
            || $name === 'iquery'
            || $name === 'ifragment'
        ) {
            call_user_func([$this, 'set_' . substr($name, 1)], $value);
        }
    }

    /**
     * Overload __get() to provide access via properties
     */
    public function __get(string $name)
    {
        $props = get_object_vars($this);

        if (
            $name === 'iri' ||
            $name === 'uri' ||
            $name === 'iauthority' ||
            $name === 'authority'
        ) {
            $return = $this->{"get_$name"}();
        } elseif (array_key_exists($name, $props)) {
            $return = $this->$name;
        } elseif (($prop = 'i' . $name) && array_key_exists($prop, $props)) {
            $return = $this->$prop;
        } elseif (($prop = substr($name, 1)) && array_key_exists($prop, $props)) {
            $return = $this->$prop;
        } else {
            trigger_error('Undefined property: ' . get_class($this) . '::' . $name, E_USER_NOTICE);
            $return = null;
        }

        if ($return === null && isset($this->normalization[$this->scheme ?? ''][$name])) {
            return $this->normalization[$this->scheme][$name];
        } else {
            return $return;
        }
    }

    /**
     * Overload __isset() to provide access via properties
     */
    public function __isset(string $name): bool
    {
        return method_exists($this, 'get_' . $name) || isset($this->$name);
    }

    /**
     * Overload __unset() to provide access via properties
     */
    public function __unset(string $name): void
    {
        if (method_exists($this, 'set_' . $name)) {
            call_user_func([$this, 'set_' . $name], '');
        }
    }

    /**
     * Create a new IRI object, from a specified string
     */
    public function __construct(?string $iri = null)
    {
        $this->set_iri($iri);
    }

    /**
     * Create a new IRI object by resolving a relative IRI
     */
    public static function absolutize($base, $relative)
    {
        if (!($relative instanceof self)) {
            $relative = new self($relative);
        }
        if (!$relative->is_valid()) {
            return false;
        } elseif ($relative->scheme !== null) {
            return clone $relative;
        } else {
            if (!($base instanceof self)) {
                $base = new self($base);
            }
            if ($base->scheme !== null && $base->is_valid()) {
                if ($relative->get_iri() !== '') {
                    if ($relative->iuserinfo !== null || $relative->ihost !== null || $relative->port !== null) {
                        $target = clone $relative;
                        $target->scheme = $base->scheme;
                    } else {
                        $target = new self();
                        $target->scheme = $base->scheme;
                        $target->iuserinfo = $base->iuserinfo;
                        $target->ihost = $base->ihost;
                        $target->port = $base->port;
                        if ($relative->ipath !== '') {
                            if ($relative->ipath[0] === '/') {
                                $target->ipath = $relative->ipath;
                            } elseif (($base->iuserinfo !== null || $base->ihost !== null || $base->port !== null) && $base->ipath === '') {
                                $target->ipath = '/' . $relative->ipath;
                            } elseif (($last_segment = strrpos($base->ipath, '/')) !== false) {
                                $target->ipath = substr($base->ipath, 0, $last_segment + 1) . $relative->ipath;
                            } else {
                                $target->ipath = $relative->ipath;
                            }
                            $target->ipath = $target->remove_dot_segments($target->ipath);
                            $target->iquery = $relative->iquery;
                        } else {
                            $target->ipath = $base->ipath;
                            if ($relative->iquery !== null) {
                                $target->iquery = $relative->iquery;
                            } elseif ($base->iquery !== null) {
                                $target->iquery = $base->iquery;
                            }
                        }
                        $target->ifragment = $relative->ifragment;
                    }
                } else {
                    $target = clone $base;
                    $target->ifragment = null;
                }
                $target->scheme_normalization();
                return $target;
            } else {
                return false;
            }
        }
    }

    /**
     * Parse an IRI into scheme/authority/path/query/fragment segments
     */
    private function parse_iri(string $iri): array
    {
        $iri = trim($iri, "\x20\x09\x0A\x0C\x0D");
        if (preg_match('/^((?P<scheme>[^:\/?#]+):)?(\/\/(?P<authority>[^\/?#]*))?(?P<path>[^?#]*)(\?(?P<query>[^#]*))?(#(?P<fragment>.*))?$/', $iri, $match)) {
            if (($match[1] ?? '') === '') {
                $match['scheme'] = null;
            }
            if (!isset($match[3]) || $match[3] === '') {
                $match['authority'] = null;
            }
            if (!isset($match[5])) {
                $match['path'] = '';
            }
            if (!isset($match[6]) || $match[6] === '') {
                $match['query'] = null;
            }
            if (!isset($match[8]) || $match[8] === '') {
                $match['fragment'] = null;
            }
            return $match;
        } else {
            trigger_error('This should never happen', E_USER_ERROR);
            exit;
        }
    }

    /**
     * Remove dot segments from a path
     */
    private function remove_dot_segments(string $input): string
    {
        $output = '';
        while (strpos($input, './') !== false || strpos($input, '/.') !== false || $input === '.' || $input === '..') {
            if (strpos($input, '../') === 0) {
                $input = substr($input, 3);
            } elseif (strpos($input, './') === 0) {
                $input = substr($input, 2);
            } elseif (strpos($input, '/./') === 0) {
                $input = substr($input, 2);
            } elseif ($input === '/.') {
                $input = '/';
            } elseif (strpos($input, '/../') === 0) {
                $input = substr($input, 3);
                $rpos = strrpos($output, '/');
                if ($rpos !== false) {
                    $output = substr_replace($output, '', $rpos);
                }
            } elseif ($input === '/..') {
                $input = '/';
                $rpos = strrpos($output, '/');
                if ($rpos !== false) {
                    $output = substr_replace($output, '', $rpos);
                }
            } elseif ($input === '.' || $input === '..') {
                $input = '';
            } elseif (($pos = strpos($input, '/', 1)) !== false) {
                $output .= substr($input, 0, $pos);
                $input = substr_replace($input, '', 0, $pos);
            } else {
                $output .= $input;
                $input = '';
            }
        }
        return $output . $input;
    }

    /**
     * Replace invalid character with percent encoding
     */
    private function replace_invalid_with_pct_encoding(string $string, string $extra_chars, bool $iprivate = false): string
    {
        $string = preg_replace_callback('/(?:%[A-Fa-f0-9]{2})+/', [$this, 'remove_iunreserved_percent_encoded'], $string);
        $string = preg_replace('/%(?![A-Fa-f0-9]{2})/', '%25', $string);
        $extra_chars .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~%';

        $position = 0;
        $strlen = strlen($string);
        while (($position += strspn($string, $extra_chars, $position)) < $strlen) {
            $value = ord($string[$position]);
            $start = $position;
            $valid = true;

            if (($value & 0xE0) === 0xC0) {
                $character = ($value & 0x1F) << 6;
                $length = 2;
                $remaining = 1;
            } elseif (($value & 0xF0) === 0xE0) {
                $character = ($value & 0x0F) << 12;
                $length = 3;
                $remaining = 2;
            } elseif (($value & 0xF8) === 0xF0) {
                $character = ($value & 0x07) << 18;
                $length = 4;
                $remaining = 3;
            } else {
                $valid = false;
                $length = 1;
                $remaining = 0;
            }

            if ($remaining) {
                if ($position + $length <= $strlen) {
                    for ($position++; $remaining; $position++) {
                        $value = ord($string[$position]);
                        if (($value & 0xC0) === 0x80) {
                            $character |= ($value & 0x3F) << (--$remaining * 6);
                        } else {
                            $valid = false;
                            $position--;
                            break;
                        }
                    }
                } else {
                    $position = $strlen - 1;
                    $valid = false;
                }
            }

            if (
                !$valid
                || ($length > 1 && $character <= 0x7F)
                || ($length > 2 && $character <= 0x7FF)
                || ($length > 3 && $character <= 0xFFFF)
                || ($character & 0xFFFE) === 0xFFFE
                || ($character >= 0xFDD0 && $character <= 0xFDEF)
                || (
                       ($character > 0xD7FF && $character < 0xF900)
                    || $character < 0xA0
                    || $character > 0xEFFFD
                )
                && (
                       !$iprivate
                    || $character < 0xE000
                    || $character > 0x10FFFD
                )
            ) {
                if ($valid) {
                    $position--;
                }

                for ($j = $start; $j <= $position; $j++) {
                    $string = substr_replace($string, sprintf('%%%02X', ord($string[$j])), $j, 1);
                    $j += 2;
                    $position += 2;
                    $strlen += 2;
                }
            }
        }

        return $string;
    }

    /**
     * Callback function for preg_replace_callback.
     */
    private function remove_iunreserved_percent_encoded(array $match): string
    {
        $bytes = explode('%', $match[0]);
        $string = '';
        $remaining = 0;
        $character = 0;
        $length = 0;
        $start = 0;

        for ($i = 1, $len = count($bytes); $i < $len; $i++) {
            $value = hexdec($bytes[$i]);

            if (!$remaining) {
                $start = $i;
                $valid = true;

                if ($value <= 0x7F) {
                    $character = $value;
                    $length = 1;
                } elseif (($value & 0xE0) === 0xC0) {
                    $character = ($value & 0x1F) << 6;
                    $length = 2;
                    $remaining = 1;
                } elseif (($value & 0xF0) === 0xE0) {
                    $character = ($value & 0x0F) << 12;
                    $length = 3;
                    $remaining = 2;
                } elseif (($value & 0xF8) === 0xF0) {
                    $character = ($value & 0x07) << 18;
                    $length = 4;
                    $remaining = 3;
                } else {
                    $valid = false;
                    $remaining = 0;
                }
            } else {
                if (($value & 0xC0) === 0x80) {
                    $remaining--;
                    $character |= ($value & 0x3F) << ($remaining * 6);
                } else {
                    $valid = false;
                    $remaining = 0;
                    $i--;
                }
            }

            if (!$remaining) {
                if (
                    !$valid
                    || ($length > 1 && $character <= 0x7F)
                    || ($length > 2 && $character <= 0x7FF)
                    || ($length > 3 && $character <= 0xFFFF)
                    || $character < 0x2D
                    || $character > 0xEFFFD
                    || ($character & 0xFFFE) === 0xFFFE
                    || ($character >= 0xFDD0 && $character <= 0xFDEF)
                    || $character === 0x2F
                    || ($character > 0x39 && $character < 0x41)
                    || ($character > 0x5A && $character < 0x61)
                    || ($character > 0x7A && $character < 0x7E)
                    || ($character > 0x7E && $character < 0xA0)
                    || ($character > 0xD7FF && $character < 0xF900)
                ) {
                    for ($j = $start; $j <= $i; $j++) {
                        $string .= '%' . strtoupper($bytes[$j]);
                    }
                } else {
                    for ($j = $start; $j <= $i; $j++) {
                        $string .= chr(hexdec($bytes[$j]));
                    }
                }
            }
        }

        if ($remaining) {
            for ($j = $start; $j < $len; $j++) {
                $string .= '%' . strtoupper($bytes[$j]);
            }
        }

        return $string;
    }

    private function scheme_normalization(): void
    {
        if ($this->scheme !== null) {
            if (isset($this->normalization[$this->scheme]['iuserinfo']) && $this->iuserinfo === $this->normalization[$this->scheme]['iuserinfo']) {
                $this->iuserinfo = null;
            }
            if (isset($this->normalization[$this->scheme]['ihost']) && $this->ihost === $this->normalization[$this->scheme]['ihost']) {
                $this->ihost = null;
            }
            if (isset($this->normalization[$this->scheme]['port']) && $this->port === $this->normalization[$this->scheme]['port']) {
                $this->port = null;
            }
            if (isset($this->normalization[$this->scheme]['ipath']) && $this->ipath === $this->normalization[$this->scheme]['ipath']) {
                $this->ipath = '';
            }
            if (isset($this->normalization[$this->scheme]['iquery']) && $this->iquery === $this->normalization[$this->scheme]['iquery']) {
                $this->iquery = null;
            }
            if (isset($this->normalization[$this->scheme]['ifragment']) && $this->ifragment === $this->normalization[$this->scheme]['ifragment']) {
                $this->ifragment = null;
            }
        }
    }

    /**
     * Check if the object represents a valid IRI.
     */
    public function is_valid(): bool
    {
        $isauthority = $this->iuserinfo !== null || $this->ihost !== null || $this->port !== null;
        if ($this->ipath !== '' &&
            (
                $isauthority && (
                    $this->ipath[0] !== '/' ||
                    substr($this->ipath, 0, 2) === '//'
                ) ||
                (
                    $this->scheme === null &&
                    !$isauthority &&
                    strpos($this->ipath, ':') !== false &&
                    (strpos($this->ipath, '/') === false ? true : strpos($this->ipath, ':') < strpos($this->ipath, '/'))
                )
            )
        ) {
            return false;
        }

        return true;
    }

    /**
     * Set the entire IRI.
     */
    private function set_iri(?string $iri): bool
    {
        static $cache = [];

        if ($iri === null) {
            return true;
        } elseif (isset($cache[$iri])) {
            [
                $this->scheme,
                $this->iuserinfo,
                $this->ihost,
                $this->port,
                $this->ipath,
                $this->iquery,
                $this->ifragment,
                $return
            ] = $cache[$iri];
            return $return;
        } else {
            $parsed = $this->parse_iri($iri);

            $return = $this->set_scheme($parsed['scheme'])
                && $this->set_authority($parsed['authority'])
                && $this->set_path($parsed['path'])
                && $this->set_query($parsed['query'])
                && $this->set_fragment($parsed['fragment']);

            $cache[$iri] = [
                $this->scheme,
                $this->iuserinfo,
                $this->ihost,
                $this->port,
                $this->ipath,
                $this->iquery,
                $this->ifragment,
                $return
            ];
            return $return;
        }
    }

    /**
     * Set the scheme.
     */
    private function set_scheme(?string $scheme): bool
    {
        if ($scheme === null) {
            $this->scheme = null;
        } elseif (!preg_match('/^[A-Za-z][0-9A-Za-z+\-.]*$/', $scheme)) {
            $this->scheme = null;
            return false;
        } else {
            $this->scheme = strtolower($scheme);
        }
        return true;
    }

    /**
     * Set the authority.
     */
    private function set_authority(?string $authority): bool
    {
        static $cache = [];

        if ($authority === null) {
            $this->iuserinfo = null;
            $this->ihost = null;
            $this->port = null;
            return true;
        } elseif (isset($cache[$authority])) {
            [
                $this->iuserinfo,
                $this->ihost,
                $this->port,
                $return
            ] = $cache[$authority];
            return $return;
        } else {
            $remaining = $authority;
            if (($iuserinfo_end = strrpos($remaining, '@')) !== false) {
                $iuserinfo = substr($remaining, 0, $iuserinfo_end);
                $remaining = substr($remaining, $iuserinfo_end + 1);
            } else {
                $iuserinfo = null;
            }

            $bracketPos = strpos($remaining, ']');
            $port_start = strpos($remaining, ':', $bracketPos !== false ? $bracketPos : 0);
            if ($port_start !== false) {
                $port = substr($remaining, $port_start + 1);
                if ($port === false) {
                    $port = null;
                }
                $remaining = substr($remaining, 0, $port_start);
            } else {
                $port = null;
            }

            $return = $this->set_userinfo($iuserinfo) &&
                      $this->set_host($remaining) &&
                      $this->set_port($port);

            $cache[$authority] = [
                $this->iuserinfo,
                $this->ihost,
                $this->port,
                $return
            ];

            return $return;
        }
    }

    /**
     * Set the iuserinfo.
     */
    private function set_userinfo(?string $iuserinfo): bool
    {
        if ($iuserinfo === null) {
            $this->iuserinfo = null;
        } else {
            $this->iuserinfo = $this->replace_invalid_with_pct_encoding($iuserinfo, '!$&\'()*+,;=:');
            $this->scheme_normalization();
        }
        return true;
    }

    /**
     * Set the ihost.
     */
    private function set_host(?string $ihost): bool
    {
        if ($ihost === null) {
            $this->ihost = null;
            return true;
        } elseif (substr($ihost, 0, 1) === '[' && substr($ihost, -1) === ']') {
            $ipv6Content = substr($ihost, 1, -1);
            if (class_exists('Requests_IPv6') && Requests_IPv6::check_ipv6($ipv6Content)) {
                $this->ihost = '[' . Requests_IPv6::compress($ipv6Content) . ']';
            } else {
                $this->ihost = null;
                return false;
            }
        } else {
            $ihost = $this->replace_invalid_with_pct_encoding($ihost, '!$&\'()*+,;=');
            $position = 0;
            $strlen = strlen($ihost);
            while (($position += strcspn($ihost, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ%', $position)) < $strlen) {
                if ($ihost[$position] === '%') {
                    $position += 3;
                } else {
                    $ihost[$position] = strtolower($ihost[$position]);
                    $position++;
                }
            }
            $this->ihost = $ihost;
        }

        $this->scheme_normalization();
        return true;
    }

    /**
     * Set the port.
     */
    private function set_port($port): bool
    {
        if ($port === null) {
            $this->port = null;
            return true;
        } elseif (is_string($port) && strspn($port, '0123456789') === strlen($port)) {
            $this->port = (int) $port;
            $this->scheme_normalization();
            return true;
        } elseif (is_int($port)) {
            $this->port = $port;
            $this->scheme_normalization();
            return true;
        } else {
            $this->port = null;
            return false;
        }
    }

    /**
     * Set the ipath.
     */
    private function set_path(?string $ipath): bool
    {
        static $cache = [];
        $ipath = (string) $ipath;

        if (isset($cache[$ipath])) {
            $this->ipath = $cache[$ipath][(int) ($this->scheme !== null)];
        } else {
            $valid = $this->replace_invalid_with_pct_encoding($ipath, '!$&\'()*+,;=@:/');
            $removed = $this->remove_dot_segments($valid);

            $cache[$ipath] = [$valid, $removed];
            $this->ipath = ($this->scheme !== null) ? $removed : $valid;
        }

        $this->scheme_normalization();
        return true;
    }

    /**
     * Set the iquery.
     */
    private function set_query(?string $iquery): bool
    {
        if ($iquery === null) {
            $this->iquery = null;
        } else {
            $this->iquery = $this->replace_invalid_with_pct_encoding($iquery, '!$&\'()*+,;=:@/?', true);
            $this->scheme_normalization();
        }
        return true;
    }

    /**
     * Set the ifragment.
     */
    private function set_fragment(?string $ifragment): bool
    {
        if ($ifragment === null) {
            $this->ifragment = null;
        } else {
            $this->ifragment = $this->replace_invalid_with_pct_encoding($ifragment, '!$&\'()*+,;=:@/?');
            $this->scheme_normalization();
        }
        return true;
    }

    /**
     * Convert an IRI to a URI
     */
    private function to_uri(string $string): string
    {
        static $non_ascii = null;
        if (!$non_ascii) {
            $non_ascii = implode('', range("\x80", "\xFF"));
        }

        $position = 0;
        $strlen = strlen($string);
        while (($position += strcspn($string, $non_ascii, $position)) < $strlen) {
            $string = substr_replace($string, sprintf('%%%02X', ord($string[$position])), $position, 1);
            $position += 3;
            $strlen += 2;
        }

        return $string;
    }

    /**
     * Get the complete IRI
     */
    private function get_iri(): ?string
    {
        if (!$this->is_valid()) {
            return null;
        }

        $iri = '';
        if ($this->scheme !== null) {
            $iri .= $this->scheme . ':';
        }
        if (($iauthority = $this->get_iauthority()) !== null) {
            $iri .= '//' . $iauthority;
        }
        $iri .= $this->ipath;
        if ($this->iquery !== null) {
            $iri .= '?' . $this->iquery;
        }
        if ($this->ifragment !== null) {
            $iri .= '#' . $this->ifragment;
        }

        return $iri;
    }

    /**
     * Get the complete URI
     */
    private function get_uri(): ?string
    {
        $iri = $this->get_iri();
        return $iri !== null ? $this->to_uri($iri) : null;
    }

    /**
     * Get the complete iauthority
     */
    private function get_iauthority(): ?string
    {
        if ($this->iuserinfo !== null || $this->ihost !== null || $this->port !== null) {
            $iauthority = '';
            if ($this->iuserinfo !== null) {
                $iauthority .= $this->iuserinfo . '@';
            }
            if ($this->ihost !== null) {
                $iauthority .= $this->ihost;
            }
            if ($this->port !== null) {
                $iauthority .= ':' . $this->port;
            }
            return $iauthority;
        } else {
            return null;
        }
    }

    /**
     * Get the complete authority
     */
    private function get_authority()
    {
        $iauthority = $this->get_iauthority();
        if (is_string($iauthority)) {
            return $this->to_uri($iauthority);
        } else {
            return $iauthority;
        }
    }
}