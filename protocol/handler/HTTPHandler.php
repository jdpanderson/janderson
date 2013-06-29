<?php
/**
 * This file defines the HTTPHandler interface
 */
namespace janderson\protocol\handler;

use janderson\protocol\http\Request;
use janderson\protocol\http\Response;
use janderson\protocol\http\HTTPException;

/**
 * Implements an HTTPHandler class.
 */
class HTTPHandler implements ProtocolHandler
{
	/**
	 * Write buffer
	 *
	 * @var string
	 */
	protected $buffer;

	/**
	 * Write buffer length
	 *
	 * @var int
	 */
	protected $buflen;

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

	/**
	 * @var janderson\protocol\http\Request
	 */
	protected $request;

	/** 
	 * @var janderson\protocol\http\Response
	 */
	protected $response;

	/**
	 * The class that will dispatch requests.
	 *
	 * @var Dispatcher
	 */
	protected $dispatcher;

	/**
	 *
	 * @param string $buffer The write buffer to which responses should be written.
	 * @param int $buflen The length of the write buffer.
	 * @param mixed[] $params Parameters for this handler.
	 */
	public function __construct(&$buffer, &$buflen, $params)
	{
		$this->buffer = &$buffer;
		$this->buflen = &$buflen;

		if (isset($params['dispatcher'])) {
			$this->dispatcher = $params['dispatcher'];
		}
	}

	/**
	 * Get a reference to the request object.
	 *
	 * @return Request
	 */
	public function &getRequest() {
		return $this->request;
	}

	/**
	 * Get a reference to the currently processing response object.
	 *
	 * @return Response
	 */
	public function &getResponse() {
		return $this->response;
	}

	public function write() {
		if (empty($this->buffer)) {
			$keepAlive = $this->request->keepAlive();
			$this->request = $this->response =  NULL;

			/* If we've emptied the write buffer but there's more data in the read buffer, this could be another request. Try to process that. */
			if ($keepAlive && !empty($this->rbuffer)) {
				$this->read("", 0);
			}

			return $keepAlive;
		} else {
			return TRUE;
		}
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

		$this->response = $this->dispatch($this->request);

		list($buf, $len) = $this->response->getBuffer();

		$this->buffer .= $buf;

		return TRUE;
	}

	/**
	 * Dispatch the request/response.
	 */
	protected function dispatch(&$request)
	{
		$response = new Response($request);
		$this->dispatcher->dispatch($request, $response);
		return $response;
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

			if (($eolpos = strpos($this->rbuffer, $eol . $eol)) !== FALSE) {
				$lines = substr($this->rbuffer, 0, $eolpos + $dbleollen);
				$lines = explode($eol, $lines);

				$this->rbuffer = substr($this->rbuffer, $eolpos + $dbleollen);
				$this->rbuflen -= $eolpos + $dbleollen;

				/* Parse and validate the request line */
				$request = explode(" ", array_shift($lines));

				if (count($request) != 3) {
					throw new HTTPException("Malformed request line");
				}

				/* Parse and validate the headers */
				$headers = array();
				foreach ($lines as $line) {
					if (empty($line)) continue;

					$header = explode(":", $line, 2);

					if (count($header) != 2) {
						throw new HTTPException("Malformed header line");
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
