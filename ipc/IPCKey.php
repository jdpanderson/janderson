<?php

namespace janderson\ipc;

class IPCKey
{
	const MIN = 0;
	const MAX = 4294967296; /* pow(2, 32) */
	/**
	 * Get a key suitable for use in IPC functions.
	 *
	 * @param mixed $key An integer, scalar, or otherwise. Conversion to integer IPC key is attempted.
	 * @return int A 32-bit integer suitable for IPC functions.
	 */
	public static function create($key = NULL)
	{
		if ($key === NULL) {
			return mt_rand(1, self::MAX);
		} elseif (is_int($key) && $key >= self::MIN && $key <= self::MAX) {
			return $key;
		} elseif (is_scalar($key)) {
			return crc32($key);
		} else {
			return crc32((string)$key);
		}
	}
}