<?php

namespace janderson\socket\server;

use janderson\misc\Destroyable;
use janderson\socket\Socket;
use janderson\lock\Lock;
use janderson\lock\IPCLock;
use janderson\store\KeyValueStore;
use janderson\store\IPCStore;
use janderson\ipc\MessageQueue;

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
	 * The lock holder gets to select on the listen socket.
	 *
	 * @var janderson\lock\Lock;
	 */
    protected $lock;

    /**
     * Parent sets status into shared storage for all children to read.
     *
     * @var janderson\store\KeyValueStore
     */
    protected $store;

    /**
     * Children dump their status into the queue to be read by the parent.
     *
     * @var janderson\ipc\MessageQueue
     */
    protected $queue;

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

	public function __construct(Socket $socket, $handler) {
		$this->lock = new IPCLock();
		$this->store = new IPCStore();
		$this->queue = new MessageQueue();

		parent::__construct($socket, $handler);
	}

	public function __destruct() {
		/* If we're the parent, do some cleanup. */
		if (!$this->child_id) {
			$this->store->delete(self::KEY_SERIAL);

			foreach (array($this->store, $this->lock, $this->queue) as $destroyable) {
				if ($destroyable instanceof Destroyable) {
					$destroyable->destroy();
				}
			}
		}
	}

	/**
	 * The last time we've sent a status update to the parent. Unix timestamp.
	 *
	 * @var int
	 */
	private $statusTs = 0;

	/**
	 * Communicate the child status to the parent.
	 *
	 * @param bool $checkTs If false, set the status without bothering to check if we've set it recently.
	 */
	protected function setChildStatus($checkTs = TRUE) {
		$time = time();

		if ($checkTs && ($time - $this->statusTs) < 5) {
			return;
		}

		$status = array($this->child_id, $time, count($this->children)); /* FIXME: $this->children is a property of the parent. Consider making this getChildCount or something. */
		$success = $this->queue->send($status);
		$this->statusTs = $time;

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
		$status = array();
		while ($s = $this->queue->receive()) {
			$status[] = $s;
		}
		return $status;
	}

	protected function select($listen = TRUE) {
		$listen = $listen ? $this->lock->trylock() : FALSE;

		$return = parent::select($listen);

		if ($listen && !in_array($this->socket, $return[1], TRUE)) {
			$this->lock->unlock();
		}

		return $return;
	}

	protected function accept() {
		$return = parent::accept();
		$this->lock->unlock();
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
			//echo "Got status from children: " . json_encode($this->getChildStatus()) . "\n";
			//$this->setChildStatus();

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

	protected function close(Socket &$socket) {
		$resourceId = $socket->getResourceId();

		//echo "Child {$this->child_id} Closing socket with resourceId $resourceId\n";

		parent::close($socket);
	}
}
