<?php

namespace janderson\misc;

class UUID {
	const NAMESPACE_DNS = "6ba7b810-9dad-11d1-80b4-00c04fd430c8"; /* e.g. www.example.com */
	const NAMESPACE_URL = "6ba7b811-9dad-11d1-80b4-00c04fd430c8"; /* e.g. http://www.example.com/index.html */
	const NAMESPACE_OID = "6ba7b812-9dad-11d1-80b4-00c04fd430c8"; /* ISO Object ID */
	const NAMESPACE_DN  = "6ba7b814-9dad-11d1-80b4-00c04fd430c8"; /* X.500 Distinguished name */

	/**
 	 * UUID Version 3: Generate an MD5-hash based UUID.
	 *
	 * @param string $ns The UUID's namespace UUID.
	 * @param string $name The UUID's identifier, e.g. www.example.com
	 * @return string The requested UUID
	 */
	public static function v3($ns, $name) {
		return self::hash($ns, $name, 'md5', 3);
	}

	/**
 	 * UUID Version 4: random data
	 *
	 * @return string The requested UUID
	 */
	public static function v4() {
		return self::getString32(
			mt_rand(0, 0xffffffff),
			(mt_rand(0, 0xffffffff) & 0xffff0fff) | 0x4000,
			(mt_rand(0, 0xffffffff) & 0xbfffffff) | 0x80000000,
			mt_rand(0, 0xffffffff)
		);
	}

	/**
 	 * UUID Version 5: Generate a SHA1-hash based UUID.
	 *
	 * @param string $ns The UUID's namespace UUID.
	 * @param string $name The UUID's identifier, e.g. www.example.com
	 * @return string The requested UUID
	 */
	public static function v5($ns, $name) {
		return self::hash($ns, $name, 'sha1', 5);
	}

	/**
	 * Get a UUID-formatted string from 4 32-bit integers.
	 *
	 * @param int $i1 The first 32-bits
	 * @param int $i2 The second 32-bits
	 * @param int $i3 The third 32-bits
	 * @param int $i4 The fourth 32-bits
	 * @return string The UUID generated from the 3 integers.
	 */
	public static function getString32($i1, $i2, $i3, $i4) {
		return sprintf("%08x-%04x-%04x-%04x-%04x%08x", $i1, ($i2 >> 16) & 0xffff, $i2 & 0xffff, ($i3 >> 16) & 0xffff, $i3 & 0xffff, $i4);
	}

	/**
	 * Shortcut for generating v3 and v4 UUIDs.
	 */
	protected static function hash($ns, $name, $algo = 'sha1', $version = 5) {
		$ns = str_replace(array('-', '{', '}'), '', $ns);

		/* Parse the hex characters */
		$ns = sscanf($ns, "%2x%2x%2x%2x%2x%2x%2x%2x%2x%2x%2x%2x%2x%2x%2x%2x");
		if (count($ns) != 16) return FALSE;
		$ns = implode("", array_map("chr", $ns));

		/* Hash the binary namespace with the name */
		$hash = hash($algo, $ns . $name, TRUE);

		/* Set the 4 version bits. */
		$values = unpack("N4", $hash);
		$values[2] = ($values[2] & 0xffff0fff) | ($version << 12); /* This digit will represent the version. */
		$values[3] = ($values[3] & 0xbfffffff) | 0x80000000; /* Two most significant bits of this digit must be 1 and 0 */

		return self::getString32($values[1], $values[2], $values[3], $values[4]);
	}
}
