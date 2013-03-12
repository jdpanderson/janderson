<?php

namespace janderson\net\socket\server;

use \janderson\net\socket\Socket;

class Server {
	protected $port;
	protected $socket;
	protected $stop = FALSE;

	protected $readers = array();
	protected $writers = array();

	protected function log($message) {
		error_log($message);
	}

	/**
	 * @param int $port
	 * @throws Exception An exception will be thrown by the underlying socket implementation if a listen socket cannot be created.
	 */
	public function __construct(Socket $socket, Dispatchable $dispatcher) {
		if (!($socket instanceof Handler)) {
			throw new Exception("The socket must implement the handler interface");
		}
		$this->socket = $socket;
		$this->dispatcher = $dispatcher;
	}

	public function run() {
		$this->stop = FALSE;

		while (!$this->stop) {
			list($num, $rready, $wready, $err) = $this->select();

			if (!$num) {
				$this->log(sprintf("No sockets ready to read. %d/%d pending readers/writers. Continuing.", count($this->readers), count($this->writers)));
				continue;
			}

			foreach ($err as $socket) {
				$this->log("Socket $socket in error state. Closing.");
				$socket->close();
				if (($reader_id = array_search($socket, $this->readers)) !== FALSE) {
					unset($this->readers[$reader_id]);
				}
				if (($writer_id = array_search($socket, $this->writers)) !== FALSE) {
					unset($this->writers[$writer_id]);
				}
			}

			foreach ($wready as $socket) {
				$this->write($socket);
			}

			foreach ($rready as $socket) {
				if ($socket === $this->socket) {
					$this->accept();
				} else {
					$this->read($socket);
				}
			}
		}

	}

	protected function select() {
		$rready = array_merge(array($this->socket), $this->readers);
		$wready = $this->writers;
		$err = array_merge($rready, $wready);

		$num = $this->socket->select($rready, $wready, $err, 5);

		return array($num, $rready, $wready, $err);
	}

	protected function accept() {
		try {
			$child = $this->socket->accept();
		} catch (\Exception $e) {
			$this->log("Failed to accept remote socket.");
			return;
		}

		$child->setBlocking(FALSE);
		$this->readers[] = $child;
	}

	protected function read(Socket &$socket) {
		try {
			$readComplete = $socket->readRequest();
		} catch (Exception $e) {
			$this->log("Exception caught while reading request: {$e->getMessage()}");
		}

		if ($readComplete === FALSE) {
			return;
		} elseif ($readComplete) {
			$socket->setResponse($this->dispatcher->dispatch($socket->getRequest()));
			$this->writers[] = $socket;
		} elseif ($readComplete === NULL) {
			$socket->close();
		}

		$reader_id = array_search($socket, $this->readers);
		unset($this->readers[$reader_id]);
	}

	protected function write(Socket &$socket) {
		try {
			$writeComplete = $socket->sendResponse();
		} catch (Exception $e) {
			$this->log("Exception caught while writing request: {$e->getMessage()}");
		}

		if ($writeComplete === FALSE) {
			return;
		} elseif ($writeComplete) {
			$this->readers[] = $socket;
		} elseif ($writeComplete === NULL) {
			$socket->close();
		}

		$writer_id = array_search($socket, $this->writers);
		unset($this->writers[$writer_id]);
	}
}
