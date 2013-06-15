<?php

namespace janderson\socket\server;

use \janderson\socket\SocketException;
use \janderson\socket\Socket;
use \janderson\Buffer;

class Child {
	/**
	 * The socket to/from which data will be sent.
	 *
	 * @var resource
	 */ 
	protected $socket;

	/**
	 * Read/recv buffer
	 *
	 * Note: destroying this buffer will close the socket.
	 *
	 * @var Buffer
	 */
	protected $rbuf;

	/**
	 * Write/send buffer
	 *
	 * Note: destroying this buffer will close the socket.
	 *
	 * @var Buffer
	 */
	protected $wbuf;

	/**
	 * @throws SocketException An exception will be thrown by the underlying socket implementation if a listen socket cannot be created.
	 */
	public function __construct(Socket $socket) {
		$this->socket = $socket;

		$this->rbuf = new Buffer();
		$this->wbuf = new Buffer();
	}

	public function getSocket()
	{
		return $this->socket;
	}

	public function read() {
		/* Overrun the max read buffer. Have to exit. */
		if ($this->rbuf->length > Server::BUF_MAX_LEN) {
			return FALSE;
		}

		list($buf, $len) = $this->socket->recv(Server::BUF_MAX_LEN);

		/* 0-length read on its own means the socket has been closed. */
		if ($len === 0) {
			return FALSE;
		}

		$this->rbuf->append($buf, $len);

		/* As an example, just echo everything back to the client. */
		$this->wbuf->set($this->rbuf->buffer, $this->rbuf->length);
		$this->rbuf->clear();

		return TRUE;
	}

	public function write() {
		$chunk = $this->wbuf->length - $this->wbuf->position;

		/* Clear the write buffer and return now if there's nothing more to write. */
		if ($chunk) {
			$sent = $this->socket->send(substr($this->wbuf->buffer, $this->wbuf->position, $chunk), $chunk);
			$this->wbuf->position += $sent;
		}

		/* As an example, just clear the write buffer once it's been written. */
		if ($this->wbuf->position >= $this->wbuf->length) {
			$this->wbuf->clear();
		}

		return TRUE;
	}
}
