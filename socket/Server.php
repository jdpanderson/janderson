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

	protected $readers = array();
	protected $writers = array();

	protected $responses = array();
	protected $requests = array();

	protected function log($message) {
		error_log($message);
	}

	public function __construct($port = 8080) {
		$this->socket = new Socket();
		$this->socket->setBlocking(FALSE);
		if (!$this->socket->listen(100, Socket::ADDR_ANY, $port)) {
			list($errno, $error) = $this->socket->getError();
			throw new Exception("Unable to create socket: $error ($errno)");
		}
	}

	protected function dispatch(HTTPRequest $request) {
		return new HTTPResponse($request);
	}

	protected function addReader(Socket $reader) {
		array_unshift($this->readers, $reader);
		array_unshift($this->requests, new HTTPRequest());
	}

	protected function addWriter(Socket $writer, HTTPResponse $response) {
		array_unshift($this->writers, $writer);
		array_unshift($this->responses, $response);
	}

	public function run() {
		$this->stop = FALSE;

		while (!$this->stop) {
			$rready = array_merge(array($this->socket), $this->readers);
			$wready = $this->writers;
			$err = array_merge($rready, $wready);

			$num = Socket::select($rready, $wready, $err, 5);

			if (!$num) {
				if ($num === FALSE) {
					list($errno, $error) = $this->socket->getEror();
					throw new Exception("Select failed: $error ($errno)");
				}

				$this->log(sprintf("No sockets ready to read. %d/%d pending readers/writers. Continuing.", count($this->readers), count($this->writers)));
				continue;
			}

			foreach ($err as $socket) {
				$this->log("Socket $socket in error state. Closing.");
				$socket->close();
				if (($reader_id = array_search($socket, $this->readers)) !== FALSE) {
					unset($this->readers[$reader_id], $this->requests[$reader_id]);
				}
				if (($writer_id = array_search($socket, $this->writers)) !== FALSE) {
					unset($this->writers[$writer_id], $this->responses[$writer_id]);
				}
			}

			foreach ($wready as $socket) {
				$writer_id = array_search($socket, $this->writers);
				if ($this->responses[$writer_id]->shouldClose()) {
					$this->log("Normal socket close after write.");
					$socket->close();
					unset($this->writers[$writer_id], $this->responses[$writer_id]);
				} elseif ($this->responses[$writer_id]->send($socket)) {
					if ($this->responses[$writer_id]->shouldClose()) {
						$this->log("Normal socket shutdown after write.");
						$socket->shutdown();
						usleep(500);
					} else {
						$this->addReader($socket);
						unset($this->writers[$writer_id], $this->responses[$writer_id]);
					}
				}
			}

			foreach ($rready as $socket) {
				if ($socket === $this->socket) {
					$child = $socket->accept();
					$child->setBlocking(FALSE);
					$this->addReader($child);
				} else {
					$reader_id = array_search($socket, $this->readers);

					$readComplete = $this->requests[$reader_id]->read($socket);

					if ($readComplete) {
						$response = $this->dispatch($this->requests[$reader_id]);
						unset($this->readers[$reader_id], $this->requests[$reader_id]);
						$this->addWriter($socket, $response);
					} elseif ($readComplete === NULL) {
						$this->log("Abnormal socket closure (remote)");
						$socket->close();
						unset($this->readers[$reader_id], $this->requests[$reader_id]);
					}
				}
			}
		}
	}
}



$svr = new Server();
$svr->run();
