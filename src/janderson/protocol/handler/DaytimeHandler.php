<?php
/**
 * This file defines the DaytimeHandler class.
 */
namespace janderson\protocol\handler;

/**
 * Example handler, daytime service.
 *
 * This should be compatible with RFC867, but this has not been tested.
 */
class DaytimeHandler implements ProtocolHandler
{
	const DATE_FORMAT = "l, F j, Y G:i:s-T";
	/**
	 * Write the date as soon as possible.
	 *
	 * @param string &$buffer A reference to the write buffer.
	 * @param int &$bufferlen A reference to the write buffer length (ignored - let the server handle that)
	 */
	public function __construct(&$buffer, &$bufferlen)
	{
		$buffer = date(self::DATE_FORMAT) . "\r\n"; /* E.g. Tuesday, February 22, 1982 17:37:43-PST */
	}

	/**
	 * Ignore any data sent to us.
	 *
	 * @param string $buffer Ignored.
	 * @param int $length Ignored.
	 * @return bool True reads are ignored, but shouldn't close the connection.
	 */
	public function read($buffer, $length) { return TRUE; }

	/**
	 * On write complete, close the socket.
	 *
	 * @return bool False the first completed write should close the connection.
	 */
	public function write() { return FALSE; }

	/**
	 * On close, we don't care.
	 */
	public function close() {}
}
