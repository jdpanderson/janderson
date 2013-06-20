<?php
/**
 * This file defines the ProtocolHandler interface
 */
namespace janderson\socket\server;

/**
 * An interface to be implemented by protocol handlers that sit on top of a common socket server.
 */
interface ProtocolHandler {
	/**
	 * Create a new ProtocolHandler instance. Called when the server gets a new connection to be handled by this protocol handler.
	 *
	 * @param string &$writebuf The write buffer shared with the server. Simply writing data to this buffer will cause the server to try to write it to the socket.
	 */
	public function __construct(&$writebuf);

	/**
	 * Called when the server has completed a write to the client. (Emptied the write buffer.)
	 *
	 * @return bool Returns true if the server should continue servicing the socket.
	 */
	public function write();

	/**
	 * Called when the server has completed reading from a socket.
	 *
	 * @param string $buf A string containing the binary data the server has just read.
	 * @param int $buflen The length of the binary data in the string.
	 * @return bool Returns true if the server should continue servicing the socket.
	 */
	public function read($buf, $buflen);

	/**
	 * Called when the server has detected that the socket has been closed.
	 */
	public function close();
}
