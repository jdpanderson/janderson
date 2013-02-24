<?php

namespace janderson\net\socket;

require __DIR__ . "/../http/HTTP.php";
require __DIR__ . "/../http/Request.php";
require __DIR__ . "/../http/Response.php";

require __DIR__ . "/Socket.php";
require __DIR__ . "/Handler.php";
require __DIR__ . "/HTTPHandler.php";
require __DIR__ . "/Exception.php";

use \janderson\net\http\Request;
use \janderson\net\http\Response;

class Server {
	protected $port;
	protected $socket;

	protected $readers = array();
	protected $writers = array();

	protected function log($message) {
		error_log($message);
	}

	/**
	 * @param int $port
	 * @throws Exception An exception will be thrown by the underlying socket implementation if a listen socket cannot be created.
	 */
	public function __construct(Socket $socket) {
		if (!($socket instanceof Handler)) {
			throw new Exception("The socket must implement the handler interface");
		}
		$this->socket = $socket;
	}

	protected function dispatch($request) {
		$response = new Response($request);
		$response->setContent('This is a test');
		return $response;
	}

	public function run() {
		$this->stop = FALSE;

		while (!$this->stop) {
			$rready = array_merge(array($this->socket), $this->readers);
			$wready = $this->writers;
			$err = array_merge($rready, $wready);

			$num = $this->socket->select($rready, $wready, $err, 5);

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
				try {
					$writeComplete = $socket->sendResponse();
				} catch (Exception $e) {
					$this->log("Exception caught while writing request: {$e->getMessage()}");
				}

				if ($writeComplete === FALSE) continue;

				if ($writeComplete) {
					if ($socket->shouldClose()) {
						$socket->close();
					} else {
						$this->readers[] = $socket;
					}
				} elseif ($writeComplete === NULL) {
					$socket->close();
				}

				$writer_id = array_search($socket, $this->writers);
				unset($this->writers[$writer_id]);
			}

			foreach ($rready as $socket) {
				if ($socket === $this->socket) {
					try {
						$child = $socket->accept();
					} catch (Exception $e) {
						$this->log("Failed to accept remote socket.");
						continue;
					}
					$child->setBlocking(FALSE);
					$this->readers[] = $child;
				} else {
					try {
						$readComplete = $socket->readRequest();
					} catch (Exception $e) {
						$this->log("Exception caught while reading request: {$e->getMessage()}");
					}

					if ($readComplete === FALSE) continue;

					$reader_id = array_search($socket, $this->readers);
					unset($this->readers[$reader_id]);

					if ($readComplete) {
						/* Successfully read a request. Dispatch and respond. */
						$socket->setResponse($this->dispatch($socket->getRequest()));
						$this->writers[] = $socket;
					} elseif ($readComplete === NULL) {
						/* Exception caught during readRequest, or abnormal socket closure (usually remote) */
						$socket->close();
					}
				}
			}
		}
	}
}

$socket = new HTTPHandler();
$socket->setBlocking(FALSE);
$socket->listen(50, Socket::ADDR_ANY, 8080);

$svr = new Server($socket);
$svr->run();
