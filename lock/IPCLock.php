<?php
/**
 * This file defines the IPCLock class.
 */

namespace janderson\lock;

/**
 * The IPCLock class uses SysV-IPC to perform machine-local locking.
 *
 * Note: I'm not aware of a way to prevent collision between two distinct users of IPC. This class uses a random number to designate its IPC keys. One may not collide with anything, two or higher might; The more locks used, the higher the chances of collision. If your program requires a lot of distinct locks, this class isn't for you.
 *
 * Expected usage:
 * <code>
 * $lock = new IPCLock();
 * pcntl_fork();
 * if ($lock->trylock()) {
 *     echo "I'm the one that got the lock!\n";
 *     $lock->unlock();
 * }
 * </code>
 */
class IPCLock {
	/**
	 * The IPC key for sem and shm functions.
	 *
	 * @var int
	 */
	protected $key;

	/**
	 * The semaphore resource, as returned by sem_get.
	 *
	 * @var resource
	 */
	protected $sem;

	/**
	 * The shared memory handle, as acquired by shm_attach.
	 *
	 * @var resource
	 */
	protected $shm;

	/**
	 * 1 or 0, denoting current lock state.
	 *
	 * @var int
	 */
	protected $locked = 0;

	/**
	 * Initialize SysV shared memory and semaphores used for this lock type.
	 */
	public function __construct() {
		if (!extension_loaded("sysvsem") || !extension_loaded("sysvshm")) {
			throw new LockException("sysvsem and sysvshm modules required");
		}

		$this->key = mt_rand(1, mt_getrandmax());
		if (($this->sem = sem_get($this->key)) === FALSE) {
			throw new LockException("Failed to get semaphore");
		}
		if (($this->shm = shm_attach($this->key)) === FALSE) {
			throw new LockException("Failed to attach shared memory");
		}
		shm_put_var($this->shm, 0, $this->locked);
	}

	/**
	 * Detach from the shared memory segment.
	 */
	public function __destruct() {
		if ($this->locked) {
			$this->unlock();
		}
		shm_detach($this->shm);
	}

	/**
	 * Destroy the resources associated with this lock type.
	 *
	 * This lock type must take special care to only destroy references once after execution is complete, or other processes may encounter errors. (E.g. removal or detchment of semaphores or shared memory before other processes expect them to disappear.)
	 */
	public function destroy() {
		sem_remove($this->sem);
		shm_remove($this->shm);
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
		sem_acquire($this->sem);
		$lock = shm_get_var($this->shm, 0);

		if ($lock > 0) {
			sem_release($this->sem);
			return FALSE;
		}

		$this->locked = 1;
		shm_put_var($this->shm, 0, $this->locked);
		sem_release($this->sem);
		return TRUE;
	}

	/**
	 * Release the lock
	 *
	 * @return bool Returns true if the lock was successfully released.
	 */
	public function unlock() {
		if (!$this->locked) {
			/* Double-unlock. */
			return TRUE;
		}

		sem_acquire($this->sem);
		$this->locked = 0;
		shm_put_var($this->shm, 0, $this->locked);
		sem_release($this->sem);

		return TRUE;
	}

	/**
	 * Check if this object is holding the lock.
	 *
	 * @return bool True if this instance is holding the lock.
	 */
	public function isLocked() {
		return (bool)$this->locked;
	}
}
