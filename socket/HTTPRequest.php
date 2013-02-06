<?php

namespace janderson\net\socket;

//use \janderson\net\socket\Socket as Socket;

class HTTPRequest {
	const EOL = "\r\n";
	const BUF_LEN = 4096;

	const FLAG_COMPLETE = 0x01;
	const FLAG_HEADERS = 0x02;

	protected $flags = 0;
	protected $headers = array();
	protected $method;
	protected $uri;
	protected $version;
	protected $data = "";

	public function read(Socket $socket) {
		if ($this->flags & self::FLAG_COMPLETE) return TRUE;

		if (!($this->flags & self::FLAG_HEADERS)) {
			$readResult = $this->readSocketHeaders($socket);
		}

		if ($this->flags & self::FLAG_HEADERS) {
			$readResult = $this->readSocketData($socket);
		}

		if ($readResult === NULL) return NULL;

		return (bool)($this->flags & self::FLAG_COMPLETE);
	}

	private function readSocketData($socket) {
		$length = $this->getHeader('content-length');

		if (!$length) {
			$this->flags |= self::FLAG_COMPLETE;
			return TRUE;
		}

		do {
			$datalen = strlen($this->data);
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
				return TRUE;
			}
		} while ($len == $readlen);

		return FALSE;
	}

	private function readSocketHeaders($socket) {
		do {
			list($buf, $len) = $socket->recv(self::BUF_LEN, MSG_PEEK | MSG_DONTWAIT);

			if ($len === FALSE) {
				list($errno, $error) = $socket->getError();
				if ($errno == SOCKET_EWOULDBLOCK) return;
				throw new Exception("recv error: $error ($errno)");
			}

			/* Using non-blocking sockets, if select succeeds but returns 0, the socket is closed. */
			if (!$len) return NULL;

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
					return TRUE;
				}
			}

			list($buf, $len) = $socket->recv($len);
			$this->data .= $buf;
		} while ($len == self::BUF_LEN);

		return FALSE;
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
