<?php
/**
 * This file defines the Handler interface
 */
namespace janderson\net\socket\server;

/**
 * An interface to be implemented by protocol handlers that sit on top of sockets.
 */
interface Handler {
	/**
	 * Returns the request as read by the protocol handler.
	 *
	 * @return mixed
	 */
	public function getRequest();

	/**
	 * Sets the response to be sent via the protocol handler.
	 *
	 * @param $response
	 */
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

	/**
	 * For stream sockets, this indicates that the protocol dictates that the socket should close.
	 */
	public function shouldClose();
}
