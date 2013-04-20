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

/**
 * Implements an HTTPHandler Socket class.
 */
class Handler extends BaseHandler {
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
		list($buf, $len) = $response->getBuffer();
		$this->wbuf->set($buf, $len);
	}

	protected function writeComplete() {
		parent::writeComplete();

		$keepAlive = TRUE;
		if (!$this->wbuf->length) {
			$keepAlive = $this->request->keepAlive();
			$this->request = $this->response = $this->flags = NULL;
		}

		return $keepAlive;
	}

	/**
	 * Read an HTTP request
	 *
	 * @return bool|null
	 */
	public function readComplete() {
		if (!($this->flags & self::FLAG_READ_COMPLETE)) {
			if (!($this->flags & self::FLAG_READ_HEADERS)) {
				$this->readHeaders();
			}

			if ($this->flags & self::FLAG_READ_HEADERS) {
				$this->readData();
			}
		}

		if ($this->flags & self::FLAG_READ_COMPLETE) {
			/* Dispatch here.... and do this better. */
			$response = new Response($this->request);
			$response->setContent("test");
			$this->setResponse($response);
		}

		return TRUE;
	}

	/**
	 *
	 * @return bool Returns true of the request data was successfully read.
	 */
	protected function readData() {
		/* Unset or empty Content-Length */
		if (!($length = $this->request->getContentLength())) {
			$this->flags |= self::FLAG_READ_COMPLETE;
			return TRUE;
		}

		/* Not enough data in the buffer. Keep reading. */
		if ($this->rbuf->length < $length) {
			return FALSE;
		}

		/* Enough data. Take it out of the buffer, and put it in the request. */
		$this->request->setContent($this->rbuf->get($length, TRUE));
		$this->flags |= self::FLAG_READ_COMPLETE;
		return TRUE;
	}

	/**
	 * Attempt to read headers from the read buffer.
	 *
	 * @return bool True if headers were successfully read.
	 */
	protected function readHeaders() {
		/* Check for any double EOL, which signifies the end of headers. */
		foreach (array("\r\n" => 2, "\n" => 1, "\r" => 1) as $eol => $eollen) {
			$dbleollen = $eollen << 1; /* $eollen * 2, slightly faster */

			if ($eolpos = $this->rbuf->find($eol . $eol)) {
				$lines = $this->rbuf->get($eolpos + $dbleollen, TRUE);
				$lines = explode($eol, $lines);

				if (empty($lines)) {
					throw new Exception("Invalid request: no headers found.");
				}

				/* Parse and validate the request line */
				$request = explode(" ", array_shift($lines));

				if (count($request) != 3) {
					throw new Exception("Malformed request line");
				}

				/* Parse and validate the headers */
				$headers = array();
				foreach ($lines as $line) {
					if (empty($line)) continue;

					$header = explode(":", $line, 2);

					if (count($header) != 2) {
						throw new Exception("Malformed header line");
					}

					$headers[strtolower(trim($header[0]))] = trim($header[1]);
				}

				$this->request = new Request($request[0], $request[1], $request[2], $headers);

				$this->flags |= self::FLAG_READ_HEADERS;
				return TRUE;
			}
		}

		return FALSE;
	}
}
