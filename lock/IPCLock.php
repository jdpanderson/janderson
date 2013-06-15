<?php
/**
 * This file defines the IPCLock class.
 */

namespace janderson\lock;

use \janderson\ipc\IPCKey;
use \janderson\ipc\Semaphore;
use \janderson\ipc\SharedMemory;
use janderson\misc\Destroyable;

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
 *
 * Note: You may notice that semaphore acquires are checked, but releases are not. This is simply because a failure usually means the semaphore has gone away. The any further attempt to acquire will fail. This is an error caused by the implementing software.
 */
class IPCLock implements Destroyable
{

	/**
	 * The semaphore resource, as returned by sem_get.
	 *
	 * @var Semaphore
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
	 *
	 * Note: There are two effectively distinct constructors here:
	 * - new self(scalar); // Create a new semaphore and shared memory segment with the given key.
	 * - new self([Semaphore, SharedMemory]); // Use the given semaphore and shared memory. This must always be a pair; the reason for the pair rather than two arguments.
	 *
	 * @param mixed $key If a scalar value is given, it will be used as the IPC key. A semaphore and shared memory pair can also be passed directly: [$sem, $shm]
	 */
	public function __construct($key = NULL) {
		if (count($key) == 2) {
			$this->sem = array_shift($key);
			$this->shm = array_shift($key);
			
			if (!($this->sem instanceof Semaphore) || !($this->shm instanceof SharedMemory)) {
				//var_dump(get_parent_class($this->sem), get_parent_class($this->shm));
				throw new \InvalidArgumentException("Argument must be a Semaphore/SharedMemory pair");
			}
		} else {
			$key = IPCKey::create($key);
			$this->sem = new Semaphore($key);
			$this->shm = new SharedMemory($key);
		}

		if ($this->shm->isNew()) {
			if (!$this->sem->acquire()) {
				throw new LockException("Failed to acquire semaphore");
			}
			$this->shm->write((string)$this->locked);
			$this->sem->release();
		}
	}

	/**
	 * Detach from the shared memory segment.
	 */
	public function __destruct() {
		if ($this->locked) {
			$this->unlock();
		}
	}

	/**
	 * Destroy the resources associated with this lock type.
	 *
	 * This lock type must take special care to only destroy references once after execution is complete, or other processes may encounter errors. (E.g. removal or detchment of semaphores or shared memory before other processes expect them to disappear.)
	 */
	public function destroy() {
		$this->__destruct();

		if (isset($this->sem)) {
			$this->sem->destroy();
			$this->sem = NULL;
		}
		if (isset($this->shm)) {
			$this->shm->destroy();
			$this->shm = NULL;
		}
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
			/* Double-lock */
			return FALSE;
		}

		if (!$this->sem->acquire()) {
			return FALSE;
		}

		$locked = (int)$this->shm->read(1);

		if ($locked > 0) {
			$this->sem->release();
			return FALSE;
		}

		$this->locked = 1;
		if (!($written = $this->shm->write((string)$this->locked))) {
			$this->locked = 0;
		}
		$this->sem->release();

		return (bool)$this->locked;
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

		if (!$this->sem->acquire()) {
			return FALSE;
		}

		/* Note: failure here isn't checked, as it likely means the shm/sem has been deleted. This is likely an issue with the parent program destroying resources before it was supposed to. */
		$this->locked = 0;
		$this->shm->write((string)$this->locked);
		$this->sem->release();

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
