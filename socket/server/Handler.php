<?php
/**
 * This file defines the Handler interface
 */
namespace janderson\net\socket\server;

use janderson\net\socket\Socket;

/**
 * An interface to be implemented by protocol handlers that sit on top of sockets.
 */
interface Handler {
	/**
	 * Get the current read/write state.
	 *
	 * The state is expected to be an integer:
	 * 1 -> Can read
	 * 2 -> Can write
	 * 3 -> Both
	 *
	 * @return int Returns an integer identifying the current state, read-capable, write-capable, or both. NULL for close.
	 */
	public function getState();

	/**
	 * Sends data to a socket, letting the caller know if it should write, read, or close the socket.
	 *
	 * @param Socket &$socket The socket to which the handler should write.
	 * @return int Returns an integer identifying the current state, read-capable, write-capable, or both. NULL for close.
	 */
	public function write(Socket &$socket);

	/**
	 * Reads data from a socket, letting the caller know if it should read, write, or close the socket.
	 *
	 * @param Socket &$socket The socket from which the handler should read.
	 * @return int Returns an integer identifying the current state, read-capable, write-capable, or both. NULL for close.
	 */
	public function read(Socket &$socket);
}
