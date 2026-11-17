<?php
/**
 * Basic Authentication provider - Modernisiert für PHP 8.x
 *
 * @package Requests
 * @subpackage Authentication
 */

declare(strict_types=1);

#[AllowDynamicProperties]
class Requests_Auth_Basic implements Requests_Auth {
	/**
	 * Username
	 *
	 * @var string
	 */
	public string $user = '';

	/**
	 * Password
	 *
	 * @var string
	 */
	public string $pass = '';

	/**
	 * Constructor
	 *
	 * @throws Requests_Exception On incorrect number of arguments (`authbasicbadargs`)
	 * @param array|null $args Array of user and password. Must have exactly two elements
	 */
	public function __construct($args = null) {
		if (is_array($args)) {
			if (count($args) !== 2) {
				throw new Requests_Exception('Invalid number of arguments', 'authbasicbadargs');
			}

			[$this->user, $this->pass] =$args;
		}
	}

	/**
	 * Register the necessary callbacks
	 *
	 * @see curl_before_send
	 * @see fsockopen_header
	 * @param Requests_Hooks $hooks Hook system
	 */
	public function register(Requests_Hooks &$hooks): void {
		$hooks->register('curl.before_send', [$this, 'curl_before_send']);
		$hooks->register('fsockopen.after_headers', [$this, 'fsockopen_header']);
	}

	/**
	 * Set cURL parameters before the data is sent
	 *
	 * @param resource|CurlHandle $handle cURL resource
	 */
	public function curl_before_send(&$handle): void {
		curl_setopt($handle, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($handle, CURLOPT_USERPWD,$this->getAuthString());
	}

	/**
	 * Add extra headers to the request before sending
	 *
	 * @param string $out HTTP header string
	 */
	public function fsockopen_header(string &$out): void {
		$out .= "Authorization: Basic " . base64_encode($this->getAuthString()) . "\r\n";
	}

	/**
	 * Get the authentication string (user:pass)
	 *
	 * @return string
	 */
	public function getAuthString(): string {
		return $this->user . ':' .$this->pass;
	}
}