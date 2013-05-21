<?php
/**
 * This file defines the Lock interface.
 */

namespace janderson\net\lock;

/**
 * Interface for a generic lock
 *
 * Keep in mind that different locks will have different behaviors. A lock could:
 *  - Lock only within a process or a thread
 *  - Lock locally across a computer (e.g. IPC or APC)
 *  - Lock locally inside a network
 *  - Lock globally across the entire Internet. (I'm not going to implement that, but you go ahead. :)
 */
interface Lock {
	/**
	 * Attempt to lock, blocking until the lock succeeds.
	 */
	public function lock();

	/**
	 * Attempt to lock, returning immediately with the lock status.
	 *
	 * @return bool True if locked successfully, false otherwise.
	 */
	public function trylock();

	/**
	 * Release the lock
	 *
	 * @return bool True if unlocked, false otherwise.
	 */
	public function unlock();

	/**
	 * Check if this object is holding the lock.
	 *
	 * @return bool True if this instance is holding the lock.
	 */
	public function isLocked();

	/**
	 * Destroy any resources that may be left behind by the lock.
	 *
	 * Note: as a lock implementation may affect other processes or even servers on a network, a lock should ensure that this is the *final* user of the lock before destroying itself.
	 *
	 * @return bool True if resources were successfully destroyed.
	 */
	public function destroy();
}
