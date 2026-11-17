<?php
/**
 * Exception for HTTP requests - Modernisiert für PHP 8.x
 *
 * @package Requests
 */

declare(strict_types=1);

#[AllowDynamicProperties]
class Requests_Exception extends Exception {
	/**
	 * Type of exception
	 *
	 * @var string
	 */
	protected string $type;

	/**
	 * Data associated with the exception
	 *
	 * @var mixed
	 */
	protected $data;

	/**
	 * Create a new exception
	 *
	 * @param string $message Exception message
	 * @param string $type Exception type
	 * @param mixed $data Associated data
	 */
	public function __construct(string $message, string $type, $data = null) {
		parent::__construct($message, 0);

		$this->type = $type;
		$this->data = $data;
	}

	/**
	 * Like {@see getCode()}, but a string code.
	 *
	 * @return string
	 */
	public function getType(): string {
		return $this->type;
	}

	/**
	 * Gives any relevant data
	 *
	 * @return mixed
	 */
	public function getData() {
		return $this->data;
	}
}