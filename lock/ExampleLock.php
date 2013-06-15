<?php
/**
 * This file defines the Lock interface.
 */

namespace janderson\lock;

/**
 * A useless example lock
 */
class ExampleLock implements Lock {
	/**
	 * Current lock state.
	 *
	 * @var bool
	 */
	private $locked = FALSE;
	/**
	 * Attempt to lock, blocking until the lock succeeds.
	 */
	public function lock()
	{
		while ($this->locked) {
			// usleep(500); // Would usually do something like this... But be a good citizen for the example.
			throw new LockException("Locking here would produce a deadlock.");
		}
		$this->locked = TRUE;
	}

	/**
	 * Attempt to lock, returning immediately with the lock status.
	 *
	 * @return bool True if locked successfully, false otherwise.
	 */
	public function trylock()
	{
		if ($this->locked) {
			return FALSE;
		}
		$this->locked = TRUE;
		return TRUE;
	}

	/**
	 * Release the lock
	 *
	 * @return bool True if unlocked, false otherwise.
	 */
	public function unlock()
	{
		if ($this->locked) {
			$this->locked = FALSE;
		} /* else double-unlock */
		return TRUE;
	}

	/**
	 * Check if this object is holding the lock.
	 *
	 * @return bool True if this instance is holding the lock.
	 */
	public function isLocked()
	{
		return $this->locked;
	}

	/**
	 * Destroy any resources that may be left behind by the lock.
	 *
	 * Note: as a lock implementation may affect other processes or even servers on a network, a lock should ensure that this is the *final* user of the lock before destroying itself.
	 *
	 * @return bool True if resources were successfully destroyed.
	 */
	public function destroy()
	{
		$this->locked = FALSE;
		return TRUE;
	}
}
