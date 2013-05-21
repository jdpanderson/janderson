<?php

namespace janderson\store;

/**
 * An implementation of a Key-Value store that uses a simple array. Basically just for testing.
 */
class ArrayStore implements KeyValueStore
{
	/**
	 * The storage mechanism.
	 *
	 * @var array
	 */
	private $array = array();

	/**
	 * Get a value stored for a given key.
	 *
	 * @param mixed $key The key, usually a string.
	 * @return mixed The value stored for the given key, or FALSE on failure.
	 */
	public function get($key)
	{
		return isset($this->array[$key]) ? $this->array[$key] : FALSE;
	}

	/**
	 * Set a value in the store for a given key.
	 *
	 * @param mixed $key The key, usually a string.
	 * @param mixed $value The value to be stored. Must be serializable. (Can't be a resource.)
	 * @param int $expiry The time to list of the key/value in the store. This is optional, and may be interpreted differently for different underlying stores.
	 * @return bool True if the value was successfully stored.
	 */
	public function set($key, $value, $expiry = NULL)
	{
		$this->array[$key] = $value;
		return TRUE;
	}

	/**
	 * Delete an item from the store.
	 *
	 * @param mixed $key The key, usually a string.
	 * @return bool True if the key/value was successfully deleted from the store.
	 */
	public function delete($key)
	{
		if (isset($this->array[$key])) {
			unset($this->array[$key]);
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Flush all items from the store.
	 *
	 * @return bool True if the store was successfully flushed.
	 */
	public function flush()
	{
		$this->array = array();
		return TRUE;
	}
}