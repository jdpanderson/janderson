<?php
/**
 * This file defines the HTTPHandler interface
 */
namespace janderson\socket\server\handler;

use janderson\socket\Server;
use janderson\socket\server\BaseHandler;
use janderson\Buffer;
use janderson\http\Request;
use janderson\http\Response;
use janderson\socket\Socket;

/**
 * Implements an HTTPHandler Socket class.
 */
class HTTPHandler {
	/**
	 * Write buffer
	 *
	 * @var string
	 */
	protected $buffer;

	/**
	 * Read buffer (data received so far)
	 *
	 * @var string
	 */
	protected $rbuffer;

	/**
	 * Read buffer length
	 *
	 * @var int
	 */
	protected $rbuflen;

	public function __construct(&$buffer)
	{
		$this->buffer = &$buffer;
	}

	protected $request;
	protected $response;

	public function getRequest() {
		return $this->request;
	}

	public function getResponse($response) {
		return $this->response;
	}

	public function write() {
		$keepAlive = $this->request->keepAlive();
		$this->request = $this->response =  NULL;

		return $keepAlive;
	}

	/**
	 * Read an HTTP request
	 *
	 * @return bool|null
	 */
	public function read($buffer, $length) {
		$this->rbuffer .= $buffer;
		$this->rbuflen += $length;

		if (!isset($this->request)) {
			if (!$this->readHeaders()) {
				return TRUE;
			}
		}

		if (!isset($this->response)) {
			if (!$this->readData()) {
				return TRUE;
			}
		}

		$this->response = new Response($this->request);
		$this->response->setContent("test");

		list($buf, $len) = $this->response->getBuffer();

		$this->buffer = $buf;

		return TRUE;
	}

	/**
	 * Reads the data part of the request, the content.
	 *
	 * @return bool Returns true of the request data was successfully read.
	 */
	protected function readData() {
		$length = $this->request->getContentLength();

		/* Not enough data in the buffer. Keep reading. */
		if ($this->rbuflen < $length) {
			return FALSE;
		}

		/* Enough data. Take the content (if applicable) out of the buffer and put it in the request. */
		if ($length) {
			$this->request->setContent(substr($this->rbuffer, 0, $length));
			$this->rbuffer = substr($this->rbuffer, $length);
			$this->rbuflen -= $length;
		}

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

			if ($eolpos = strpos($this->rbuffer, $eol . $eol)) {
				$lines = substr($this->rbuffer, 0, $eolpos + $dbleollen);
				$lines = explode($eol, $lines);

				$this->rbuffer = substr($this->rbuffer, $eolpos + $dbleollen);
				$this->rbuflen -= $eolpos + $dbleollen;

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

				return TRUE;
			}
		}

		return FALSE;
	}

	public function close()
	{
		//echo "HTTP connection closed\n";
	}
}
