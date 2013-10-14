<?php

namespace janderson\tests\assistant;

class TestAssistant {
	protected $assistant;
	protected $pipe;

	public function __construct() {
		$this->assistant = proc_open(
			"php " . __DIR__ . "/assistant.php",
			array(0 => array("pipe", "r")),
			$pipes
		);

		if (!$this->assistant) {
			throw new \Exception("Process creation failed");
		}

		$this->pipe = $pipes[0];
	}

	public function __asdconstuct() {
		



	}

	public function __destruct() {
		return;
		$this->command("exit(0);");
		usleep(500);
		if ($this->assistant) {
			proc_terminate($this->assistant);
		}
	}

	public function command($command) {
		if ($this->pipe) {
			fwrite($this->pipe, $command);
		}
	}
}