<?php

namespace janderson\ipc;

use \janderson\misc\Destroyable;

/**
 * Wrap the PHP semaphore
 */
class Semaphore implements Destroyable
{
	/**
	 * A reference to PHP's semaphore resource.
	 *
	 * @var resource
	 */
	private $sem;

	public function __construct($key = NULL)
	{
		$key = IPCKey::create($key);

		if (($this->sem = sem_get($key)) === FALSE) {
			throw new IPCException("Failed to get semaphore");
		}
	}

	public function acquire()
	{
		return isset($this->sem) ? sem_acquire($this->sem) : FALSE;
	}

	public function release()
	{
		return isset($this->sem) ? sem_release($this->sem) : FALSE;
	}

	public function destroy()
	{
		if (isset($this->sem)) {
			sem_remove($this->sem);
			$this->sem = NULL;
		}
	}
}