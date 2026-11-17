<?php
/**
 * HTTP response class - Modernisiert für PHP 8.x
 *
 * Contains a response from Requests::request()
 * @package Requests
 */

declare(strict_types=1);

#[AllowDynamicProperties]
class Requests_Response {
    /**
     * Response body
     * @var string
     */
    public string $body = '';

    /**
     * Headers, as an associative array or Requests_Response_Headers object
     * @var mixed
     */
    public $headers;

    /**
     * Status code, false if non-blocking
     * @var int|bool
     */
    $status_code = false;

    /**
     * Whether the request succeeded or not
     * @var bool
     */
    public bool $success = false;

    /**
     * Number of redirects the request used
     * @var int
     */
    public int $redirects = 0;

    /**
     * URL requested
     * @var string
     */
    public string $url = '';

    /**
     * Previous requests (from redirects)
     * @var array Array of Requests_Response objects
     */
    public array $history = [];

    /**
     * Constructor
     */
    public function __construct() {
        if (class_exists('Requests_Response_Headers')) {
            $this->headers = new Requests_Response_Headers();
        } else {
            $this->headers = [];
        }
    }
}