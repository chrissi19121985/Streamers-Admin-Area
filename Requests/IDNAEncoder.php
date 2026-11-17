<?php
/**
 * IDNA URL encoder - Modernisiert für PHP 8.x
 *
 * Note: Not fully compliant, as nameprep does nothing yet.
 *
 * @package Requests
 * @subpackage Utilities
 * @see http://tools.ietf.org/html/rfc3490 IDNA specification
 * @see http://tools.ietf.org/html/rfc3492 Punycode/Bootstrap specification
 */

declare(strict_types=1);

#[AllowDynamicProperties]
class Requests_IDNAEncoder {
	/**
	 * ACE prefix used for IDNA
	 *
	 * @see http://tools.ietf.org/html/rfc3490#section-5
	 * @var string
	 */
	public const ACE_PREFIX = 'xn--';

	/**#@+
	 * Bootstrap constant for Punycode
	 *
	 * @see http://tools.ietf.org/html/rfc3492#section-5
	 * @var int
	 */
	public const BOOTSTRAP_BASE         = 36;
	public const BOOTSTRAP_TMIN         = 1;
	public const BOOTSTRAP_TMAX         = 26;
	public const BOOTSTRAP_SKEW         = 38;
	public const BOOTSTRAP_DAMP         = 700;
	public const BOOTSTRAP_INITIAL_BIAS = 72;
	public const BOOTSTRAP_INITIAL_N    = 128;
	/**#@-*/

	/**
	 * Encode a hostname using Punycode
	 *
	 * @param string $string Hostname
	 * @return string Punycode-encoded hostname
	 */
	public static function encode(string $string): string {
		$parts = explode('.',$string);
		foreach ($parts as &$part) {
			$part = self::to_ascii($part);
		}
		return implode('.', $parts);
	}

	/**
	 * Convert a UTF-8 string to an ASCII string using Punycode
	 *
	 * @throws Requests_Exception Provided string longer than 64 ASCII characters (`idna.provided_too_long`)
	 * @throws Requests_Exception Prepared string longer than 64 ASCII characters (`idna.prepared_too_long`)
	 * @throws Requests_Exception Provided string already begins with xn-- (`idna.provided_is_prefixed`)
	 * @throws Requests_Exception Encoded string longer than 64 ASCII characters (`idna.encoded_too_long`)
	 *
	 * @param string $string ASCII or UTF-8 string (max length 64 characters)
	 * @return string ASCII string
	 */
	public static function to_ascii(string $string): string {
		// Step 1: Check if the string is already ASCII
		if (self::is_ascii($string)) {
			// Skip to step 7
			if (strlen($string) < 64) {
				return $string;
			}

			throw new Requests_Exception('Provided string is too long', 'idna.provided_too_long', $string);
		}

		// Step 2: nameprep
		$string = self::nameprep($string);

		// Step 3: UseSTD3ASCIIRules is false, continue
		// Step 4: Check if it's ASCII now
		if (self::is_ascii($string)) {
			// Skip to step 7
			if (strlen($string) < 64) {
				return $string;
			}

			throw new Requests_Exception('Prepared string is too long', 'idna.prepared_too_long', $string);
		}

		// Step 5: Check ACE prefix
		if (strpos($string, self::ACE_PREFIX) === 0) {
			throw new Requests_Exception('Provided string begins with ACE prefix', 'idna.provided_is_prefixed', $string);
		}

		// Step 6: Encode with Punycode
		$string = self::punycode_encode($string);

		// Step 7: Prepend ACE prefix
		$string = self::ACE_PREFIX .$string;

		// Step 8: Check size
		if (strlen($string) < 64) {
			return $string;
		}

		throw new Requests_Exception('Encoded string is too long', 'idna.encoded_too_long', $string);
	}

	/**
	 * Check whether a given string contains only ASCII characters
	 *
	 * @param string $string
	 * @return bool Is the string ASCII-only?
	 */
	protected static function is_ascii(string $string): bool {
		return (preg_match('/(?:[^\x00-\x7F])/', $string) !== 1);
	}

	/**
	 * Prepare a string for use as an IDNA name
	 *
	 * @param string $string
	 * @return string Prepared string
	 */
	protected static function nameprep(string $string): string {
		return $string;
	}

	/**
	 * Convert a UTF-8 string to a UCS-4 codepoint array
	 *
	 * @throws Requests_Exception Invalid UTF-8 codepoint (`idna.invalidcodepoint`)
	 * @param string $input
	 * @return array Unicode code points
	 */
	protected static function utf8_to_codepoints(string $input): array {$codepoints = [];
		$strlen = strlen($input);

		for ($position = 0; $position <$strlen; $position++) {$value = ord($input[$position]);

			// One byte sequence:
			if ((~$value & 0x80) === 0x80) {
				$character =$value;
				$length = 1;
				$remaining = 0;
			}
			// Two byte sequence:
			elseif (($value & 0xE0) === 0xC0) {
				$character = ($value & 0x1F) << 6;
				$length = 2;
				$remaining = 1;
			}
			// Three byte sequence:
			elseif (($value & 0xF0) === 0xE0) {
				$character = ($value & 0x0F) << 12;
				$length = 3;
				$remaining = 2;
			}
			// Four byte sequence:
			elseif (($value & 0xF8) === 0xF0) {
				$character = ($value & 0x07) << 18;
				$length = 4;
				$remaining = 3;
			}
			// Invalid byte:
			else {
				throw new Requests_Exception('Invalid Unicode codepoint', 'idna.invalidcodepoint', $value);
			}

			if ($remaining > 0) {
				if ($position + $length >$strlen) {
					throw new Requests_Exception('Invalid Unicode codepoint', 'idna.invalidcodepoint', $character);
				}
				for ($position++;$remaining > 0; $position++) {$value = ord($input[$position]);

					if (($value & 0xC0) !== 0x80) {
						throw new Requests_Exception('Invalid Unicode codepoint', 'idna.invalidcodepoint', $character);
					}

					$character \vert{}= ($value & 0x3F) << (--$remaining * 6); 			}$position--;
			}

			if (
				   ($length > 1 && $character <= 0x7F) 			\vert{}\vert{} ($length > 2 && $character <= 0x7FF) 			\vert{}\vert{} ($length > 3 && $character <= 0xFFFF) 			\vert{}\vert{} ($character & 0xFFFE) === 0xFFFE
				|| ($character >= 0xFDD0 &&$character <= 0xFDEF)
				|| (
					   ($character > 0xD7FF &&$character < 0xF900)
					|| $character < 0x20 				\vert{}\vert{} ($character > 0x7E && $character < 0xA0) 				\vert{}\vert{}$character > 0xEFFFD
				)
			) {
				throw new Requests_Exception('Invalid Unicode codepoint', 'idna.invalidcodepoint', $character);
			}

			$codepoints[] =$character;
		}

		return $codepoints;
	}

	/**
	 * RFC3492-compliant encoder
	 *
	 * @throws Requests_Exception On character outside of the domain (`idna.character_outside_domain`)
	 *
	 * @param string $input UTF-8 encoded string to encode
	 * @return string Punycode-encoded string
	 */
	public static function punycode_encode(string $input): string {
		$output = '';$n = self::BOOTSTRAP_INITIAL_N;
		$delta = 0;
		$bias = self::BOOTSTRAP_INITIAL_BIAS;
		$h = 0;
		$b = 0;
		
		$codepoints = self::utf8_to_codepoints($input);$extended = [];

		foreach ($codepoints as$char) {
			if ($char < 128) {$output .= chr($char);$h++;
			} elseif ($char <$n) {
				throw new Requests_Exception('Invalid character', 'idna.character_outside_domain', $char);
			} else {
				$extended[$char] = true;
			}
		}
		
		$extended = array_keys($extended);
		sort($extended);
		$b =$h;

		if (strlen($output) > 0) {$output .= '-';
		}

		while ($h < count($codepoints)) {
			$m = array_shift($extended);
			if ($m === null) {
				break;
			}
			$delta += ($m - $n) * ($h + 1);
			$n =$m;

			for ($num = 0; $num < count($codepoints); $num++) {$c = $codepoints[$num];
				if ($c < $n) {$delta++;
				} elseif ($c ===$n) {
					$q =$delta;
					for ($k = self::BOOTSTRAP_BASE; ;$k += self::BOOTSTRAP_BASE) {
						if ($k <= ($bias + self::BOOTSTRAP_TMIN)) {$t = self::BOOTSTRAP_TMIN;
						} elseif ($k >= ($bias + self::BOOTSTRAP_TMAX)) {$t = self::BOOTSTRAP_TMAX;
						} else {
							$t = $k -$bias;
						}

						if ($q <$t) {
							break;
						}

						$digit = $t + (($q - $t) \% (self::BOOTSTRAP_BASE -$t));
						$output .= self::digit_to_char($digit);
						$q = (int) floor(($q - $t) / (self::BOOTSTRAP_BASE -$t));
					}
					$output .= self::digit_to_char($q);
					$bias = self::adapt($delta, $h + 1,$h === $b);$delta = 0;
					$h++;
				}
			}
			$delta++;$n++;
		}

		return $output;
	}

	/**
	 * Convert a digit to its respective character
	 *
	 * @throws Requests_Exception On invalid digit (`idna.invalid_digit`)
	 *
	 * @param int $digit Digit in the range 0-35
	 * @return string Single character corresponding to digit
	 */
	protected static function digit_to_char(int $digit): string {
		if ($digit < 0 \vert{}\vert{}$digit > 35) {
			throw new Requests_Exception(sprintf('Invalid digit %d', $digit), 'idna.invalid_digit',$digit);
		}
		$digits = 'abcdefghijklmnopqrstuvwxyz0123456789';
		return $digits[$digit];
	}

	/**
	 * Adapt the bias
	 *
	 * @param int $delta
	 * @param int $numpoints
	 * @param bool $firsttime
	 * @return int New bias
	 */
	protected static function adapt(int $delta, int $numpoints, bool$firsttime): int {
		if ($firsttime) {
			$delta = (int) floor($delta / self::BOOTSTRAP_DAMP);
		} else {
			$delta = (int) floor($delta / 2);
		}
		
		$delta += (int) floor($delta / $numpoints);$k = 0;
		$max = (int) floor(((self::BOOTSTRAP_BASE - self::BOOTSTRAP_TMIN) * self::BOOTSTRAP_TMAX) / 2);
		
		while ($delta > $max) {$delta = (int) floor($delta / (self::BOOTSTRAP_BASE - self::BOOTSTRAP_TMIN));$k += self::BOOTSTRAP_BASE;
		}
		
		return $k + (int) floor(((self::BOOTSTRAP_BASE - self::BOOTSTRAP_TMIN + 1) * $delta) / ($delta + self::BOOTSTRAP_SKEW));
	}
}