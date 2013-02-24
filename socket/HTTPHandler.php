<?php
/**
 * This file defines the HTTPHandler interface
 */
namespace janderson\net\socket;

use janderson\net\http\Request;
use janderson\net\http\Response;

/**
 * Implements an HTTPHandler Socket class.
 */
class HTTPHandler extends Socket implements Handler {
	const EOL = "\r\n";
	const BUF_LEN = 4096;

	/**
	 * Signifies that the read is complete.
	 */
	const FLAG_READ_COMPLETE = 0x01;

	/**
	 * Signfies that the headers have been read.
	 */
	const FLAG_READ_HEADERS = 0x02;

	/**
	 * Signifies that the socket should be closed after the response has been transmitted.
	 */
	const FLAG_CLOSE = 0x04;

	protected $flags = 0;
	protected $request;
	protected $response;
	protected $headers = array();

	/**
	 * The buffer is used for both read and write phases.
	 *
	 * During the read phase, the buffer is filled as it becomes available then parsed into an HTTPRequest when a complete request is received.
	 * During the write phase, the buffer is pulled from an HTTPResponse and emptied in chunks into the socket.
	 *
	 * @var string
	 */
	protected $buf;

	/**
	 * The length of the content in buffer, so repeated strlen isn't necessary
	 * @var int
	 */
	protected $buflen = 0;

	/**
	 * The length of the buffer left to send.
	 *
	 * @var int
	 */
	protected $bufrem;

	public function getRequest() {
		return $this->request;
	}

	public function setResponse($response) {
		$this->response = $response;
	}

	/**
	 * Read an HTTP request
	 *
	 * @return bool|null
	 */
	public function readRequest() {
		if ($this->flags & self::FLAG_READ_COMPLETE) return TRUE;

		if (!($this->flags & self::FLAG_READ_HEADERS)) {
			$readResult = $this->readHeaders();
		}

		if ($this->flags & self::FLAG_READ_HEADERS) {
			$readResult = $this->readData();
		}

		if ($readResult === NULL) {
			return NULL;
		}

		return (bool)($this->flags & self::FLAG_READ_COMPLETE);
	}

	public function sendResponse() {
		if (empty($this->buf)) {
			list($this->buf, $this->buflen) = $this->response->getBuffer();
			$this->bufrem = $this->buflen;
		}

		$bufpos = $this->buflen - $this->bufrem;
		$sent = $this->send(substr($this->buf, $bufpos, self::BUF_LEN), $this->bufrem);

		$this->bufrem -= $sent;

		if (!$this->bufrem) {
			$this->flags |= self::FLAG_CLOSE;
			return TRUE;
		}

		return FALSE;
	}

	public function shouldClose() {
		return (bool)($this->flags & self::FLAG_CLOSE);
	}

	private function readData() {
		if (!($length = $this->request->getContentLength())) {
			$this->flags |= self::FLAG_READ_COMPLETE;
			return TRUE;
		}

		do {
			$readlen = min(self::BUF_LEN, $length - $this->buflen); /* Bytes left to read */

			list($buf, $buflen) = $this->recv($readlen, MSG_DONTWAIT);

			$this->buf .= $buf;
			$this->buflen += $buflen;

			if ($this->buflen == $length) {
				$this->flags |= self::FLAG_READ_COMPLETE;
				return TRUE;
			}
		} while ($buflen == $readlen);

		return FALSE;
	}

	private function readHeaders() {
		do {
			/* Do a peek to see if the next chunk contains the end of headers. Some efficiency loss here. */
			list($buf, $buflen) = $this->recv(self::BUF_LEN, MSG_PEEK | MSG_DONTWAIT);

			/* Using non-blocking sockets, if select succeeds but returns 0, the socket is closed. */
			if (!$buflen) return NULL;

			foreach (array("\r\n" => 2, "\n" => 1, "\r" => 1) as $eol => $eollen) {
				$dbleollen = $eollen * 2;

				if ($this->buflen == 0) {
					$subtract = 0;
				} elseif ($this->buflen > $dbleollen) {
					$buf = substr($this->buf, -$dbleollen) . $buf;
					$subtract = $dbleollen;
				} else {
					$buf = $this->buf . $buf;
					$subtract = $this->buflen;
				}

				if ($eolpos = strpos($buf, $eol . $eol)) {
					list($buf, $buflen) = $this->recv($eolpos + ($eollen * 2) - $subtract);
					$this->buf .= $buf;
					$this->buflen += $buflen;
					$this->headers = explode($eol, $this->buf);
					$this->buf = "";

					$this->parseRequest();
					$this->flags |= self::FLAG_READ_HEADERS;
					return TRUE;
				}
			}

			list($buf, $buflen) = $this->recv($buflen);
			$this->buf .= $buf;
			$this->buflen += $buflen;
		} while ($buflen == self::BUF_LEN);

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

		$this->request = new Request($request[0], $request[1], $request[2], $this->parseHeaders());
	}

	/**
	 * Given a set of unparsed header lines, extract the key/value pairs from each header line.
	 *
	 * This function finalizes the headers property as an associative array.
	 *
	 * @return string[] An array of headers.
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

		return $headers;
	}
}
