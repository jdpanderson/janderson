<?php

namespace janderson\socket\server\handler;

/**
 * Example handler, echoes any input until the client disconnects.
 *
 * This should be compatible with RFC862, but this has not been tested.
 */
class EchoHandler
{
	/**
	 * A local reference to the write buffer.
	 */
	protected $buffer;

	/**
	 * Prepare to handle the echo protocol!
	 *
	 * @param string &$buffer A reference to the write buffer.
	 */
	public function __construct(&$buffer)
	{
		$this->buffer = &$buffer;
	}

	/**
	 * On read complete, append any read data to thh buffer.
	 *
	 * @param string $buffer The newly received data.
	 * @param int $length The length of data received. Ignored.
	 * @return bool True. The service will continue to echo until the client disconnects.
	 */
	public function read($buffer, $length)
	{
		$this->buffer .= $buffer;
		return TRUE;
	}

	/**
	 * We don't care that the write completes.
	 *
	 * @return bool True
	 */
	public function write() { return TRUE; }

	/**
	 * On close, do nothing.
	 */
	public function close() {}
}