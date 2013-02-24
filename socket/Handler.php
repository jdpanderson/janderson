<?php
/**
 * This file defines the Handler interface
 */
namespace janderson\net\socket;

/**
 * An interface to be implemented by protocol handlers that sit on top of sockets.
 */
interface Handler {
	public function getRequest();

	public function setResponse($response);

	/**
	 * Sends data to a socket, letting the caller know if it should write, read, or close the socket.
	 *
	 * @return bool Returns true when ready to change to read state, or null if the connection should be closed.
	 */
	public function sendResponse();

	/**
	 * Reads data from a socket, letting the caller know if it should read, write, or close the socket.
	 *
	 * @return bool Returns true when ready to change to write state, or null if the connection should be closed.
	 */
	public function readRequest();

	/**
	 * Get the socket resource, for use in socket_* functions.
	 *
	 * @return resource The socket resource that belongs to this class.
	 */
	public function getResource();

	public function shouldClose();
}
