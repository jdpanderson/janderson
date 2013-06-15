<?php

namespace janderson\socket\server;

use \janderson\misc\Destroyable;
use \janderson\socket\Socket;
use \janderson\lock\Lock;
use \janderson\lock\IPCLock;
use \janderson\store\KeyValueStore;
use \janderson\store\IPCStore;

/**
 * Class ForkingServer
 *
 * @package janderson\socket\server
 */
class ForkingServer extends Server {
	/**
	 * The following pieces of shared data (IPC) are needed:
	 *  - Signal from parent to child to stop/exit.
	 *  - Signal from child to parent to notify status.
	 *
	 * To keep children from all trying to accept a single incoming connection, children must communicate somehow...
	 *  - Connection # so that only child attempts to accepts the new connection, plus lock.
	 *
	 * Parent communication to children can be simple: false to stop.
	 * Child communication requires a simple protocol:
	 *  - Key is an array.
	 *  - Write access to data requires a lock (lock before write, unlock after)
	 *  - Child appends its ID, timestamp, and # of active sockets as an array of ints.
	 *  - Parent will pull child data off like a stack.
	 *
	 * APC does this trivially, but this could be replaced by other IPC mechanisms.
	 */
	const KEY_STATUS_PARENT = "ForkingServer::Parent";
	const KEY_STATUS_CHILD = "ForkingServer::Children";
	const KEY_SERIAL = "ForkingServer::ConnNo";

	/**
	 * Lock before reading/writing shared data to/from APC shared memory.
	 *
	 * @var \janderson\lock\Lock;
	 */
    protected $lock;

	/**
	 * Keep track of the incoming connection attempt number, so that only one child performs an accept call.
	 *
	 * @var int
	 */
	protected $serial;

	/**
	 * The serial number of the child, or 0/NULL for the parent process.
	 * @var int
	 */
	protected $child_id;

	/**
	 * An array of children that are expected to be alive.
	 *
	 * @var int[]
	 */
	protected $processes = array();

	/**
	 * List of timestamps we've last heard from our children.
	 *
	 * @var int[]
	 */
	protected $processTimestamps = array();

	public function __construct(Socket $socket, $handlerClass, Lock $lock = NULL, KeyValueStore $store = NULL) {
		$this->lock = isset($lock) ? $lock : new IPCLock(); /* Allow any lock, but default to IPCLock */
		$this->store = isset($store) ? $store : new IPCStore(); /* Allow any store, but default to IPCStore */

		$this->store->set(self::KEY_STATUS_CHILD, array(), 86400);
		$this->store->set(self::KEY_SERIAL, 0, 86400);

		parent::__construct($socket, $handlerClass);
	}

	public function __destruct() {
		/* If we're the parent, do some cleanup. */
		if (!$this->child_id) {
			$this->store->delete(self::KEY_STATUS_CHILD);
			$this->store->delete(self::KEY_SERIAL);

			if ($this->store instanceof Destroyable) {
				$this->store->destroy();
			}

			if ($this->lock instanceof Destroyable) {
				$this->lock->destroy();
			}
		}
	}

	private $statusTs;

	/**
	 * Communicate the child status to the parent.
	 *
	 * @param bool $checkTs If false, set the status without bothering to check if we've set it recently.
	 */
	protected function setChildStatus($checkTs = TRUE) {
		$time = time();

		if ($checkTs && $this->statusTs && $this->statusTs == $time) {
			return;
		}

		$success = FALSE;
		if (($child_status = $this->store->get(self::KEY_STATUS_CHILD)) !== FALSE) {
			/* For the child, we set the status in the array. For the parent, we clear it all. */
			if ($this->child_id) {
				$child_status[$this->child_id] = array(time(), count($this->sockets));
			} else {
				$child_status = array();
			}
			if ($this->store->set(self::KEY_STATUS_CHILD, $child_status, 3600)) {
				$this->statusTs = $time;
				$success = TRUE;
			}
		}

		if (!$success) {
			// XXX FIXME: Add a log notice/warning here, noting that we couldn't communicate child status.
		}
	}

	/**
	 * Get the child status array
	 *
	 * @return mixed[]
	 */
	protected function getChildStatus() {
		return $this->store->get(self::KEY_STATUS_CHILD);
	}

	protected function getSerial() {
		$this->serial = $this->store->get(self::KEY_SERIAL);
	}

	protected function incrementSerial() {
		$this->lock->lock();
		$serial = $this->store->get(self::KEY_SERIAL);
		$result = $this->store->set(self::KEY_SERIAL, ++$serial);
		$this->lock->unlock();

		return $result;
	}

	protected function select() {
		$this->lock->lock();
		$this->getSerial();
		$this->setChildStatus();
		$this->lock->unlock();

		return parent::select();
	}

	protected function accept() {
		$return = NULL;

		if (is_int($this->serial)) {
			$this->lock->lock();

			$serial = $this->getSerial();
			if ($this->serial === $serial) {
				while ($this->incrementSerial() === FALSE) {
					$this->log('Warning: Failed to increment serial. Will retry.');
					usleep(1000);
				}
				$this->log("Child {$this->child_id} accepting.");
				$return = parent::accept();
				$this->setChildStatus(FALSE);
			}

			$this->lock->unlock();
		}

		return $return;
	}

	public function run($processes = 10) {
		/* Set bounds on # of processes to 1 <= $processes <= 1024 */
		$processes = min(1024, max(1, $processes));
		$child_id = 0;
		echo "Running $processes processes\n";

		/**
		 * Start managing processes!
		 */
		while (TRUE) {
			$this->lock->lock();
			echo "Got status from children: " . print_r($this->getChildStatus(), TRUE) . "\n";
			$this->setChildStatus();
			$this->lock->unlock();

			if (count($this->processes) < $processes) {
				$child_id++;

				switch ($pid = pcntl_fork()) {
					case -1: /* Error */
						$this->log("Fork failure.");
						return;

					case 0:  /* Child */
						$this->child_id = $child_id;
						unset($this->processes); /* Not the managing parent anymore. */
						//$this->log("Child $this->child_id running.");
						echo "Child $this->child_id running.";
						parent::run();
						exit(0);

					default: /* Parent. Do nothing yet. */
						$this->processes[$child_id] = $pid;
						break;
				}
			} else {
				/* Wait a few ms, and do it all over again. XXX fix value */
				usleep(1000000);
			}
		}

		if ($this->child_id === NULL) {
			echo "Parent going to sleep as a proof of concept.\n";
			sleep(1000);
		} else {
			echo "done...\n";
		}
	}
}
