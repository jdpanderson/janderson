<?php

namespace janderson\net\socket\server;

use \janderson\net\socket\Socket;

class ForkingServer extends Server {
	const KEY_LOCK = "ForkingServer::socketlock";
	const KEY_SERIAL = "ForkingServer::socketserial";

	protected $locked = FALSE;
	protected $serial;

	protected function lock() {
		$value = apc_inc(self::KEY_LOCK);

		if ($value === 1) {
			$this->locked = TRUE;
			return TRUE;
		} elseif ($value !== FALSE) {
			while (!apc_dec(self::KEY_LOCK)) {
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

		while (!apc_dec(self::KEY_LOCK)) {
			$this->log('Warning: Unlocking failed. Will retry.');
			sleep(1);
		}

		$this->locked = FALSE;
		return TRUE;
	}

	protected function select() {
		$this->lock();
		$this->serial = apc_fetch(self::KEY_SERIAL);
		$this->unlock();

		return parent::select();
	}

	protected function accept() {
		$return = array(0, array(), array(), array());

		if (is_int($this->serial)) {
			$this->lock();
			if ($this->serial ===  apc_fetch(self::KEY_SERIAL)) {
				while (!apc_inc(self::KEY_SERIAL)) {
					$this->log('Warning: Failed to increment serial. Will retry.');
					sleep(1);
				}
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

		$parent = FALSE;
		apc_store(self::KEY_LOCK, 0);
		apc_store(self::KEY_SERIAL, 1);
		for ($i = 0; $i < 2; $i++) {
			switch (pcntl_fork()) {
				case -1: /* Error */
					$this->log("Fork failure.");
					return;

				case 0:  /* Child */
					echo "Child $i running...\n";
					parent::run();
					break;

				default: /* Parent */
					$parent = TRUE;
					break;
			}
		}

		if ($parent) {
			echo "Parent going to sleep as a proof of concept.\n";
			sleep(1000);
		}
	}
}
