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
	 */
	public function __construct() {
		if (!extension_loaded("apc")) {
			throw new LockException("apc extension required but not found");
		}

		/* The key is made up of enough random data to ensure that collision is unlikely. */
		$this->key = sprintf(
			"%s::lock(%d, %d)",
			__CLASS__,
			mt_rand(0, mt_getrandmax()),
			microtime(TRUE) * 1000000
		);

		if (!apc_store($this->key, 0)) {
			throw new LockException("unable to store initial value for lock");
		}
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

		$value = apc_inc($this->key);

		if ($value === 1) {
			$this->locked = TRUE;
			return TRUE;
		} elseif ($value !== FALSE) {
			while (apc_dec($this->key) === FALSE) {
				$this->log('Warning: Cleaning up after lock failure failed. Will retry.');
				sleep(1);
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

		while (apc_dec($this->key) === FALSE) {
			$this->log('Warning: Unlocking failed. Will retry.');
			sleep(1);
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
