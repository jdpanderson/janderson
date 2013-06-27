<?php
/**
 * This file defines the Frame class
 */
namespace janderson\protocol\handler;

use janderson\protocol\http\HTTP;
use janderson\protocol\http\Request;
use janderson\protocol\http\Response;

use janderson\protocol\websocket\Frame;

/**
 * ASCII-art representation of a websocket frame, from RFC 6455
 *
 * @see http://tools.ietf.org/html/rfc6455 Page 28
 *
 *  0                   1                   2                   3
 *  0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 * +-+-+-+-+-------+-+-------------+-------------------------------+
 * |F|R|R|R| opcode|M| Payload len |    Extended payload length    |
 * |I|S|S|S|  (4)  |A|     (7)     |             (16/64)           |
 * |N|V|V|V|       |S|             |   (if payload len==126/127)   |
 * | |1|2|3|       |K|             |                               |
 * +-+-+-+-+-------+-+-------------+ - - - - - - - - - - - - - - - +
 * |     Extended payload length continued, if payload len == 127  |
 * + - - - - - - - - - - - - - - - +-------------------------------+
 * |                               |Masking-key, if MASK set to 1  |
 * +-------------------------------+-------------------------------+
 * | Masking-key (continued)       |          Payload Data         |
 * +-------------------------------- - - - - - - - - - - - - - - - +
 * :                     Payload Data continued ...                :
 * + - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - +
 * |                     Payload Data continued ...                |
 * +---------------------------------------------------------------+
 */

/**
 * Frame
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

	protected $rbuf = "";
	protected $rbuflen = 0;

	/**
	 * Are web in websocket mode, or passthru-to-HTTPHandler mode?
	 */
	protected $websocket = FALSE;

	/**
	 * Determine if a given request is an HTTP to WebSocket upgrade request.
	 *
	 * @return bool True if this is a WebSocket upgrade request.
	 */
	protected function isWebSocketUpgrade($request)
	{
		return ($request->getVersion() == HTTP::VERSION_1_1) && (strtolower($request->getHeader('Connection')) == "upgrade") && (strtolower($request->getHeader('Upgrade')) == "websocket");
	}

	public function read($buf, $buflen)
	{
		if (!$this->websocket) {
			return parent::read($buf, $buflen);
		}

		$frame = Frame::unpack($buf, $buflen);

		if ($frame instanceof Frame) {
			$frame->setMask(NULL);
			list($buf, $buflen) = $frame->pack();

			$this->buffer .= $buf; /* Just echo the frame back for now. */
		}
		
		return TRUE;
	}

	public function write()
	{
		if (!$this->websocket) {
			return parent::write();
		}

		return TRUE;
	}

	public function dispatch(&$request)
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

		$response->setStatusCode(HTTP::STATUS_SWITCHING_PROTOCOLS);
		$response->setHeader('Connection', 'upgrade');
		$response->setHeader('Upgrade', 'websocket');
		$response->setHeader('Sec-WebSocket-Accept', base64_encode(hash('sha1', $key . self::UUID, TRUE)));

		$this->websocket = TRUE;

		return $response;
	}
}
