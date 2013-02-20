<?php

namespace janderson\net\socket;

require __DIR__ . "/Socket.php";
require __DIR__ . "/Handler.php";
require __DIR__ . "/HTTPRequest.php";
require __DIR__ . "/HTTPResponse.php";
require __DIR__ . "/HTTPHandler.php";
require __DIR__ . "/Exception.php";

use \janderson\net\socket\Socket;
use \janderson\net\socket\HTTPRequest;

class Server {
	protected $port;
	protected $socket;

	protected $readers = array();
	protected $writers = array();

	protected function log($message) {
		//error_log($message);
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

	protected function dispatch(HTTPRequest $request) {
		return new HTTPResponse($request);
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
				$writer_id = array_search($socket, $this->writers);
				if ($socket->shouldClose()) {
					$this->log("Normal socket close after write.");
					$socket->close();
					unset($this->writers[$writer_id]);
				} elseif ($socket->sendResponse()) {
					if ($socket->shouldClose()) {
						$this->log("Normal socket shutdown after write.");
						$socket->shutdown();
						usleep(500);
					} else {
						$this->readers[] = $socket;
						unset($this->writers[$writer_id]);
					}
				}
			}

			foreach ($rready as $socket) {
				if ($socket === $this->socket) {
					$child = $socket->accept();
					$child->setBlocking(FALSE);
					$this->readers[] = $child;
				} else {
					$readComplete = $socket->readRequest();

					$reader_id = array_search($socket, $this->readers);

					if ($readComplete) {
						$response = $this->dispatch($socket->getRequest());
						$socket->setResponse($response);
						unset($this->readers[$reader_id]);
						$this->writers[] = $socket;
					} elseif ($readComplete === NULL) {
						$this->log("Abnormal socket closure (remote)");
						$socket->close();
						unset($this->readers[$reader_id]);
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
