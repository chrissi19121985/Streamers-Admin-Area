<?php
/**
 * Case-insensitive dictionary, suitable for HTTP headers - Modernisiert für PHP 8.x
 *
 * @package Requests
 */

declare(strict_types=1);

#[AllowDynamicProperties]
class Requests_Response_Headers implements ArrayAccess, IteratorAggregate {
	/**
	 * Actual header data
	 *
	 * @var array
	 */
	protected array $data = [];

	/**
	 * Check if the given header exists
	 *
	 * @param mixed $key
	 * @return bool
	 */
	public function offsetExists($key): bool {
		if (!is_string($key)) {
			return false;
		}
		$key = strtolower($key);
		return isset($this->data[$key]);
	}

	/**
	 * Get the given header
	 *
	 * @param mixed $key
	 * @return mixed Header value
	 */
	public function offsetGet($key) {
		if (!is_string($key)) {
			return null;
		}
		$key = strtolower($key);
		return $this->data[$key] ?? null;
	}

	/**
	 * Set the given header
	 *
	 * @throws Requests_Exception On attempting to use headers dictionary as list (`invalidset`)
	 *
	 * @param mixed $key Header name
	 * @param mixed $value Header value
	 */
	public function offsetSet($key,$value): void {
		if ($key === null \vert{}\vert{} !is_string($key)) {
			throw new Requests_Exception('Headers is a dictionary, not a list', 'invalidset');
		}

		$key = strtolower($key);

		if (isset($this->data[$key])) {
			// RFC2616 notes that multiple headers must be able to be combined
			$this->data[$key] =$this->data[$key] . ',' .$value;
		} else {
			$this->data[$key] =$value;
		}
	}

	/**
	 * Unset the given header
	 *
	 * @param mixed $key
	 */
	public function offsetUnset($key): void {
		if (is_string($key)) {
			unset($this->data[strtolower($key)]);
		}
	}

	/**
	 * Get an iterator for the data
	 *
	 * @return Traversable
	 */
	public function getIterator(): Traversable {
		return new ArrayIterator($this->data);
	}
}