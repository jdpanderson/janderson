<?php
/**
 * This file defines the ProtocolHandler interface
 */
namespace janderson\protocol\handler;

/**
 * An interface to be implemented by protocol handlers that sit on top of a common server framework.
 *
 * The concept is very simple: A protocol handler is expected to read and/or write buffers. The details of how those buffers get to and from other endpoints is handled elsewhere.
 *
 * The protocol handler will be passed a reference to a write buffer and buffer length on construction. It should write data to this buffer as the protocol requires, and the lower level code will pass it on.
 *
 * The protocol handler will also have callbacks run when certain "events" happen:
 *  - On connect, the protocol handler will be constructed, being passed a write buffer.
 *  - On completion of a read cycle, the read callback is called with the read data represented as a buffer and buffer length.
 *  - On completion of a write cycle, the write callback is called. (This happens once a write is complete, not when the protocol handler should write.)
 *  - On close of the underlying connection (socket, stream, etc.) the close callback is called.
 *
 * The protocol handler is expected to return minimal state to the server. The read and write callbacks are expected to return true if the server should continue sending/receiving data, or false if the connection should close. The return value of the close callback is ignored.
 * 
 */
interface ProtocolHandler {
	/**
	 * Create a new ProtocolHandler instance. Called when the server gets a new connection to be handled by this protocol handler.
	 *
	 * @param string &$writebuf The write buffer shared with the server. Simply writing data to this buffer will cause the server to try to write it to the connection.
	 * @param int &$writebuflen The write buffer length. This should be updated with the updated length of the write buffer if known. It can also be ignored and the server will determine the buffer length on its own.
	 *
	 * The write buffer and length will be shared between the server and the protocol handler.
	 */
	public function __construct(&$writebuf, &$writebuflen);

	/**
	 * Called when the server has completed a write to the client. (Emptied the write buffer.)
	 *
	 * @return bool Returns true if the server should continue servicing the connection.
	 */
	public function write();

	/**
	 * Called when the server has completed reading from a connection.
	 *
	 * @param string $buf A string containing the binary data the server has just read.
	 * @param int $buflen The length of the binary data in the string.
	 * @return bool Returns true if the server should continue servicing the connection.
	 */
	public function read($buf, $buflen);

	/**
	 * Called when the server has detected that the connection has been closed.
	 */
	public function close();
}
