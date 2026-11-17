<?php
/**
 * Handles adding and dispatching events - Modernisiert für PHP 8.x
 *
 * @package Requests
 * @subpackage Utilities
 */

declare(strict_types=1);

#[AllowDynamicProperties]
class Requests_Hooks {
	/**
	 * Registered hooks
	 * @var array
	 */
	protected array $hooks = [];

	/**
	 * Constructor
	 */
	public function __construct() {
		// pass
	}

	/**
	 * Register a callback for a hook
	 *
	 * @param string $hook Hook name
	 * @param callable $callback Function/method to call on event
	 * @param int $priority Priority number. <0 is executed earlier, >0 is executed later
	 */
	public function register(string $hook, callable $callback, int $priority = 0): void {
		if (!isset($this->hooks[$hook])) {
			$this->hooks[$hook] = [];
		}
		if (!isset($this->hooks[$hook][$priority])) {
			$this->hooks[$hook][$priority] = [];
		}

		$this->hooks[$hook][$priority][] = $callback;
	}

	/**
	 * Dispatch a message
	 *
	 * @param string $hook Hook name
	 * @param array $parameters Parameters to pass to callbacks
	 * @return bool Successfulness
	 */
	public function dispatch(string $hook, array $parameters = []): bool {
		if (empty($this->hooks[$hook])) {
			return false;
		}

		foreach ($this->hooks[$hook] as $priority => $hooked) {
			foreach ($hooked as $callback) {
				call_user_func_array($callback, $parameters);
			}
		}

		return true;
	}
}