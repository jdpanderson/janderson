<?php
/**
 * This file defines the BaseHandler interface
 */

use janderson\net\Buffer;

/**
 * BaseHandler
 */
class BaseHandler implements Handler {
	const BUF_MAX_LEN = 16777216; /* 16 MiB; 16 * 1024 * 1024 */
	const BUF_CHUNK_LEN = 4096;

	/**
	 * Read/recv buffer
	 *
	 * @var Buffer
	 */
	protected $rbuf;

	/**
	 * Write/send buffer
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

	public function getState() {
		if ($this->close) {
			return NULL;
		}

		$state = 0;
		if ($this->wbuf->length - $this->wbuf->position) {
			$state |= Server::STATE_WR;
		} else {
			$state |= Server::STATE_RD;
		}

		return $state;
	}

	public function read(Socket &$socket) {
		do {
			if ($this->rbuf->length > self::BUF_MAX_LEN) {
				$this->close = TRUE;
				return;
			}

			list($buf, $len) = $socket->recv(self::BUF_CHUNK_LEN);

			$this->rbuf->append($buf, $len);
		} while ($len >= self::BUF_CHUNK_LEN);

		$this->readComplete();
	}

	protected function readComplete() {
		/* As an example, just echo everything. */
		$this->wbuf->set($this->rbuf->buffer, $this->rbuf->length);
		$this->rbuf->clear();
	}

	public function write(Socket &$socket) {
		do {
			$chunk = min($this->wbuf->length - $this->wbuf->position, self::BUF_CHUNK_LEN);

			/* Clear the write buffer and return now if there's nothing more to write. */
			if ($chunk <= 0) {
				$this->wbuf->clear();
				return;
			}

			$sent = $socket->send(substr($this->wbuf->buffer, $this->wbuf->position, $chunk), $chunk);

			$this->wbuf->position += $sent;
		} while ($sent >= $chunk);

		$this->writeComplete();
	}

	protected function writeComplete() {
		/* As an example, just clear the write buffer once it's been written. */
		if ($this->wbuf->position >= $this->wbuf->length) {
			$this->wbuf->clear();
		}
	}
}