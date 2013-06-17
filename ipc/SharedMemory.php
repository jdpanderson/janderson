<?php

namespace janderson\ipc;

use \janderson\misc\Destroyable;

/**
 * A class representing a SysV-IPC shared memory segment.
 */
class SharedMemory implements Destroyable
{
	/**
	 * Shared memory identifier, as returned by shmop_open
	 *
	 * @var resource
	 */
	private $shm;

	/**
	 * Identify if this is a newly created shared memory segment.
	 *
	 * @var bool
	 */
	private $new = FALSE;

	/**
	 * Create or use a shared memory segment with a given key and size.
	 *
	 * @param mixed $key A value to be used as the shared memory key.
	 * @param int $size The size of the shared memory segment to create, in bytes.
	 */
	public function __construct($key = NULL, $size = 1024)
	{
		$key = IPCKey::create($key);
		
		if (!$this->open($key, $size)) {
			throw new IPCException("Failed to attach shared memory");
		}
	}

	/**
	 * Open the shared memory block.
	 *
	 * @param mixed $key A value to be used as the shared memory key.
	 * @param int $size The size of the shared memory segment to create, in bytes.
	 * @return bool True if successful.
	 */
	protected function open($key, $size)
	{
		/* shmop_open can emit warnings for errors we're already catching. Catch the warnings, saving useless log entries. */
		$orig_handler = set_error_handler(function() {}, E_WARNING);

		/* Try to create */
		$this->shm = shmop_open($key, 'n', 0600, $size);

		if ($this->shm === FALSE) {
			$this->shm = shmop_open($key, 'w', 0, 0);
		} else {
			$this->new = TRUE;
		}

		/* Restore the error handler */
		set_error_handler($orig_handler, E_WARNING);

		return $this->shm !== FALSE;
	}

	/**
	 * Close the shared memory block.
	 */
	public function __destruct()
	{
		if (isset($this->shm)) {
			shmop_close($this->shm);
			$this->shm = NULL;
		}
	}

	/**
	 * Communicates whether or not this was a newly created shared memory segment.
	 *
	 * @return bool
	 */
	public function isNew()
	{
		return $this->new;
	}

	/**
	 * Write data to the shared memory segment.
	 *
	 * @param string $string The data to write to shared memory.
	 * @return bool True if the write was successful.
	 */
	public function write($string)
	{
		return isset($this->shm) ? (bool)shmop_write($this->shm, $string, 0) : FALSE;
	}


	/**
	 * Read data from the shared memory segment.
	 *
	 * @param int $bytes The number of bytes to read from the shared memory segment.
	 * @return string Returns the data in the shared memory segment, or false on failure.
	 */
	public function read($bytes)
	{
		return isset($this->shm) ? shmop_read($this->shm, 0, $bytes) : FALSE;
	}

	/**
	 * Destroy the underlying shared memory segment.
	 */
	public function destroy()
	{
		if (isset($this->shm)) {
			shmop_delete($this->shm);
			$this->shm = NULL;
		}
	}
}