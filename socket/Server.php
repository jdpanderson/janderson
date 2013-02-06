<?php

namespace janderson\net\socket;

require __DIR__ . "/Socket.php";
require __DIR__ . "/HTTPRequest.php";
require __DIR__ . "/HTTPResponse.php";
require __DIR__ . "/Exception.php";

use \janderson\net\socket\Socket;
use \janderson\net\socket\HTTPRequest;

class Server {
	protected $port;
	protected $socket;

	protected function log($message) {
		error_log($message);
	}

	public function __construct($port = 8080) {
		$this->socket = new Socket();
		$this->socket->setBlocking(FALSE);
		if (!$this->socket->listen(150, Socket::ADDR_ANY, $port)) {
			list($errno, $error) = $this->socket->getError();
			throw new Exception("Unable to create socket: $error ($errno)");
		}
	}

	protected function dispatch(HTTPRequest $request) {
		return new HTTPResponse($request);
	}

	public function run() {
		$this->stop = FALSE;
		$requests = array();
		$responses = array();
		$readers = array();
		$writers = array();

		while (!$this->stop) {
			$rready = array_merge(array($this->socket), $readers);
			$wready = $writers;
			$err = array_merge($rready, $wready);

			$num = Socket::select($rready, $wready, $err, 5);

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

			foreach ($wready as $socket) {
				$writer_id = array_search($socket, $writers);
				if ($responses[$writer_id]->send($socket)) {
					if ($responses[$writer_id]->shouldClose()) {
						$socket->close();
					} else {
						array_push($readers, $socket);
					}
					unset($writers[$writer_id], $responses[$writer_id]);
				}
			}

			foreach ($rready as $socket) {
				if ($socket === $this->socket) {
					$child = $socket->accept();
					$child->setBlocking(FALSE);
					array_push($readers, $child);
				} else {
					$reader_id = array_search($socket, $readers);
					if (!isset($requests[$reader_id])) {
						$requests[$reader_id] = new HTTPRequest();
					}

					$readComplete = $requests[$reader_id]->read($socket);
					if ($readComplete) {
						$response = $this->dispatch($requests[$reader_id]);
						unset($readers[$reader_id], $requests[$reader_id]);
						array_unshift($writers, $socket);
						array_unshift($responses, $response);
					} elseif ($readComplete === NULL) {
						unset($readers[$reader_id], $requests[$reader_id]);
					}
				}
			}
		}
	}
}



$svr = new Server();
$svr->run();
