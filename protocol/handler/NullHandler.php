<?php
/**
 * This file defines the NullHandler class.
 */
namespace janderson\protocol\handler;

/**
 * Example handler, immediately close the connection.
 */
class NullHandler implements ProtocolHandler
{
	/**
	 * Do nothing.
	 *
	 * @param string &$buffer A reference to the write buffer.
	 * @param int &$bufferlen A reference to the write buffer length (ignored - let the server handle that)
	 */
	public function __construct(&$buffer, &$bufferlen) {}

	/**
	 * Close immediately.
	 *
	 * @param string $buffer Ignored.
	 * @param int $length Ignored.
	 * @return bool True reads are ignored, but shouldn't close the connection.
	 */
	public function read($buffer, $length) { return FALSE; }

	/**
	 * Close immediately.
	 *
	 * @return bool False the first completed write should close the connection.
	 */
	public function write() { return FALSE; }

	/**
	 * On close, we don't care.
	 */
	public function close() {}
}
