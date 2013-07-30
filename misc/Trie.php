<?php

namespace janderson\misc;

class Trie {
	/**
 	 * The key to use in the dictionary to store the value for the node.
	 */
	const KEY_VALUE = 'val';

	protected $root = array();

	/**
	 * Add a key to the Trie.
	 *
	 * @param string $string The string key to add to the trie.
	 * @param mixed $value The value to be stored associated with the given key.
	 */
	public function add($string, $value = TRUE) {
		$node = &$this->root;
		$len = strlen($string);

		for ($i = 0; $i < $len; $i++) {
			$char = $string[$i];
			if (!isset($node[$char])) {
				$node[$char] = array();
			}
			$node = &$node[$char];
		}

		$node[self::KEY_VALUE] = $value;
	}

	/**
 	 * Gets the value stored for a key in the Trie.
	 *
	 * @param string $string The string key for which a value will be retrieved.
	 * @param [string, mixed] &$longest If provided, is populated with the longest matching key and value, or will remain unchanged if no value was found.
	 * @return mixed The value stored in the Trie for the given key. (True is stored by default if no value is given, so if existence-only tests are required, checking for true/false is sufficient.)
	 */
	public function get($string, &$longest = NULL) {
		$node = &$this->root;
		$len = strlen($string);

		for ($i = 0; $i < $len; $i++) {
			$char = $string[$i];

			if (!isset($node[$char])) {
				return FALSE;
			}

			$node = &$node[$char];

			if (isset($node[self::KEY_VALUE])) {
				$longest = array(substr($string, 0, $i + 1), $node[self::KEY_VALUE]);
			}
		}

		return isset($node[self::KEY_VALUE]) ? $node[self::KEY_VALUE] : FALSE;
	}

	/**
	 * Gets the value for the longest prefix matching the given string.
	 *
	 * @param string $string The string key to search for.
	 * @return [string, mixed] The longest matching key and value, or false if not found.
	 */
	public function prefix($string, &$key = NULL)
	{
		$longest = FALSE;
		$this->get($string, $longest);

		if ($longest) {
			$key = $longest[0];
			return $longest[1];
		}

		return FALSE;
	}
}
