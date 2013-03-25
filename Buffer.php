<?php
/**
 * This file defines the Buffer class
 */
namespace janderson\net;
 
/**
 * The Buffer class represents a string of binary data which can be sent in pieces.
 */
class Buffer {
	public $buffer = "";
	public $position = 0;
	public $length = 0;

	protected static $overload;

	public function __construct($buf = NULL, $len = NULL) {
		if (isset($buf)) $this->set($buf, $len);
	}

	/**
	 * Append a chunk of data to the buffer.
	 *
	 * @param string $buf The buffer chunk to append to this buffer.
	 * @param int $len The length, if available. (Should be provided if at all possible.)
	 */
	public function append($buf, $len = NULL) {
		$this->buffer .= $buf;
		$this->length += (is_int($len) && $len >= 0) ? $len : self::strlen($buf);
	}

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
	 * Get the byte length of a string. Tries to handle mbstring.func_overload safely and transparently.
	 *
	 * @param string $str The string for which to check the length.
	 * @return int The number of bytes in the string.
	 */
	public static function strlen($str) {
		if (!isset(self::$overload)) {
			self::$overload = function_exists('mb_internal_encoding') && ini_get("mbstring.func_overload") != "0";
		}

		if (!self::$overload) {
			return strlen($str);
		}

		$enc = mb_internal_encoding('pass');
		$len = strlen($str);
		mb_internal_encoding($enc);
		return $len;
	}

	public static function strpos($str, $start = 0, $length = NULL) {
		if (!isset(self::$overload)) {
			self::$overload = function_exists('mb_internal_encoding') && ini_get("mbstring.func_overload") != "0";
		}

		if (!self::$overload) {
			return isset($length) ? strlen($str, $start, $length) : strlen($str, $start);
		}

		$enc = mb_internal_encoding('pass');
		$str = isset($length) ? strlen($str, $start, $length) : strlen($str, $start);
		mb_internal_encoding($enc);
		return $str;
	}
}
