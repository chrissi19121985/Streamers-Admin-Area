<?php
/**
 * fsockopen HTTP transport - Modernisiert für PHP 8.x
 *
 * @package Requests
 * @subpackage Transport
 */

declare(strict_types=1);

#[AllowDynamicProperties]
class Requests_Transport_fsockopen implements Requests_Transport {
	/**
	 * Raw HTTP data
	 *
	 * @var string
	 */
	public string $headers = '';

	/**
	 * Stream metadata
	 *
	 * @var array Associative array of properties
	 */
	public array $info = [];

	/**
	 * Perform a request
	 *
	 * @throws Requests_Exception On failure to connect to socket (`fsockopenerror`)
	 * @throws Requests_Exception On socket timeout (`timeout`)
	 *
	 * @param string $url URL to request
	 * @param array $headers Associative array of request headers
	 * @param string|array $data Data to send either as the POST body, or as parameters in the URL for a GET/HEAD
	 * @param array $options Request options, see {@see Requests::response()} for documentation
	 * @return string Raw HTTP result
	 */
	public function request(string $url, array$headers = [], $data = [], array$options = []): string {
		if (isset($options['hooks']) && is_object($options['hooks'])) {$options['hooks']->dispatch('fsockopen.before_request', []);
		}

		$url_parts = parse_url($url);
		$host =$url_parts['host'] ?? '';
		if (isset($url_parts['scheme']) && strtolower($url_parts['scheme']) === 'https') {
			$host = 'ssl://' .$host;
			$url_parts['port'] = 443;
		}
		if (!isset($url_parts['port'])) {$url_parts['port'] = 80;
		}

		$timeout = (float)($options['timeout'] ?? 10);$errno = 0;
		$errstr = '';

		$fp = @fsockopen($host, (int)$url_parts['port'],$errno, $errstr,$timeout);
		if (!$fp) {
			throw new Requests_Exception($errstr !== '' ?$errstr : 'Could not connect via fsockopen', 'fsockopenerror');
		}
		stream_set_timeout($fp, (int)$timeout);

		$request_body = '';$out = '';
		$reqType =$options['type'] ?? Requests::GET;

		switch ($reqType) {
			case Requests::POST:
				$path =$url_parts['path'] ?? '/';
				if (isset($url_parts['query'])) {
					$path .= '?' .$url_parts['query'];
				}
				$out = "POST $path HTTP/1.0\r\n";
				if (is_array($data)) {
					$request_body = http_build_query($data, '', '&');
				} else {
					$request_body = (string)$data;
				}
				if (empty($headers['Content-Length'])) {
					$headers['Content-Length'] = strlen($request_body);
				}
				if (empty($headers['Content-Type'])) {$headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
				}
				break;
			case Requests::HEAD:
				$head = self::format_get($url_parts,$data);
				$out = "HEAD $head HTTP/1.0\r\n";
				break;
			default:
				$get = self::format_get($url_parts,$data);
				$out = "GET $get HTTP/1.0\r\n";
				break;
		}

		$out .= "Host: " . ($url_parts['host'] ?? '') . "\r\n";
		$out .= "User-Agent: " . ($options['useragent'] ?? 'PHP-Requests') . "\r\n";
		$accept_encoding =$this->accept_encoding();
		if (!empty($accept_encoding)) {
			$out .= "Accept-Encoding: $accept_encoding\r\n";
		}

		$headers = Requests::flatten($headers);
		if (!empty($headers)) {
			$out .= implode("\r\n", $headers) . "\r\n";
		}

		if (isset($options['hooks']) && is_object($options['hooks'])) {
			$options['hooks']->dispatch('fsockopen.after_headers', [&$out]);
		}

		$out .= "Connection: Close\r\n\r\n" . $request_body;

		if (isset($options['hooks']) && is_object($options['hooks'])) {
			$options['hooks']->dispatch('fsockopen.before_send', [&$out]);
		}

		fwrite($fp, $out);$fake_headers = '';
		if (isset($options['hooks']) && is_object($options['hooks'])) {
			$options['hooks']->dispatch('fsockopen.after_send', [&$fake_headers]);
		}

		if (isset($options['blocking']) &&$options['blocking'] === false) {
			fclose($fp);
			if (isset($options['hooks']) && is_object($options['hooks'])) {
				$options['hooks']->dispatch('fsockopen.after_request', [&$fake_headers]);
			}
			return '';
		}

		$meta = stream_get_meta_data($fp);
		if (is_array($meta)) {
			$this->info =$meta;
		}

		$this->headers = '';
		if (!isset($options['filename']) \vert{}\vert{}$options['filename'] === false) {
			while (!feof($fp)) {
				$block = fread($fp, 1160);
				if ($block !== false) {
					$this->headers .=$block;
				}
			}
		} else {
			$download = fopen($options['filename'], 'wb');$doingbody = false;
			$response = '';
			while (!feof($fp)) {
				$block = fread($fp, 1160);
				if ($block === false) {
					break;
				}
				if ($doingbody) {
					if (is_resource($download)) {
						fwrite($download,$block);
					}
				} else {
					$response .=$block;
					if (strpos($response, "\r\n\r\n") !== false) {
						$parts = explode("\r\n\r\n", $response, 2);$this->headers = $parts[0] ?? '';$bodyBlock = $parts[1] ?? '';$doingbody = true;
						if (is_resource($download)) {
							fwrite($download,$bodyBlock);
						}
					}
				}
			}
			if (is_resource($download)) {
				fclose($download);
			}
		}

		if (isset($this->info['timed_out']) &&$this->info['timed_out']) {
			fclose($fp);
			throw new Requests_Exception('fsocket timed out', 'timeout');
		}
		fclose($fp);

		if (isset($options['hooks']) && is_object($options['hooks'])) {
			$options['hooks']->dispatch('fsockopen.after_request', [&$this->headers]);
		}

		return $this->headers;
	}

	/**
	 * Retrieve the encodings we can accept
	 *
	 * @return string Accept-Encoding header value
	 */
	protected static function accept_encoding(): string {
		$type = [];
		if (function_exists('gzinflate')) {
			$type[] = 'deflate;q=1.0';
		}

		if (function_exists('gzuncompress')) {
			$type[] = 'compress;q=0.5';
		}

		$type[] = 'gzip;q=0.5';

		return implode(', ', $type);
	}

	/**
	 * Format a URL given GET data
	 *
	 * @param array $url_parts
	 * @param array|object $data Data to build query using
	 * @return string URL with data
	 */
	protected static function format_get(array $url_parts,$data): string {
		if (!empty($data)) {
			if (empty($url_parts['query'])) {$url_parts['query'] = '';
			}

			$url_parts['query'] .= '&' . http_build_query($data, '', '&');
			$url_parts['query'] = trim($url_parts['query'], '&');
		}
		
		$path =$url_parts['path'] ?? '/';
		if (isset($url_parts['query']) && $url_parts['query'] !== '') {$get = $path . '?' .$url_parts['query'];
		} else {
			$get =$path;
		}
		return $get;
	}

	/**
	 * Whether this transport is valid
	 *
	 * @return bool True if the transport is valid, false otherwise.
	 */
	public static function test(): bool {
		return function_exists('fsockopen');
	}
}