<?php

class Socket {
	const ADDR_ANY = "0.0.0.0";
	const PORT_ANY = 0;

	public function getError() {
		$errno = isset($this->socket) ? socket_last_error($this->socket) : socket_last_error();
		$error = socket_strerror($errno);

		return array($errno, $error);
	}

	/**
	 * @param int $domain Protocol family, usually AF_INET or AF_INET6. Also accepts a socket resource to be wrapped.
	 * @param int $type
	 * @param int $proto
	 */
	public function __construct($domain = AF_INET, $type = SOCK_STREAM, $proto = SOL_TCP) {
		if (is_resource($domain) && get_resource_type($domain) == "Socket") {
			$this->socket = $domain;
			return;
		}

		$this->socket = socket_create($domain, $type, $proto);

		if (!$this->socket) {
			list($errno, $error) = $this->getError();
			throw new Exception("Unable to create socket: $error ($errno)");
		}
	}

	public function accept() {
		$child = socket_accept($this->socket);

		if ($child === FALSE) return FALSE;

		return new Socket($child);
	}

	public function bind($address = self::ADDR_ANY, $port = self::PORT_ANY) {
		return socket_bind($this->socket, $address, $port);
	}

	public function close() {
		return socket_close($this->socket);
	}

	public function listen($backlog = 0, $address = NULL, $port = NULL) {
		if (!$this->setOption(SO_REUSEADDR, 1)) return FALSE;

		if (isset($address) || isset($port)) {
			if (!$this->bind($address, $port)) return FALSE;
		}

		if (!socket_listen($this->socket, $backlog)) return FALSE;

		return TRUE;
	}

	public function setOption($option, $value) {
		// XXX this could detect known options and adjust SOL_SOCKET. For now, no use case for anything other than SOL_SOCKET
		return socket_set_option($this->socket, SOL_SOCKET, $option, $value);
	}

	public function getOption($option) {
		return socket_get_option($this->socket, SOL_SOCKET, $option);
	}

	public function getPeerName() {
		$result = socket_getpeername($this->socket, $addr, $port);
		return $result ? array($addr, $port) : FALSE;
	}

	public function getSockName() {
		$result = socket_getsockname($this->socket, $addr, $port);
		return $result ? array($addr, $port) : FALSE;
	}

	public function setBlocking($blocking = TRUE) {
		return $blocking ? socket_set_block($this->socket) : socket_set_nonblock($this->socket);
	}

	public static function select(&$read = NULL, &$write = NULL, &$except = NULL, $tv_sec = 0, $tv_usec = 0) {
		$map = array();

		foreach (array('read', 'write', 'except') as $arrName) {
			if (!is_array($$arrName)) continue;
			foreach ($$arrName as &$s) {
				if ($s instanceof Socket) {
					$socket = $s->getResource();
					$map[(string)$socket] = $s;
					$s = $socket;
				}
			}
		}

		$result = socket_select($read, $write, $except, $tv_sec, $tv_usec);

		foreach (array('read', 'write', 'except') as $arrName) {
			if (!is_array($$arrName)) continue;
			foreach ($$arrName as &$s) {
				if (isset($map[(string)$s])) {
					$s = $map[(string)$s];
				}
			}
		}

		return $result;
	}

	public function recv($len, $flags = NULL) {
		$len = socket_recv($this->socket, $buf, $len, $flags);

		return array($buf, $len);
	}

	public function send($buf, $len, $flags = NULL) {
		return socket_send($this->socket, $buf, $len, $flags);
	}

	public function getResource() {
		return $this->socket;
	}

	public function getResourceId() {
		$str = (string)$this->socket;

		$tok = explode("#", $str, 2);

		if (count($tok) != 2 || $tok[0] != "Resource id") throw new Exception("String format has changed: Cannot parse resource ID");

		return (int)$tok[1];
	}
}

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
							$requests[$child_id] = new HTTPRequest();
						}
						if ($requests[$child_id]->readSocket($socket)) {
							$this->log("Request complete!");
							// XXX create response here, and either close if connection should not keep alive, or create new req.
							$requests[$child_id] = new HTTPRequest();
						}
					}
				}

			}
		}
	}
}

class HTTPRequest {
	const EOL = "\r\n";
	const BUF_LEN = 4096;

	const FLAG_COMPLETE = 0x01;
	const FLAG_HEADERS  = 0x02;

	protected $flags = 0;
	protected $headers = array();
	protected $method;
	protected $uri;
	protected $version;
	protected $data = "";

	public function readSocket(Socket $socket) {
		if ($this->flags & self::FLAG_COMPLETE) return TRUE;

		if (!($this->flags & self::FLAG_HEADERS)) {
			$this->readSocketHeaders($socket);
		}

		if ($this->flags & self::FLAG_HEADERS) {
			$this->readSocketData($socket);
		}

		return (bool)($this->flags & self::FLAG_COMPLETE);
	}

	public function readSocketData($socket) {
		$length = $this->getHeader('content-length');

		if (!$length) {
			$this->flags |= self::FLAG_COMPLETE;
			return;
		}

		do {
			$datalen = strlen($data);
			$readlen = $length - $datalen;
			if ($readlen > self::BUF_LEN) {
				$readlen = self::BUF_LEN;
			}

			list($buf, $len) = $socket->recv($socket, $readlen, MSG_DONTWAIT);

			if ($len === FALSE) {
				list($errno, $error) = $socket->getError();
				if ($errno == SOCKET_EWOULDBLOCK) return;
				throw new Exception("recv error: $error ($errno)");
			}

			$this->data .= $buf;

			if ($datalen + $len == $length) {
				$this->flags |= self::FLAG_COMPLETE;
				return;
			}
		} while ($len == $readlen);
	}

	public function readSocketHeaders($socket) {
		do {
			list($buf, $len) = $socket->recv(self::BUF_LEN, MSG_PEEK | MSG_DONTWAIT);

			if ($len === FALSE) {
				list($errno, $error) = $socket->getError();
				if ($errno == SOCKET_EWOULDBLOCK) return;
				throw new Exception("recv error: $error ($errno)");
			}

			if (!$len) {
				// XXX should have a way to specify the closed socket to the server.
				throw new Exception("Socket closed");
			}

			foreach (array("\r\n" => 2, "\n" => 1, "\r" => 1) as $eol => $eollen) {
				$dbleollen = $eollen * 2;
				$datalen = strlen($this->data);

				if ($datalen == 0) {
					$subtract = 0;
				} elseif ($datalen > $dbleollen) {
					$buf = substr($this->data, -$dbleollen) . $buf;
					$subtract = $dbleollen;
				} else {
					$buf = $this->data . $buf;
					$subtract = $datalen;
				}

				if ($eolpos = strpos($buf, $eol . $eol)) {
					list($buf, $len) = $socket->recv($eolpos + ($eollen * 2) - $subtract);
					$this->data .= $buf;
					$this->headers = explode($eol, $this->data);
					$this->data = "";

					$this->parseRequest();
					$this->parseHeaders();
					$this->flags |= self::FLAG_HEADERS;
					return;
				}
			}

			list($buf, $len) = $socket->recv($len);
			$this->data .= $buf;
		} while ($len == self::BUF_LEN);
	}

	/**
	 * Given a set of unparsed header lines, extract the first line: the VERB URI VERSION (GET / HTTP/1.0) line.
	 *
	 * @throws Exception
	 */
	private function parseRequest() {
		if (!count($this->headers)) throw new Exception("Invalid request: no request line found.");

		$request = explode(" ", array_shift($this->headers));

		if (count($request) != 3) throw new Exception("Malformed request line");

		list($this->method, $this->uri, $this->version) = $request;
	}

	/**
	 * Given a set of unparsed header lines, extract the key/value pairs from each header line.
	 *
	 * This function finalizes the headers property as an associative array.
	 *
	 * @throws Exception
	 */
	private function parseHeaders() {
		$headers = array();
		foreach ($this->headers as $header) {
			if (empty($header)) continue;

			$header = explode(":", $header, 2);
			if (count($header) != 2) throw new Exception("Malformed header line");

			list($header, $value) = $header;
			$headers[strtolower(trim($header))] = trim($value);
		}

		$this->headers = $headers;
	}

	/**
	 *
	 * @param $header
	 */
	public function getHeader($header) {
		$header = strtolower($header);

		return isset($this->headers[$header]) ? $this->headers[$header] : FALSE;
	}
}

$svr = new SocketServer();
$svr->run();
