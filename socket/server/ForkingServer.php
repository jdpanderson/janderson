<?php

namespace janderson\net\socket\server;

use \janderson\net\socket\Socket;
use \janderson\net\lock\APCLock;
use \janderson\net\lock\IPCLock;

class ForkingServer extends Server {
	const KEY_SERIAL = "ForkingServer::socketserial";

	protected $locked = FALSE;

	/**
	 * In future, should be used to signal all processes to stop.
	 *
	 * @var \janderson\net\lock\Lock;
	 */
	protected $kill;
    protected $lock;
	protected $serial;
	protected $child_id;

	public function __construct(Socket $socket, $handlerClass) {
		foreach (array('\\janderson\\net\\lock\\IPCLock', '\\janderson\\net\\lock\\APCLock') as $lockImpl) {
			try {
				$this->lock = new $lockImpl();
				$this->kill = new $lockImpl();
				break;
			} catch (LockException $e) {}
		}

		if (!isset($this->lock)) {
			throw new Exception("Unable to create lock.");
		}

		parent::__construct($socket, $handlerClass);
	}

	protected function select() {
		$this->lock->lock();
		$this->serial = apc_fetch(self::KEY_SERIAL);
		$this->lock->unlock();

		return parent::select();
	}

	protected function accept() {
		$return = array(0, array(), array(), array());

		if (is_int($this->serial)) {
			$this->lock->lock();

			if ($this->serial ===  apc_fetch(self::KEY_SERIAL)) {
				while (apc_inc(self::KEY_SERIAL) === FALSE) {
					$this->log('Warning: Failed to increment serial. Will retry.');
					sleep(1);
				}
				$this->log("Child {$this->child_id} accepting.");
				$return = parent::accept();
			}

			$this->lock->unlock();
		}

		return $return;
	}

	public function run($processes = 10) {
		if (!function_exists('pcntl_fork') || !function_exists('apc_inc')) {
			$this->log("Warning: fork not available. Running with only one process.");
			parent::run();
			return;
		}

		/* Set bounds on # of processes to 1 <= $processes <= 1024 */
		$processes = min(1024, max(1, $processes));

		apc_store(self::KEY_SERIAL, 1);

		for ($i = 0; $i < $processes; $i++) {
			switch (pcntl_fork()) {
				case -1: /* Error */
					$this->log("Fork failure.");
					return;

				case 0:  /* Child */
					$this->child_id = $i;
					$this->log("Child $i running.");
					parent::run();
					break;

				default: /* Parent. Do nothing yet. */
					break;
			}
		}

		if ($this->child_id === NULL) {
			echo "Parent going to sleep as a proof of concept.\n";
			sleep(1000);
		}
	}
}
