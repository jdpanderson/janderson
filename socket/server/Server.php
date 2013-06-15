<?php

namespace janderson\socket\server;

use \janderson\socket\SocketException;
use \janderson\socket\Socket;
use \janderson\Buffer;

class Server {
	/**
	 * Biggest buffer to read.
	 */
	const BUF_MAX_LEN = 16777216; /* 16 MiB; 16 * 1024 * 1024 */

	/**
	 * Receive data in chunks of this size.
	 *
	 * Interestingly, if you try to receive huge chunks, performance plummets. Possibly allocating and de-allocating large buffers?
	 */
	const RCV_MAX_LEN = 4096;

	const SELECT_TIMEOUT = 5;

	/**
	 * The listen socket.
	 *
	 * @var Socket
	 */
	protected $socket;

	/**
	 * A list of tuples containing (each) a socket, a write buffer, and a protocol handler.
	 * 
	 * @var [Socket, Buffer, Handler][]
	 */
	protected $children = array();

	/**
	 * True signifies that server stop has been requested.
	 *
	 * @var bool
	 */
	protected $stop = FALSE;


	protected function log($message) {
		error_log($message);
	}

	/**
	 * @throws SocketException An exception will be thrown by the underlying socket implementation if a listen socket cannot be created.
	 */
	public function __construct(Socket $socket, $handler) {
		$this->socket = $socket;
		$this->handler = $handler;
	}

	public function run() {
		$this->stop = FALSE;

		while (!$this->stop) {
			list($num, $rready, $wready, $err) = $this->select();

			/* No sockets ready to read/write, probably hit the select timeout.  */
			if (!$num) {
				continue;
			}

			foreach ($err as $socket) {
				$this->error($socket);
			}

			foreach ($wready as $socket) {
				$this->send($socket) || $this->close($socket);
			}

			foreach ($rready as $socket) {
				if ($socket === $this->socket) {
					$this->accept() || $this->error($socket);
				} else {
					$this->recv($socket) || $this->close($socket);
				}
			}
		}
	}

	public function stop() {
		$this->stop = TRUE;
	}

	protected function error(&$socket) {
		if ($socket === $this->socket) {
			$this->stop();
		}
		$this->log("Socket $socket in error state. Closing.");
		$this->close($socket);
		return FALSE;
	}

	protected function select() {
		$readers = array($this->socket);
		$writers = array();
		foreach ($this->children as $child) {
			list($socket, $buffer, $handler) = $child;
			$readers[] = $socket;
			if (!empty($buffer)) {
				$writers[] = $socket;
			}
		}

		$err = $readers;
		$num = $this->socket->select($readers, $writers, $err, self::SELECT_TIMEOUT);

		return array($num, $readers, $writers, $err);
	}

	protected function accept() {
		try {
			$socket = $this->socket->accept();
		} catch (\Exception $e) {
			$this->log("Failed to accept remote socket.");
			return FALSE;
		}

		$socket->setBlocking(FALSE);
		$handler = $this->handler;
		$resourceId = $socket->getResourceId();
		$buffer = "";
		$this->children[$resourceId] = array(
			$socket,
			&$buffer,
			new $handler($buffer)
		);
		return TRUE;
	}

	/**
	 * Read as much as possible, then pass the data off to the handler.
	 */
	protected function recv(Socket &$socket) {
		$resourceId = $socket->getResourceId();

		if (!isset($this->children[$resourceId])) {
			return FALSE;
		}

		$buffer = "";
		$length = 0;
		do {
			list($buf, $len) = $socket->recv(self::RCV_MAX_LEN);
			$buffer .= $buf;
			$length += $len;
		} while ($len == self::RCV_MAX_LEN);

		/* 0-length read on its own means the socket has been closed. */
		if ($len === 0 && $length === 0) {
			return FALSE;
		}

		list($socket, $buffer, $handler) = $this->children[$resourceId];

		return $handler->read($buf, $len);
	}

	protected function send(Socket &$socket) {
		$resourceId = $socket->getResourceId();

		if (!isset($this->children[$resourceId])) {
			return FALSE;
		}

		list($socket, $buffer, $handler) = $this->children[$resourceId];

		$len = strlen($buffer); // maybe mb_strlen($buffer, 'pass'); ?

		/* Clear the write buffer and return now if there's nothing more to write. */
		if ($len) {
			$sent = $socket->send($buffer, $len);
			if ($sent === FALSE) {
				return FALSE;
			}
			$buffer = substr($buffer, $sent); // maybe mb_substr($buffer, $sent, $len, 'pass'); ?
			$this->children[$resourceId][1] = $buffer;

			if (empty($buffer)) {
				return $handler->write();
			}
		}

		return TRUE;
	}

	protected function close(Socket &$socket) {
		$resourceId = $socket->getResourceId();

		if (!isset($this->children[$resourceId])) {
			$socket->close();
			return;
		}

		list($socket, $buffer, $handler) = $this->children[$resourceId];
		$socket->close();
		$handler->close();
		unset($this->children[$resourceId]);
	}
}