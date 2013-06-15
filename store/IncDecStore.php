<?php

namespace janderson\store;

/**
 * An interface for store that can increment and decrement the value in a key.
 *
 * It is expected that the underlying store can perform this operation atomically, or at least practically so. If the store can't do it atomically, one shouldn't emulate this interface, but rather just use normal get/set methods.
 */
interface IncDecStore
{
	/**
	 * Increment then get a value stored for a given key.
	 *
	 * @param mixed $key The key, usually a string.
	 * @return mixed The value stored for the given key, or FALSE on failure.
	 */
	public function inc($key);

	/**
	 * Decrement then get a value stored for a given key.
	 *
	 * @param mixed $key The key, usually a string.
	 * @return mixed The value stored for the given key, or FALSE on failure.
	 */
	public function dec($key);
}