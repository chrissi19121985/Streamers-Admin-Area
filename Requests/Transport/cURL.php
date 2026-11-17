<?php
/**
 * cURL HTTP transport - Modernisiert für PHP 8.x
 *
 * @package Requests
 * @subpackage Transport
 */

declare(strict_types=1);

#[AllowDynamicProperties]
class Requests_Transport_cURL implements Requests_Transport {
	/**
	 * Raw HTTP data
	 *
	 * @var string
	 */
	public string $headers = '';

	/**
	 * Information on the current request
	 *
	 * @var array cURL information array
	 */
	public array $info = [];

	/**
	 * Version string
	 *
	 * @var string
	 */
	public string $version = '';

	/**
	 * cURL handle
	 *
	 * @var resource|CurlHandle|null
	 */
	protected $fp = null;

	/**
	 * Have we finished the headers yet?
	 *
	 * @var bool
	 */
	protected bool $done_headers = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		$curl = curl_version();$this->version = $curl['version'] ?? '';$ch = curl_init();
		if ($ch !== false) {
			$this->fp =$ch;
			curl_setopt($this->fp, CURLOPT_HEADER, false);
			curl_setopt($this->fp, CURLOPT_RETURNTRANSFER, 1);
			if (version_compare($this->version, '7.10.5', '>=')) {
				curl_setopt($this->fp, CURLOPT_ENCODING, '');
			}
			curl_setopt($this->fp, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($this->fp, CURLOPT_SSL_VERIFYPEER, 0);
		}
	}

	/**
	 * Perform a request
	 *
	 * @throws Requests_Exception On a cURL error (`curlerror`)
	 *
	 * @param string $url URL to request
	 * @param array $headers Associative array of request headers
	 * @param string|array $data Data to send either as the POST body, or as parameters in the URL for a GET/HEAD
	 * @param array $options Request options, see {@see Requests::response()} for documentation
	 * @return string Raw HTTP result
	 */
	public function request(string $url, array$headers = [], $data = [], array$options = []): string {
		if ($this->fp === null) {$ch = curl_init();
			if ($ch !== false) {
				$this->fp =$ch;
			}
		}

		if (isset($options['hooks']) && is_object($options['hooks'])) {
			$options['hooks']->dispatch('curl.before_request', [&$this->fp]);
		}

		$headers = Requests::flatten($headers);
		$reqType =$options['type'] ?? Requests::GET;

		if (($reqType === Requests::HEAD \vert{}\vert{}$reqType === Requests::GET) && !empty($data)) {$url = self::format_get($url,$data);
		}

		switch ($reqType) {
			case Requests::POST:
				curl_setopt($this->fp, CURLOPT_POST, true);
				curl_setopt($this->fp, CURLOPT_POSTFIELDS,$data);
				break;
			case Requests::HEAD:
				curl_setopt($this->fp, CURLOPT_NOBODY, true);
				break;
		}

		curl_setopt($this->fp, CURLOPT_URL,$url);
		curl_setopt($this->fp, CURLOPT_TIMEOUT, (int)($options['timeout'] ?? 10));
		curl_setopt($this->fp, CURLOPT_CONNECTTIMEOUT, (int)($options['timeout'] ?? 10));
		curl_setopt($this->fp, CURLOPT_REFERER,$url);
		curl_setopt($this->fp, CURLOPT_USERAGENT,$options['useragent'] ?? 'PHP-Requests');
		curl_setopt($this->fp, CURLOPT_HTTPHEADER,$headers);

		if (isset($options['blocking']) && true ===$options['blocking']) {
			curl_setopt($this->fp, CURLOPT_HEADERFUNCTION, [$this, 'stream_headers']);
		}

		if (isset($options['hooks']) && is_object($options['hooks'])) {
			$options['hooks']->dispatch('curl.before_send', [&$this->fp]);
		}

		$stream_handle = null;
		if (isset($options['filename']) &&$options['filename'] !== false) {
			$stream_handle = fopen($options['filename'], 'wb');
			curl_setopt($this->fp, CURLOPT_FILE,$stream_handle);
		}

		$response = curl_exec($this->fp);

		$fake_headers = '';
		if (isset($options['hooks']) && is_object($options['hooks'])) {
			$options['hooks']->dispatch('curl.after_send', [&$fake_headers]);
		}

		if (isset($options['blocking']) &&$options['blocking'] === false) {
			curl_close($this->fp);
			if (isset($options['hooks']) && is_object($options['hooks'])) {
				$options['hooks']->dispatch('curl.after_request', [&$fake_headers]);
			}
			return '';
		}

		if (isset($options['filename']) && $options['filename'] !== false && is_resource($stream_handle)) {
			fclose($stream_handle);
			$this->headers = trim($this->headers);
		} else {
			if (is_string($response)) {
				$this->headers .=$response;
			}
		}

		$curl_errno = curl_errno($this->fp);
		if ($curl_errno === 23 \vert{}\vert{}$curl_errno === 61) {
			curl_setopt($this->fp, CURLOPT_ENCODING, 'none');
			$retry_res = curl_exec($this->fp);
			if (is_string($retry_res)) {
				$this->headers =$retry_res;
			}
		}

		if (curl_errno($this->fp)) {
			$error_msg = curl_error($this->fp);
			$error_no = curl_errno($this->fp);
			curl_close($this->fp);
			throw new Requests_Exception('cURL error ' . $error_no . ': ' . $error_msg, 'curlerror',$this->fp);
		}

		$info = curl_getinfo($this->fp);
		if (is_array($info)) {
			$this->info =$info;
		}
		
		curl_close($this->fp);

		if (isset($options['hooks']) && is_object($options['hooks'])) {
			$options['hooks']->dispatch('curl.after_request', [&$this->headers]);
		}

		return $this->headers;
	}

	/**
	 * Collect the headers as they are received
	 *
	 * @param $handle cURL resource
	 * @param string $headers Header string
	 * @return int Length of provided header
	 */
	protected function stream_headers($handle, string$headers): int {
		if ($this->done_headers) {
			$this->headers = '';$this->done_headers = false;
		}
		$this->headers .=$headers;

		if ($headers === "\r\n") {
			$this->done_headers = true;
		}
		return strlen($headers);
	}

	/**
	 * Format a URL given GET data
	 *
	 * @param string $url
	 * @param array|object $data Data to build query using
	 * @return string URL with data
	 */
	protected static function format_get(string $url,$data): string {
		if (!empty($data)) {
			$url_parts = parse_url($url);
			$query =$url_parts['query'] ?? '';

			$query .= '&' . http_build_query($data, '', '&');
			$query = trim($query, '&');

			if (empty($url_parts['query'])) {
				$url .= '?' .$query;
			} else {
				$url = str_replace($url_parts['query'], $query,$url);
			}
		}
		return $url;
	}

	/**
	 * Whether this transport is valid
	 *
	 * @return bool True if the transport is valid, false otherwise.
	 */
	public static function test(): bool {
		return (function_exists('curl_init') && function_exists('curl_exec'));
	}
}