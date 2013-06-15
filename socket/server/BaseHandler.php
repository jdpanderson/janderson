<?php
/**
 * This file defines the BaseHandler interface
 */
namespace janderson\socket\server;

use janderson\Buffer;
use janderson\socket\Socket;

/**
 * BaseHandler: An example handler that echoes any data back to the client.
 */
class BaseHandler implements Handler {
	const BUF_MAX_LEN = 16777216; /* 16 MiB; 16 * 1024 * 1024 */
	const BUF_CHUNK_LEN = 4096;

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
	 * Initialize internal buffers.
	 */
	public function __construct() {
		$this->rbuf = new Buffer();
		$this->wbuf = new Buffer();
	}

	/**
	 * Get the current state of the handler
	 *
	 * For the base handler, if there's data in the write buffer that's write state. Anything else is read state. No buffers means we're done.
	 *
	 * @return int
	 */
	public function getState() {
		if (!isset($this->rbuf, $this->wbuf)) {
			return NULL;
		} elseif (($this->wbuf->length - $this->wbuf->position) > 0) {
			return Server::STATE_WR;
		} else {
			return Server::STATE_RD;
		}
	}

	/**
	 * Perform a read cycle: read from the socket into the read buffer until no more data is available.
	 *
	 * @param Socket $socket
	 * @return bool Returns true on success, or false on error.
	 */
	public function read(Socket &$socket) {
		$readBytes = 0; /* Track data read in this cycle. */

		do {
			/* Overrun the max read buffer. Have to exit. */
			if ($this->rbuf->length > self::BUF_MAX_LEN) {
				$this->rbuf = $this->wbuf = NULL;
				return FALSE;
			}

			list($buf, $len) = $socket->recv(self::BUF_CHUNK_LEN);

			$readBytes += $len;

			/* 0-length read on its own means the socket has been closed. */
			if (!$readBytes && $len === 0) {
				$this->rbuf = $this->wbuf = NULL;
				return FALSE;
			}

			$this->rbuf->append($buf, $len);
		} while ($len >= self::BUF_CHUNK_LEN);

		return $this->readComplete();
	}

	/**
	 * Method executed when a read cycle is complete.
	 *
	 * @return bool Returns true on success, or false on error.
	 */
	protected function readComplete() {
		/* As an example, just echo everything back to the client. */
		$this->wbuf->set($this->rbuf->buffer, $this->rbuf->length);
		$this->rbuf->clear();

		return TRUE;
	}

	/**
	 * Perform a write cycle: write to the socket from the write buffer until no more data can be written.
	 *
	 * @param Socket $socket
	 * @return bool Returns true on success, or false on error.
	 */
	public function write(Socket &$socket) {
		do {
			$chunk = min($this->wbuf->length - $this->wbuf->position, self::BUF_CHUNK_LEN);

			/* Clear the write buffer and return now if there's nothing more to write. */
			if ($chunk <= 0) {
				$this->wbuf->clear();
				break;
			}

			$sent = $socket->send(substr($this->wbuf->buffer, $this->wbuf->position, $chunk), $chunk);

			$this->wbuf->position += $sent;
		} while ($sent >= $chunk);

		return $this->writeComplete();
	}

	/**
	 * Method executed when a write cycle is complete.
	 *
	 * @return bool Returns true on success, or false on error.
	 */
	protected function writeComplete() {
		/* As an example, just clear the write buffer once it's been written. */
		if ($this->wbuf->position >= $this->wbuf->length) {
			$this->wbuf->clear();
		}

		return TRUE;
	}
}