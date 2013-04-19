<?php

namespace janderson\net\socket\server;

use \janderson\net\socket\Exception;
use \janderson\net\socket\Socket;

class Server {
	const STATE_RD = 1;
	const STATE_WR = 2;
	const STATE_RDWR = 3;

	const SELECT_TIMEOUT = 1;
	protected $socket;
	protected $handlerClass;

	protected $stop = FALSE;

	protected $sockets = array();
	protected $handlers = array();

	protected function log($message) {
		error_log($message);
	}

	/**
	 * @throws Exception An exception will be thrown by the underlying socket implementation if a listen socket cannot be created.
	 */
	public function __construct(Socket $socket, $handlerClass) {
		$this->socket = $socket;

		if (!in_array('janderson\net\socket\server\Handler', class_implements($handlerClass))) {
			throw new Exception("Handler class must implement the Handler interface");
		}

		$this->handlerClass = $handlerClass;
	}

	public function run() {
		$this->stop = FALSE;

		while (!$this->stop) {
			list($num, $rready, $wready, $err) = $this->select();
			echo "Found $num sockets\n";

			/* No sockets ready to read/write, probably hit the select timeout.  */
			if (!$num) {
				continue;
			}

			foreach ($err as $socket) {
				echo "Error!\n";
				$this->error($socket);
			}

			foreach ($wready as $socket) {
				echo "write\n";
				$this->write($socket) || $this->close($socket);
			}

			foreach ($rready as $socket) {
				echo "read";
				if ($socket === $this->socket) {
					$this->accept() || $this->close($socket);
				} else {
					$this->read($socket) || $this->close($socket);
				}
			}
		}
	}

	public function stop() {
		$this->stop = TRUE;
	}

	protected function error(&$socket) {
		$this->log("Socket $socket in error state. Closing.");
		echo "Err close\n";
		$this->close($socket);
	}

	protected function select() {
		$rd = array($this->socket);
		$wr = array();
		$err = array();

		foreach ($this->sockets as $socket) {
			$state = $this->handlers[$socket->getResourceId()]->getState();

			if ($state & Server::STATE_RD) $rd[] = $socket;
			if ($state & Server::STATE_WR) $wr[] = $socket;
			$err[] = $socket;

			echo "Socket in $state\n";
		}

		$num = $this->socket->select($rd, $wr, $err, self::SELECT_TIMEOUT);

		return array($num, $rd, $wr, $err);
	}

	protected function accept() {
		try {
			$socket = $this->socket->accept();
		} catch (\Exception $e) {
			$this->log("Failed to accept remote socket.");
			return FALSE;
		}

		$socket->setBlocking(FALSE);
		$handlerClass = $this->handlerClass;
		$resourceId = $socket->getResourceId();
		$this->handlers[$resourceId] = new $handlerClass();
		$this->sockets[$resourceId] = $socket;

		return TRUE;
	}

	protected function read(Socket &$socket) {
		$handler = $this->handlers[$socket->getResourceId()];

		return $handler->read($socket);
	}

	protected function write(Socket &$socket) {
		$handler = $this->handlers[$socket->getResourceId()];

		return $handler->write($socket);
	}

	protected function close(&$socket) {
		$resourceId = $socket->getResourceId();
		unset($this->handlers[$resourceId], $this->sockets[$resourceId]);
		$socket->close();
	}
}
