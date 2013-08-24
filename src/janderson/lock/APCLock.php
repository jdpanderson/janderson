<?php
/**
 * This file defines the APCLock class.
 */

namespace janderson\lock;

/**
 * The APCLock class uses the APC extension's inc/dec methods to perform machine-local locking.
 *
 * Expected usage:
 * <code>
 * $lock = new APCLock();
 * $pid = pcntl_fork();
 * if ($lock->trylock()) {
 *     echo "I'm the process that got the lock!\n";
 *     $lock->unlock();
 * }
 * </code>
 */
class APCLock {
	/**
	 * The APC key used for storing the lock variable.
	 *
	 * @var string
	 */
	protected $key;

	/**
	 * Internal state: are we locked.
	 *
	 * @var bool
	 */
	protected $locked = FALSE;

	/**
	 * Initialize the APC key used for this lock type.
	 *
	 * @param string $key The key to use for storing the lock value. If none is provided, a random key will be generated.
	 */
	public function __construct($key = NULL) {
		/* The key is made up of enough random data to ensure that collision is unlikely. */
		if (empty($key)) {
			$this->key = sprintf(
				"%s::lock(%d, %d)",
				__CLASS__,
				mt_rand(0, mt_getrandmax()),
				microtime(TRUE) * 1000000
			);
		} else {
			$this->key = $key;
		}

		if (!$this->init()) {
			throw new LockException("unable to store initial value for lock");
		}
	}

	/**
	 * Initialize the lock value, if appropriate.
	 *
	 * If the value exists, leave it be. If not, set it to unlocked.
	 *
	 * @return bool True if the lock value could be initialized correctly, or was already initialized.
	 */
	protected function init()
	{
		if (!apc_exists($this->key)) {
			if (!$this->store((int)$this->locked)) {
				return FALSE;
			}
		}
		return TRUE;
	}

	/**
	 * Wrap around apc_inc to improve testability.
	 */
	protected function inc()
	{
		return apc_inc($this->key);
	}

	/**
	 * Wrap around apc_dec to improve testability.
	 */
	protected function dec()
	{
		return apc_dec($this->key);
	}

	/**
	 * Wrap around apc_store to improve testability.
	 */
	protected function store($value)
	{
		return apc_store($this->key, $value);
	}

	/**
	 * Unlock if we're locked. (Don't die holding the lock.)
	 */
	public function __destruct() {
		if ($this->locked) {
			$this->unlock();
		}
	}

	/**
	 * Remove the lock key from APC.
	 */
	public function destroy() {
		$this->locked = FALSE;
		apc_delete($this->key);
	}

	/**
	 * Obtain a lock, potentially blocking until one can be obtained.
	 */
	public function lock() {
		while (!$this->locked) {
			if (!$this->trylock()) {
				usleep(500);
			}
		}
	}

	/**
	 * Try to obtain a lock.
	 *
	 * @return bool Returns true if a lock was successful, false otherwise.
	 */
	public function trylock() {
		if ($this->locked) {
			return FALSE;
		}

		$value = $this->inc();

		if ($value === 1) {
			$this->locked = TRUE;
			return TRUE;
		} elseif ($value !== FALSE) {
			/* If we successfully incremented the key but didn't get the lock, we have to decrement it again. */
			while ($this->dec() === FALSE) {
				usleep(500);
			}
		}

		return FALSE;
	}

	/**
	 * Release the lock
	 *
	 * @return bool Returns true if the lock was successfully released.
	 */
	public function unlock() {
		if (!$this->locked) {
			return TRUE; /* Double-unlock. Not harmful, but means something isn't right. */
		}

		while ($this->dec() === FALSE) {
			usleep(500);
		}

		$this->locked = FALSE;
		return TRUE;
	}

	/**
	 * Check if this object is holding the lock.
	 *
	 * @return bool True if this instance is holding the lock.
	 */
	public function isLocked() {
		return $this->locked;
	}
}
