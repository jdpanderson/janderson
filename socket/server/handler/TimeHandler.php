<?php

namespace janderson\socket\server\handler;

use janderson\socket\server\ProtocolHandler;

/**
 * Example handler, daytime service.
 *
 * This should be compatible with RFC867 if run on TCP port 37, but this has not been tested.
 */
class TimeHandler implements ProtocolHandler
{
	/**
	 * Seconds between Jan 1 1900 and Jan 1 1970
	 */
	const JAN_1_1970 = 2208988800;

	/**
	 * Write the date as soon as possible.
	 *
	 * @param string &$buffer A reference to the write buffer.
	 */
	public function __construct(&$buffer)
	{
		$buffer = pack("N", self::JAN_1_1970 + time()); /* Seconds since January 1 1900. */
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
