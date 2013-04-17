<?php
/**
 * This file defines the HTTPHandler interface
 */
namespace janderson\net\http;

use janderson\net\socket\Server;
use janderson\net\socket\server\BaseHandler;
use janderson\net\Buffer;
use janderson\net\http\Request;
use janderson\net\http\Response;
use janderson\net\socket\Socket;
use janderson\net\socket\server\Handler as IHandler;

/**
 * Implements an HTTPHandler Socket class.
 */
class Handler extends BaseHandler implements IHandler {
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

	private function readData() {
		if (!($length = $this->request->getContentLength())) {
			$this->flags |= self::FLAG_READ_COMPLETE;
			return TRUE;
		}

		do {
			$readlen = min(self::BUF_LEN, $length - $this->buflen); /* Bytes left to read */
			echo "$this->buf";
			list($buf, $buflen) = $this->recv($readlen, MSG_DONTWAIT);

			$this->buf .= $buf;
			$this->buflen += $buflen;

			if ($this->buflen == $length) {
				$this->request->setContent($this->buf);
				$this->buf = "";
				$this->buflen = 0;
				$this->flags |= self::FLAG_READ_COMPLETE;
				return TRUE;
			}
		} while ($buflen == $readlen);

		return FALSE;
	}

	/**
	 * Attempt to read headers from the read buffer.
	 *
	 * @return bool True if headers were successfully read.
	 */
	private function readHeaders() {
		/* Check for any double EOL */
		foreach (array("\r\n" => 2, "\n" => 1, "\r" => 1) as $eol => $eollen) {
			$dbleollen = $eollen << 1; /* $eollen * 2, slightly faster */

			if ($eolpos = strpos($this->rbuf->buffer, $eol . $eol)) {
				$headers = $this->rbuf->get($eolpos + $dbleollen, TRUE);
				$this->headers = explode($eol, $headers);

				$this->parseRequest();
				$this->flags |= self::FLAG_READ_HEADERS;
				return TRUE;
			}
		}

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
