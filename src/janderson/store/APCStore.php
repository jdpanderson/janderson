<?php

namespace janderson\store;

/**
 * An implementation of a Key-Value store that uses APC. The apc module is required.
 */
class APCStore implements KeyValueStore, IncDecStore
{
	/**
	 * Get a value stored in APC for a given key.
	 *
	 * @param mixed $key The key, usually a string.
	 * @return mixed The value stored for the given key, or FALSE on failure.
	 */
	public function get($key)
	{
		return apc_fetch($key);
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
		return apc_store($key, $value, $expiry);
	}

	/**
	 * Delete an item from the store.
	 *
	 * @param mixed $key The key, usually a string.
	 * @return bool True if the key/value was successfully deleted from the store.
	 */
	public function delete($key)
	{
		return apc_delete($key);
	}

	/**
	 * Flush all items from the store.
	 *
	 * @return bool True if the store was successfully flushed.
	 */
	public function flush()
	{
		return apc_clear_cache('user');
	}

	/**
	 * Increment then get a value stored for a given key.
	 *
	 * @param mixed $key The key, usually a string.
	 * @return mixed The value stored for the given key, or FALSE on failure.
	 */
	public function inc($key)
	{
		return apc_inc($key);
	}

	/**
	 * Decrement then get a value stored for a given key.
	 *
	 * @param mixed $key The key, usually a string.
	 * @return mixed The value stored for the given key, or FALSE on failure.
	 */
	public function dec($key)
	{
		return apc_dec($key);
	}
}