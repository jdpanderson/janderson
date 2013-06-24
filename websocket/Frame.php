<?php
/**
 * This file defines the Frame class
 */
namespace janderson\websocket;

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
class Frame {
	/**
	 * Currently known opcodes.
	 * 
	 * These are carried by the last 4 bits of the first byte in a websocket frame.
	 */
	const OPCODE_CONTINUATION = 0;
	const OPCODE_TEXT = 1;
	const OPCODE_BINARY = 2;
	const OPCODE_CLOSE = 8;
	const OPCODE_PING = 9;
	const OPCODE_PONG = 10;

	/**
	 * Signifies that no further frames follow this frame.
	 *
	 * @var bool
	 */
	protected $fin;

	/**
	 * An opcode as defined in RFC6455, expected to be one of the OPCODE_* constants. 4 bits.
	 *
	 * @var int
	 */
	protected $opcode;

	/**
	 * The masking key. A 32-bit integer.
	 *
	 * @var int
	 */
	protected $mask;

	/**
	 * The payload length. 6, 16, or 64 bits.
	 *
	 * @var int
	 */
	protected $length;

	/**
	 * The payload. Binary data.
	 *
	 * @var string
	 */
	protected $payload;

	/**
	 * Unpack a websocket frame from a buffer.
	 *
	 * @param string $buf The binary buffer that should contain a websocket frame.
	 * @param int $buflen The length of the buffer.
	 * @return janderson\websocket\Frame The unpacked frame, or false if a frame could not be unpacked from the given buffer.
	 */
	public static function unpack(&$buf, &$buflen) {
		/* We need at least 2 bytes. */
		if ($buflen < 2) {
			return FALSE;
		}

		/* The first two bytes define flags, opcode, mask (bool), and initial length. */
		list($flags, $len) = array_values(unpack("C2", $buf));
		$fin = (bool)($flags & 0x80); /* Fin is the top-bit at 0x80, or binary mask 10000000 */
		$opcode = $flags & 0x0f; /* The opcode is the 4 bits at 0x0f, or binary mask 00001111 */
		$mask = (bool)($len & 0x80); /* Mask is the top bit at 0x80, or binary mask 10000000 */
		$len = $len & 0x7f; /* Length is the bottom 7 bits, or 0x7f, or binary mask 01111111 */

		/* From here we can determine the overall frame length, and determine if we have enough buffer for it. */
		$key = NULL;
		if ($len == 127) {
			$headerlen = 10 + ($mask ? 4 : 0); /* header + length + mask */

			if ($buflen < $headerlen) {
				return FALSE; /* Not enough data yet. */
			}

			if ($mask) {
				list(/*ign*/, $len1, $len2, $key) = array_values(unpack("na/N3b", $buf));
			} else {
				list(/*ign*/, $len1, $len2) = array_values(unpack("na/N2b", $buf));
			}

			$len = ($len1 << 32) | $len2;
		} elseif ($len == 126) {
			$headerlen = 4 + ($mask ? 4 : 0); /* header + length + mask */

			/* The following 2 bytes are a 16-bit unsigned short length (up to 64k) */
			if ($buflen < $headerlen) { /* buffer has to be at least header+length+mask long to proceed. */
				return FALSE; /* Not enough data yet. */
			}

			if ($mask) {
				list(/*ign*/, $len, $key) = array_values(unpack("n2a/Nb", $buf));
			} else {
				list(/*ign*/, $len) = array_values(unpack("n2", $buf));
			}
		} else {
			$headerlen = 2 + ($mask ? 4 : 0); /* header + length + mask */

			if ($buflen < $headerlen) {
				return FALSE; /* Not enough data yet. */
			}

			if ($mask) {
				list(/*ign*/, $key) = array_values(unpack("na/Nb", $buf));
			}
		}

		if ($buflen < ($headerlen + $len)) {
			return FALSE; /* Not enough data for a complete frame. */
		}

		$payload = substr($buf, $headerlen, $len);

		/* If masked, unmask the data (slow) */
		if ($mask) {
			$keybytes = array(
				($key & 0xff000000) >> 24,
				($key & 0x00ff0000) >> 16,
				($key & 0x0000ff00) >> 8,
				$key & 0x000000ff
			);

			for ($i = 0, $j = 0; $i < $len; $i++, $j++) {
				if ($j > 3) {
					$j = 0;
				}
				$payload[$i] = chr(ord($payload[$i]) ^ $keybytes[$j]);
			}
		}

		/* We've successfully parsed and decoded the frame data, update the buffer. */
		$buf = substr($buf, $headerlen + $len);
		$buflen -= ($headerlen + $len);

		return new Frame($fin, $opcode, $key, $len, $payload);
	}

	public function pack() {
		
	}

	public function __construct($fin, $opcode, $mask, $len, $payload)
	{
		$this->fin = $fin;
		$this->opcode = $opcode;
		$this->mask = $mask;
		$this->len = $len;
		$this->payload = $payload;
	}

	public function isFin()
	{
		return $this->fin;
	}

	public function getOpcode()
	{
		return $this->opcode;
	}

	public function isMasked()
	{
		return isset($this->mask);
	}

	public function getMask()
	{
		return $this->mask;
	}

	public function getLength()
	{
		return $this->len;
	}

	public function getPayload()
	{
		return $this->payload;
	}
}
