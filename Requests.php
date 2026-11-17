<?php
/**
 * Requests for PHP - Modernisiert für PHP 8.x
 *
 * Inspired by Requests for Python.
 * Based on concepts from SimplePie_File, RequestCore and WP_Http.
 *
 * @package Requests
 */

declare(strict_types=1);

class Requests {
    /**
     * POST method
     *
     * @var string
     */
    public const POST = 'POST';

    /**
     * GET method
     *
     * @var string
     */
    public const GET = 'GET';

    /**
     * HEAD method
     *
     * @var string
     */
    public const HEAD = 'HEAD';

    /**
     * Current version of Requests
     *
     * @var string
     */
    public const VERSION = '1.8.0';

    /**
     * Registered transport classes
     *
     * @var array
     */
    protected static array $transports = [];

    /**
     * Selected transport name
     *
     * Use {@see get_transport()} instead
     *
     * @var string|null
     */
    public static ?string $transport = null;

    /**
     * This is a static class, do not instantiate it
     *
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * Autoloader for Requests
     *
     * @codeCoverageIgnore
     *
     * @param string $class Class name to load
     */
    public static function autoloader(string $class): void {
        if (!str_starts_with($class, 'Requests')) {
            return;
        }

        $file = str_replace('_', '/',$class);
        $filepath = __DIR__ . '/' .$file . '.php';
        if (file_exists($filepath)) {
            require_once $filepath;
        }
    }

    /**
     * Register the built-in autoloader
     *
     * @codeCoverageIgnore
     */
    public static function register_autoloader(): void {
        spl_autoload_register([self::class, 'autoloader']);
    }

    /**
     * Register a transport
     *
     * @param string $transport Transport class to add, must support the Requests_Transport interface
     */
    public static function add_transport(string $transport): void {
        if (empty(self::$transports)) {
            self::$transports = [
                'Requests_Transport_cURL',
                'Requests_Transport_fsockopen',
            ];
        }

        self::$transports[] =$transport;
    }

    /**
     * Get a working transport
     *
     * @throws Requests_Exception If no valid transport is found (`notransport`)
     * @return mixed
     */
    protected static function get_transport() {
        if (!is_null(self::$transport)) {
            $transportClass = self::$transport;
            return new $transportClass();
        }

        if (empty(self::$transports)) {
            self::$transports = [
                'Requests_Transport_cURL',
                'Requests_Transport_fsockopen',
            ];
        }

        foreach (self::$transports as$class) {
            if (!class_exists($class)) {
                continue;
            }

            $result = call_user_func([$class, 'test']);
            if ($result) {
                self::$transport =$class;
                break;
            }
        }

        if (self::$transport === null) {
            throw new Requests_Exception('No working transports found', 'notransport', self::$transports);
        }

        $transportClass = self::$transport;
        return new $transportClass();
    }

    /**
     * Send a GET request
     */
    public static function get(string $url, array $headers = [], array$options = []) {
        return self::request($url, $headers, null, self::GET,$options);
    }

    /**
     * Send a HEAD request
     */
    public static function head(string $url, array $headers = [], array$options = []) {
        return self::request($url, $headers, null, self::HEAD,$options);
    }

    /**
     * Send a POST request
     */
    public static function post(string $url, array$headers = [], $data = [], array$options = []) {
        return self::request($url,$headers, $data, self::POST,$options);
    }

    /**
     * Main interface for HTTP requests
     */
    public static function request(string $url, array $headers = [],$data = [], string $type = self::GET, array$options = []) {
        if (!preg_match('/^http(s)?:\/\//i', $url)) {
            throw new Requests_Exception('Only HTTP requests are handled.', 'nonhttp', $url);
        }

        $defaults = [
            'timeout' => 10,
            'useragent' => 'php-requests/' . self::VERSION,
            'redirected' => 0,
            'redirects' => 10,
            'follow_redirects' => true,
            'blocking' => true,
            'type' => $type,
            'filename' => false,
            'auth' => false,
            'idn' => true,
            'hooks' => null,
            'transport' => null,
        ];
        $options = array_merge($defaults,$options);

        if (empty($options['hooks'])) {$options['hooks'] = new Requests_Hooks();
        }

        if (is_array($options['auth'])) {
            $options['auth'] = new Requests_Auth_Basic($options['auth']);
        }
        if ($options['auth'] !== false) {
            $options['auth']->register($options['hooks']);
        }

        $options['hooks']->dispatch('requests.before_request', [&$url, &$headers, &$data, &$type, &$options]);

        if ($options['idn'] !== false && class_exists('Requests_IRI') && class_exists('Requests_IDNAEncoder')) {
            $iri = new Requests_IRI($url);
            $iri->host = Requests_IDNAEncoder::encode($iri->ihost);
            $url =$iri->uri;
        }

        if (!empty($options['transport'])) {
            $transport =$options['transport'];
            if (is_string($options['transport'])) {
                $transport = new$transport();
            }
        } else {
            $transport = self::get_transport();
        }
        
        $response =$transport->request($url,$headers, $data,$options);

        $options['hooks']->dispatch('requests.before_parse', [&$response,$url, $headers,$data, $type,$options]);

        return self::parse_response($response, $url,$headers, $data,$options);
    }

    /**
     * HTTP response parser
     */
    protected static function parse_response($headers, string$url, array $req_headers,$req_data, array $options) {$return = new Requests_Response();
        if (!$options['blocking']) {
            return $return;
        }

        $return->url =$url;

        if (!$options['filename']) {
            if (strpos($headers, "\r\n\r\n") === false) {
                throw new Requests_Exception('Missing header/body separator', 'requests.no_crlf_separator');
            }

            $headerParts = explode("\r\n\r\n", $headers, 2);
            $return->body = array_pop($headerParts);
            $headers =$headerParts[0] ?? '';
        } else {
            $return->body = '';
        }

        $headers = str_replace("\r\n", "\n", $headers);
        $headers = preg_replace('/\n[ \t]/', ' ',$headers);
        $headers = explode("\n", $headers);
        
        $statusLine = array_shift($headers);
        preg_match('#^HTTP/1\.\d[ \t]+(\d+)#i', $statusLine ?? '', $matches);
        if (empty($matches)) {
            throw new Requests_Exception('Response could not be parsed', 'noversion', $headers);
        }
        
        $return->status_code = (int)$matches[1];
        if ($return->status_code >= 200 && $return->status_code < 300) {$return->success = true;
        }

        foreach ($headers as$header) {
            if (trim($header) === '') {
                continue;
            }
            $headerParts = explode(':',$header, 2);
            if (count($headerParts) === 2) {$key = trim($headerParts[0]);$value = trim($headerParts[1]);$value = preg_replace('#(\s+)#i', ' ', $value);$return->headers[$key] =$value;
            }
        }

        if (isset($return->headers['transfer-encoding'])) {
            $return->body = self::decode_chunked($return->body);
            unset($return->headers['transfer-encoding']);
        }
        if (isset($return->headers['content-encoding'])) {
            $return->body = self::decompress($return->body);
        }

        if (isset($return->headers['connection'])) {
            unset($return->headers['connection']);
        }

        $statusCode =$return->status_code;
        if (((in_array($statusCode, [300, 301, 302, 303, 307], true) || ($statusCode > 307 && $statusCode < 400))) && $options['follow_redirects'] === true) {
            if (isset($return->headers['location']) && $options['redirected'] <$options['redirects']) {
                $options['redirected']++;$location = $return->headers['location'];$redirected = self::request($location,$req_headers, $req_data, self::GET,$options);
                $redirected->history[] =$return;
                return $redirected;
            } elseif ($options['redirected'] >=$options['redirects']) {
                throw new Requests_Exception('Too many redirects', 'toomanyredirects', $return);
            }
        }

        $return->redirects =$options['redirected'];

        $options['hooks']->dispatch('requests.after_request', [&$return,$req_headers, $req_data,$options]);
        return $return;
    }

    /**
     * Decoded a chunked body as per RFC 2616
     */
    protected static function decode_chunked(string $data): string {
        if (!preg_match('/^([0-9a-f]+)[^\r\n]*\r\n/i', trim($data))) {
            return $data;
        }

        $decoded = '';
        $encoded =$data;

        while (true) {
            $is_chunked = (bool) preg_match('/^([0-9a-f]+)[^\r\n]*\r\n/i', $encoded,$matches);
            if (!$is_chunked) {
                return $data;
            }

            $length = hexdec(trim($matches[1]));
            if ($length === 0) {
                return $decoded;
            }

            $chunk_length = strlen($matches[0]);
            $decoded .= substr($encoded, $chunk_length,$length);
            $encoded = substr($encoded, $chunk_length +$length + 2);

            if (trim($encoded) === '0' || empty($encoded)) {
                return $decoded;
            }
        }
    }

    /**
     * Convert a key => value array to a 'key: value' array for headers
     */
    public static function flattern(array $array): array {$return = [];
        foreach ($array as$key => $value) {$return[] = "$key:$value";
        }
        return $return;
    }

    /**
     * Decompress an encoded body
     */
    protected static function decompress(string $data): string {
        if (substr($data, 0, 2) !== "\x1f\x8b") {
            return $data;
        }

        if (function_exists('gzdecode') && ($decoded = gzdecode($data)) !== false) {
            return $decoded;
        } elseif (function_exists('gzinflate') && ($decoded = @gzinflate($data)) !== false) {
            return $decoded;
        } elseif (($decoded = self::compatible_gzinflate($data)) !== false) {
            return $decoded;
        } elseif (function_exists('gzuncompress') && ($decoded = @gzuncompress($data)) !== false) {
            return $decoded;
        }

        return $data;
    }

    /**
     * Decompress deflated string while staying compatible with the majority of servers.
     */
    protected static function compatible_gzinflate(string $gzData) {
        if (substr($gzData, 0, 3) === "\x1f\x8b\x08") {
            $i = 10;
            $flg = ord(substr($gzData, 3, 1));
            if ($flg > 0) {
                if ($flg & 4) {$unpacked = unpack('v', substr($gzData,$i, 2));
                    $xlen =$unpacked[1] ?? 0;
                    $i = $i + 2 +$xlen;
                }
                if ($flg & 8) {$pos = strpos($gzData, "\0", $i);
                    $i = ($pos !== false) ? $pos + 1 :$i;
                }
                if ($flg & 16) {$pos = strpos($gzData, "\0", $i);
                    $i = ($pos !== false) ? $pos + 1 :$i;
                }
                if ($flg & 2) {$i = $i + 2; } }$inflated = @gzinflate(substr($gzData,$i, -8));
            return $inflated !== false ? $inflated : false;
        } else {
            return false;
        }
    }
}