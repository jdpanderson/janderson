<?php

namespace janderson\net\socket\server;

use \janderson\net\socket\Socket;

class ForkingServer extends Server {
	const KEY_LOCK = "ForkingServer::socketlock";
	const KEY_SERIAL = "ForkingServer::socketserial";

	protected $locked = FALSE;
	protected $serial;
	protected $child_id;

	protected function lock() {
		$value = apc_inc(self::KEY_LOCK);

		if ($value === 1) {
			$this->locked = TRUE;
			return TRUE;
		} elseif ($value !== FALSE) {
			while (apc_dec(self::KEY_LOCK) === FALSE) {
				$this->log('Warning: Cleaning up after lock failure failed. Will retry.');
				sleep(1);
			}
		}

		return FALSE;
	}

	protected function unlock() {
		if (!$this->locked) {
			return FALSE;
		}

		while (apc_dec(self::KEY_LOCK) === FALSE) {
			$this->log('Warning: Unlocking failed. Will retry.');
			sleep(1);
		}

		$this->locked = FALSE;
		return TRUE;
	}

	protected function select() {
		while (!$this->lock()) {
			usleep(1000);
		}
		$this->serial = apc_fetch(self::KEY_SERIAL);
		$this->unlock();

		return parent::select();
	}

	protected function accept() {
		$return = array(0, array(), array(), array());

		if (is_int($this->serial)) {
			if (!$this->lock()) {
				return $return;
			}

			if ($this->serial ===  apc_fetch(self::KEY_SERIAL)) {
				while (apc_inc(self::KEY_SERIAL) === FALSE) {
					$this->log('Warning: Failed to increment serial. Will retry.');
					sleep(1);
				}
				$this->log("Child {$this->child_id} accepting.");
				$return = parent::accept();
			}
			$this->unlock();
		}

		return $return;
	}

	public function run() {
		if (!function_exists('pcntl_fork') || !function_exists('apc_inc')) {
			$this->log("Warning: fork not available. Running with only one process.");
			parent::run();
			return;
		}

		apc_store(self::KEY_LOCK, 0);
		apc_store(self::KEY_SERIAL, 1);
		for ($i = 0; $i < 4; $i++) {
			switch (pcntl_fork()) {
				case -1: /* Error */
					$this->log("Fork failure.");
					return;

				case 0:  /* Child */
					$this->child_id = $i;
					echo "Child $i running...\n";
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
