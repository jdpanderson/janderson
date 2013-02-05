<?php

namespace janderson\net\socket;

require __DIR__ . "/Socket.php";
require __DIR__ . "/HTTPRequest.php";
require __DIR__ . "/Exception.php";

use \janderson\net\socket\Socket;
use \janderson\net\socket\HTTPRequest;

class SocketServer {
	protected $port;
	protected $socket;

	protected function log($message) {
		error_log($message);
	}

	public function __construct($port = 8080) {
		$this->socket = new Socket();
		$this->socket->setBlocking(FALSE);
		if (!$this->socket->listen(8, Socket::ADDR_ANY, $port)) {
			list($errno, $error) = $this->socket->getError();
			throw new Exception("Unable to create socket: $error ($errno)");
		}
	}

	protected function dispatch(SocketHTTPRequest $request) {

	}

	public function run() {
		$this->stop = FALSE;
		$children = array();

		while (!$this->stop) {
			$readers = array_merge(array($this->socket), $children);
			list ($read, $write, $err) = array($readers, array(), $readers);

			$num = Socket::select($read, $write, $err, 5);
			if (!$num) {
				if ($num === FALSE) {
					list($errno, $error) = $this->socket->getEror();
					throw new Exception("Select failed: $error ($errno)");
				}

				$this->log("No sockets ready to read. Continuing.");
				continue;
			}

			if (!empty($err)) {
				// XXX clean up error socket here.
				throw new Exception("Failure on a socket: cleanup not yet handled.");
			}

			if (!empty($read)) {
				foreach ($read as $socket) {
					if ($socket === $this->socket) {
						$child = $socket->accept();
						$child->setBlocking(FALSE);

						$child_id = count($children);
						$children[$child_id] = $child;
					} else {
						$child_id = array_search($socket, $children);
						if (!isset($requests[$child_id])) {
							$requests[$child_id] = new SocketHTTPRequest();
						}
						if ($requests[$child_id]->readSocket($socket)) {
							$this->dispatch($requests[$child_id]);
							$requests[$child_id] = NULL;
						}
					}
				}

			}
		}
	}
}



$svr = new SocketServer();
$svr->run();
