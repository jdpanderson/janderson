<?php

namespace janderson\store;

/**
 * A key-value store implemented on top of SysV-IPC shared memory segments. The sysvshm is required.
 *
 * This class works by becoming a sort of hashtable on top of shared memory.
 *
 * Please note that several processes can 
 */
class IPCStore implements KeyValueStore
{
	private $shm;

	/**
	 * Create a new IPC SysV-SHM-backed key value store.
	 *
	 * @param int $size The size of the store, in bytes.
	 * @param int $key The SHM key, or NULL for a random key (default).
	 *
	 */
	public function __construct($size = 65536, $key = NULL)
	{
		if ($key === NULL) {
			$key = mt_rand(0, 4294967296);
		} elseif (!is_int($key)) {
			$key = $this->getKey($key);
		}

		$this->shm = @shm_attach($key, $size, 0600);

		if (!$this->shm) {
			throw new StoreException("failed to attach shared memory");
		}
	}

	/**
	 * Destroy any resources associated with this store. (SysV-SHM)
	 *
	 * Note: This should only be done if you're sure this is the last user of this store.
	 */
	public function destroy()
	{
		return @shm_remove($this->shm);
	}

	/**
	 * Get an SHM variable key. (A 32-bit integer.)
	 */
	private function getKey($key)
	{
		if (is_scalar($key)) {
			return crc32($key);
		} else {
			throw new StoreException("invalid key (non-scalar)");
		}
	}
	/**
	 * Get a value stored in shared memory for a given key.
	 *
	 * @param mixed $key The key, usually a string.
	 * @return mixed The value stored for the given key, or FALSE on failure.
	 */
	public function get($key)
	{
		$ipckey = $this->getKey($key);

		$bucket = @shm_get_var($this->shm, $ipckey);

		if (!$bucket) {
			return FALSE;
		}

		return isset($bucket[$key]) ? $bucket[$key] : FALSE;
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
		$ipckey = $this->getKey($key);

		$bucket = @shm_get_var($this->shm, $ipckey);

		if (!$bucket) {
			$bucket = array();
		}
		$bucket[$key] = $value;

		return @shm_put_var($this->shm, $ipckey, $bucket);
	}

	/**
	 * Delete an item from the store.
	 *
	 * @param mixed $key The key, usually a string.
	 * @return bool True if the key/value was successfully deleted from the store.
	 */
	public function delete($key)
	{
		$ipckey = $this->getKey($key);

		$bucket = @shm_get_var($this->shm, $ipckey);

		if (!$bucket) {
			return FALSE;
		}

		if (isset($bucket[$key])) {
			unset($bucket[$key]);
		}

		if (empty($bucket)) {
			return @shm_remove_var($this->shm, $ipckey);
		} else {
			return @shm_put_var($this->shm, $ipckey, $bucket);
		}
	}

	/**
	 * Flush all items from the store. This is not possible for SHM without removing and re-creating the shared memory, so false is always returned.
	 *
	 * @return bool True if the store was successfully flushed.
	 */
	public function flush()
	{
		return FALSE;
	}
}