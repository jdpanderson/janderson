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
			foreach (array($this->store, $this->lock, $this->queue) as $destroyable) {
				if ($destroyable instanceof Destroyable) {
					$destroyable->destroy();
				}
			}
		} else {
			/* If we're the child destructing, try letting the parent know we're gone. */
			if ($this->queue) {
				$this->queue->send(array($this->child_id, FALSE, FALSE));
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
		if (!$this->child_id) {
			echo "Warning: non-child tried to set child status.\n";
			return;
		}

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
			list($child_id, $timestamp, $count) = $s;

			if ($timestamp === FALSE) {
				echo "Detected destructing process $child_id\n";
				unset($this->processes[$child_id], $this->processTimestamps[$child_id]);
			} else {
				$this->processTimestamps[$child_id] = $timestamp;
			}
		}
		return $this->processTimestamps;
	}

	protected function select($listen = TRUE) {
		$this->setChildStatus();

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
						$queue = $this->queue;
						register_shutdown_function(function() use ($child_id, $queue) { echo "$child_id shutdown\n"; $queue->send(array($child_id, FALSE, FALSE)); });
						parent::run();
						exit(0);

					default: /* Parent. Do nothing yet. */
						$this->processes[$child_id] = $pid;
						break;
				}
			} else {
				/* Wait a few ms, and do it all over again. XXX fix value */
				$time = time();
				foreach ($this->getChildStatus() as $child_id => $timestamp) {
					if (($time - $timestamp) > 10) {
						echo "Child $child_id timed out.\n";
						unset($this->processes[$child_id], $this->processTimestamps[$child_id]);
					}
				}
				usleep(1000000);
			}
		}
	}

	protected function close(Socket &$socket) {
		$resourceId = $socket->getResourceId();

		//echo "Child {$this->child_id} Closing socket with resourceId $resourceId\n";

		parent::close($socket);
	}
}
