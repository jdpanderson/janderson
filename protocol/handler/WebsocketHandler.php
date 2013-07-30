<?php
/**
 * This file defines the Frame class
 */
namespace janderson\protocol\handler;

use janderson\protocol\http\HTTP;
use janderson\protocol\http\Request;
use janderson\protocol\http\Response;
use janderson\protocol\handler\ProtocolHandler;
use janderson\protocol\websocket\Frame;

/**
 * 
 */
class WebsocketHandler extends HTTPHandler
{
	/**
	 * Supported websocket protocol version.
	 */
	const VERSION = 13;

	/**
	 * UUID used sort of like a "shared secret" for websockets.
	 */
	const UUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";

	/**
	 * Are web in websocket mode, or passthru-to-HTTPHandler mode?
	 *
	 * @var bool
	 */
	protected $websocket = FALSE;

	/**
	 * The protocol handler factory which is expected to produce a ProtocolHandler for incoming WebSocket connections.
	 *
	 * @var callable
	 */
	protected $factory;

	/**
	 * The protocol handler, produced by the protocol handler factory.
	 *
	 * @var ProtocolHandler;
	 */
	protected $handler;

	/**
	 * The websocket write buffer, for handing off to the protocol handler.
	 *
	 * @var string
	 */
	protected $wsBuffer = "";

	/**
	 * The websocket write buffer length, for handing off to the protocol handler.
	 *
	 * @var int;
	 */
	protected $wsBuflen = 0;

	/**
	 * Websocket read buffer.
	 *
	 * @var string
	 */
	protected $wsReadBuffer = "";

	/**
	 * Websocket read buffer length.
	 *
	 * @var int
	 */
	protected $wsReadBuflen = 0;

	/**
	 * Determine if a given request is an HTTP to WebSocket upgrade request.
	 *
	 * @return bool True if this is a WebSocket upgrade request.
	 */
	protected function isWebSocketUpgrade($request)
	{
		return ($request->getVersion() == HTTP::VERSION_1_1) && (strtolower($request->getHeader('Connection')) == "upgrade") && (strtolower($request->getHeader('Upgrade')) == "websocket");
	}

	public function setProtocolHandlerFactory(callable $factory)
	{
		$this->factory = $factory;
	}

	public function read($buffer, $buflen)
	{
		if (!$this->websocket) {
			return parent::read($buffer, $buflen);
		}

		$frame = Frame::unpack($buffer, $buflen);

		if ($frame instanceof Frame) {
			$this->wsReadBuflen += $frame->getLength(); 
			$this->wsReadBuffer .= $frame->getPayload();

			/* If we're done reading a sequence, pass it to the protocol handler. */
			if ($frame->isFin()) {
				$this->handler->read($this->wsReadBuffer, $this->wsReadBuflen); // XXX handle false (close) from protocol handler.
				$this->wsReadBuffer = "";
				$this->wsReadBuflen = 0;
			}

			$this->writeBuffer();
		}
		
		return TRUE;
	}

	public function write()
	{
		if (!$this->websocket) {
			return parent::write();
		}

		$this->handler(write()); // XXX handle false (close) from protocol handler.

		return TRUE;
	}

	/**
	 * If the websocket write buffer is not empty, pack then write it to the HTTP buffer to go out on the socket.
	 */
	private function writeBuffer()
	{
		/* Handle the case where the protocol handler doesn't update the buffer length. */
		if (!empty($this->wsBuffer) && !$this->wsBuflen) {
			$this->wsBuflen = strlen($this->wsBuffer);
		}

		/* If the buffer isn't empty, pack it and write it. */
		if ($this->wsBuflen) {
			$frame = new Frame(TRUE, Frame::OPCODE_BINARY, FALSE, $this->wsBuflen, $this->wsBuffer);
			list($buf, $buflen) = $frame::pack();
			$this->buffer .= $buf;
			$this->buflen += $buflen;

			$this->wsBuffer = "";
			$this->wsBuflen = 0;
		}
	}

	/**
	 * Override the parent HTTPHandler's dispatch method to intercept WebSocket upgrade requests.
	 *
	 * @param Request &$request
	 */
	protected function dispatch(&$request)
	{
		/* If this is *not* a WebSocket upgrade, just treat it like a standard HTTP request. */
		if (!$this->isWebSocketUpgrade($request)) {
			return parent::dispatch($request);
		}

		$response = new Response($request);

		/* Let the client know if we/they are using an unsupported version */
		if ($request->getHeader('Sec-WebSocket-Version') != self::VERSION) {
			$response->setStatusCode(HTTP::STATUS_UPGRADE_REQUIRED);
			$response->setHeader('Sec-WebSocket-Version', self::VERSION);
			return $response;
		}

		$key = $request->getHeader('Sec-WebSocket-Key');

		/* WebSocket key is a 16-byte key required as part of the response to prove we're websocket-capable. */
		if (empty($key)) { /* A protocol oddity is that we're not required to decode it, so don't bother decoding it. */
			$response->setStatusCode(HTTP::STATUS_BAD_REQUEST);
			return $response;
		}

		/**
		 * We've determined we're going to upgrade to websocket, we need to figure out our Websocket handler.
		 */
		if (!($this->handler = $this->getProtocolHandler($request, $response))) {
			/* Only return a server error if the status wasn't changed. */
			if ($response->getStatusCode() == HTTP::STATUS_OK) {
				$response->setStatusCode(HTTP::STATUS_INTERNAL_SERVER_ERROR);
			}
			return $response;
		}

		$response->setStatusCode(HTTP::STATUS_SWITCHING_PROTOCOLS);
		$response->setHeader('Connection', 'upgrade');
		$response->setHeader('Upgrade', 'websocket');
		$response->setHeader('Sec-WebSocket-Accept', base64_encode(hash('sha1', $key . self::UUID, TRUE)));

		$this->websocket = TRUE;

		return $response;
	}

	/**
	 * Get a protocol handler instance which will be used to handle websocket I/O
	 *
	 * @return ProtocolHandler
	 */
	protected function getProtocolHandler(Request &$request, Response &$response)
	{
		if (!is_callable($this->factory)) {
			return FALSE;
		}

		 /* I consider call_user_func* to be "ugly", so for 5.4+ use callable syntax. */
		if (PHP_VERSION_ID >= 50400) {
			$factory = $this->factory;
			$handler = $factory($this->wsBuffer, $this->wsBuflen, $request, $response);
		} else {
			$handler = call_user_func_array($this->factory, array(&$this->wsBuffer, &$this->wsBuflen, &$request, &$response));
		}

		if (!($handler instanceof ProtocolHandler)) {
			return FALSE;
		}

		if ($handler instanceof HTTPAware) {
			$handler->setRequest($request);
			$handler->setResponse($response);
		}

		return $handler;
	}
}
