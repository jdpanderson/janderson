<?php
/**
 * This file defines the Frame class
 */
namespace janderson\socket\server\handler;

use janderson\socket\server\Handler;
use janderson\socket\server\handler\HTTPHandler;

use janderson\http\HTTP;
use janderson\http\Request;
use janderson\http\Response;

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
	const OPCODE_CONTINUATION = 0;
	const OPCODE_TEXT = 1;
	const OPCODE_BINARY = 2;
	const OPCODE_CLOSE = 8;
	const OPCODE_PING = 9;
	const OPCODE_PONG = 10;
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
	protected $wbuf;

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

		list($c1, $c2) = array_values(unpack("C2", $buf));
		list($t) = array_values(unpack('N', $buf));

		echo sprintf("First two shorts: $c1 (%s) $c2 (%s), as long: $t (%s)\n", decbin($c1), decbin($c2), decbin($t));

		echo "Hex: ";
		foreach (str_split($buf) as $chr) {
			echo sprintf("%02x", ord($chr));
		}
		echo "\n";

		echo "Binary garbage: $buf\n\n";

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

	public function _read($buf, $buflen)
	{
		$this->rbuf .= $buf;
		$this->rbuflen += $buflen;

		if ($this->rbuflen >= 2) {
			/* The first two bytes define flags, opcode, mask (bool), and initial length. */
			list($flags, $len) = array_values(unpack("C2", $this->rbuf));
			$fin = (bool)$flags & 0x01;
			$opcode = $flags >> 4;
			$mask = (bool)$len & 0x01;
			$len = $len >> 1;
			
			if ($len == 126) {
				if ($this->rbuflen < 4) {
					return TRUE;
				}
				list($ignore, $len) = array_values(unpack("n2", $this->rbuf));
			} elseif ($len == 127) {
				if ($this->rbuflen < 10) {
					return TRUE;
				}
				list($ignore, $len1, $len2) = array_values(unpack("nN2", $this->rbuf));
				$len = $len2 + ($len1 << 32);
			}

		}

		return TRUE;
	}
}
