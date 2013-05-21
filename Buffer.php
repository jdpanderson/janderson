<?php
/**
 * This file defines the Buffer class
 */
namespace janderson\net;
 
/**
 * The Buffer class represents a string of binary data which can be manipulated in pieces.
 */
class Buffer {
	/**
	 * Binary buffer, as a string (because PHP has no concept of a binary buffer)
	 *
	 * @var string
	 */
	public $buffer = "";

	/**
	 * The current position within the buffer.
	 *
	 * @var int
	 */
	public $position = 0;

	/**
	 * The length of the buffer.
	 *
	 * @var int
	 */
	public $length = 0;

	/**
	 * Handle overloaded mbstring functions.
	 *
	 * This should be set to true if the following condition would return true:
	 * <code>extension_loaded('mbstring') && ((int)ini_get("mbstring.func_overload") & 2)</code>
	 *
	 * @var bool
	 */
	protected static $overload = FALSE;

	/**
	 * Construct a new buffer, with optional initial contents.
	 *
	 * @param string $buf The initial buffer contents.
	 * @param int $len The length of the initial buffer contents, if available.
	 */
	public function __construct($buf = NULL, $len = NULL) {
		if (isset($buf)) {
			$this->set($buf, $len);
		}
	}

	/**
	 * Append a chunk of data to the buffer.
	 *
	 * Note: Passing in the wrong length will produce incorrect return values from other methods. This class will not prevent you from shooting yourself in the foot.
	 *
	 * @param string $buf The buffer chunk to append to this buffer.
	 * @param int $len The length, if available. (Should be provided if at all possible.
	 */
	public function append($buf, $len = NULL) {
		$this->buffer .= $buf;
		$this->length += (is_int($len) && $len >= 0) ? $len : self::strlen($buf);
	}

	/**
	 * Find a substring within the buffer
	 *
	 * @param $needle The substring for which to search.
	 * @return int The position of the needle, or false on failure.
	 */
	public function find($needle) {
		return self::strpos($this->buffer, $needle);
	}

	/**
	 * Get a portion of the buffer, optionally clearing the retrieved portion.
	 *
	 * @param int $length Get $length bytes from the buffer.
	 * @param bool $clear If true, delete the returned data from the buffer.
	 */
	public function get($length = NULL, $clear = FALSE) {
		/* Clamp length between 0 and the length of the buffer. */
		$length = max(min((int)$length, $this->length), 0);

		$buf = self::substr($this->buffer, 0, $length);

		if ($clear) {
			$this->buffer = self::substr($this->buffer, $length);
		}

		return $buf;
	}

    /**
     * Set the buffer contents.
     *
     * @param string $buf
     * @param int $len
     */
    public function set($buf, $len = NULL) {
		$this->buffer = $buf;
		$this->length = (is_int($len) && $len >= 0) ? $len : self::strlen($buf);
		$this->position = 0;
	}

	/**
	 * Clear (empty) the buffer.
	 */
	public function clear() {
		$this->buffer = "";
		$this->length = 0;
		$this->position = 0;
	}

	/**
	 * Trim the buffer, discarding any portion before the current position pointer.
	 *
	 * @param int $length If positive, increments the position pointer by $length before performing the trim operation.
	 */
	public function trim($length = 0) {
		$this->position += (int)$length;

		$this->buffer = self::substr($this->buffer, $this->position);
	}

	/**
	 * Get the byte length of a string.
	 *
	 * Tries to handle mbstring.func_overload safely and transparently.
	 *
	 * @param string $str The string for which to check the length.
	 * @return int The number of bytes in the string.
	 */
	public static function strlen($str) {
		if (!self::$overload) {
			return strlen($str);
		}

		$enc = mb_internal_encoding('pass');
		$len = strlen($str);
		mb_internal_encoding($enc);
		return $len;
	}

	/**
	 * Get the position of a substring within a string.
	 *
	 * Tries to handle mbstring.func_overload safely and transparently.
	 *
	 * @param string $haystack The string in which we're searching.
	 * @param string $needle The substring for which we're searching.
	 * @param int $offset Start searching at offset.
	 * @return int
	 */
	public static function strpos($haystack, $needle, $offset = 0) {
		if (!self::$overload) {
			return strpos($haystack, $needle, $offset);
		}

		$enc = mb_internal_encoding('pass');
		$pos = strpos($haystack, $needle, $offset);
		mb_internal_encoding($enc);
		return $pos;
	}


	/**
	 * Extract a portion of a string.
	 *
	 * Tries to handle mbstring.func_overload safely and transparently.
	 *
	 * @param string $haystack The string in which we're searching.
	 * @param string $needle The substring for which we're searching.
	 * @param int $offset Start searching at offset.
	 * @return int
	 */
	public static function substr($string, $start, $length = NULL) {
		if (!self::$overload) {
			return isset($length) ? substr($string, $start, $length) : substr($string, $start);
		}

		$enc = mb_internal_encoding('pass');
		$pos = isset($length) ? substr($string, $start, $length) : substr($string, $start);
		mb_internal_encoding($enc);
		return $pos;
	}
}
