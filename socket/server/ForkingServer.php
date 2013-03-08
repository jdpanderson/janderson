<?php

namespace janderson\net\socket\server;

use \janderson\net\socket\Socket;

class ForkingServer extends Server {
	protected $locked = FALSE;

	protected function lock() {
		$value = apc_inc("FS::lock");

		if ($value === 1) {
			$this->locked = TRUE;
			return TRUE;
		} elseif ($value !== FALSE) {
			apc_dec("FS::lock");
		}

		return FALSE;
	}

	protected function unlock() {
		if (!$this->locked) {
			return FALSE;
		}

		$value = apc_dec("FS::lock");
		
		if ($value === FALSE) {
			$this->log("Massive problem: unlocking failed. We're probably going to zombify! Bailing out instead!");
			exit(0);
		}

		$this->locked = FALSE;
		return TRUE;
	}

	public function run() {
		if (!function_exists('pcntl_fork') || !function_exists('apc_inc')) {
			$this->log("Warning: fork not available. Running with only one process.");
			parent::run();
			return;
		}

		$parent = FALSE;
		apc_store("FS::lock", 0);
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
