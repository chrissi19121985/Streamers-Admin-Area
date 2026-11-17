<?php
/**
 * Authentication provider interface - Modernisiert für PHP 8.x
 *
 * @package Requests
 * @subpackage Authentication
 */

declare(strict_types=1);

interface Requests_Auth {
	/**
	 * Register hooks as needed
	 *
	 * This method is called in {@see Requests::request} when the user has set
	 * an instance as the 'auth' option. Use this callback to register all the
	 * hooks you'll need.
	 *
	 * @see Requests_Hooks::register
	 * @param Requests_Hooks $hooks Hook system
	 */
	public function register(Requests_Hooks &$hooks): void;
}