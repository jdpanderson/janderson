<?php

namespace janderson\net;

use \janderson\net\Socket;
use \janderson\net\SocketHTTPRequest;

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
						$this->log("Accepting new child...");
						$child = $socket->accept();
						$child->setBlocking(FALSE);

						$child_id = count($children);
						$children[$child_id] = $child;
						//$requests[$child_id] = new HTTPRequest();
					} else {
						$child_id = array_search($socket, $children);
						$this->log("Reading from child $child_id");
						if (!isset($requests[$child_id])) {
							$requests[$child_id] = new SocketHTTPRequest();
						}
						if ($requests[$child_id]->readSocket($socket)) {
							$this->log("Request complete!");
							// XXX create response here, and either close if connection should not keep alive, or create new req.
							$requests[$child_id] = new SocketHTTPRequest();
						}
					}
				}

			}
		}
	}
}



$svr = new SocketServer();
$svr->run();
