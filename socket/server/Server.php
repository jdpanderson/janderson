<?php

namespace janderson\socket\server;

use janderson\log\NullLogger;
use janderson\socket\SocketException;
use janderson\socket\Socket;
use janderson\Buffer;
use janderson\protocol\handler\ProtocolHandler;

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

	/**
	 * Timeout for the select call.
	 *
	 * Lower means better response at the cost of higher overhead. A value between 0.05 and 1 is probably best.
	 *
	 * @var float
	 */
	const SELECT_TIMEOUT = 0.1;

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

	/**
	 * A logger instance
	 *
	 * @var Psr\Log\LoggerInterface
	 */
	protected $logger;

	/**
	 * A callable which serves as a handler factory; Creates or returns protocol handlers.
	 *
	 * The callable will be called with two arguments, the same as the ProtocolHandler interface:
	 * - A reference to the write buffer
	 * - A reference to the write buffer length
	 *
	 * If a ProtocolHandler isn't returned by the callable, the socket will be closed.
	 *
	 * @var callable
	 */
	protected $handler;

	/**
	 * Create a generalized socket server.
	 *
	 * @param Socket $socket
	 * @param Callable $handlerFactory A callable which will be passed the write buffer and is expected to return a ProtocolHandler
	 *
	 * @throws SocketException An exception will be thrown by the underlying socket implementation if a listen socket cannot be created.
	 */
	public function __construct(Socket $socket, $handlerFactory) {
		if (!is_callable($handlerFactory)) {
			throw new SocketException("Handler factory is not callable.");
		}
		$this->socket = $socket;
		$this->handler = $handlerFactory;

		$this->logger = new NullLogger();
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
				$this->logger->notice("select returned error in resource {resid}, possible abnormal socket termination. closing.", array('resid' => $socket->getResourceId()));
				$this->error($socket);
			}

			foreach ($wready as $socket) {
				if (!$this->send($socket)) {
					$this->logger->info("send triggered socket {resid} close", array('resid' => $socket->getResourceId()));
					$this->close($socket);
				}
			}

			foreach ($rready as $socket) {
				if ($socket === $this->socket) {
					if (!$this->accept()) {
						$this->logger->alert("accept triggered listen socket {resid} close. Server terminating.", array('resid' => $socket->getResourceId()));
						$this->error($socket);
						break 2;
					}
				} else {
					if (!$this->recv($socket)) {
						$this->logger->info("recv triggered socket {resid} close", array('resid' => $socket->getResourceId()));
						$this->close($socket);
					}
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
		$this->close($socket);
		return FALSE;
	}

	/**
	 * Perform a select, likely including the listen socket.
	 *
	 * @param bool $listen If true, also select on the listen socket. (Default.)
	 */
	protected function select($listen = TRUE) {
		$readers = $listen ? array($this->socket) : array();
		$writers = array();
		foreach ($this->children as $child) {
			list($socket, $buffer, $buflen, $handler) = $child;
			$readers[] = $socket;
			if (!empty($buffer)) {
				$writers[] = $socket;
			}
		}

		$err = $readers;

		if (empty($readers) && empty($writers)) {
			$this->logger->debug("No readers or writers. Sleeping.");
			usleep(self::SELECT_TIMEOUT * 1000000);
			$num = 0;
		} else {
			$num = $this->socket->select($readers, $writers, $err, self::SELECT_TIMEOUT);
		}

		return array($num, $readers, $writers, $err);
	}

	protected function accept() {
		try {
			$socket = $this->socket->accept();
		} catch (\Exception $e) {
			return FALSE;
		}

		$socket->setBlocking(FALSE);
		$buffer = "";
		$buflen = NULL;

		/* I consider call_user_func* to be "ugly", so for 5.4+, use true callable syntax. */
		if (PHP_VERSION_ID >= 50300) {
			$handlerFn = $this->handler;
			$handler = $handlerFn($buffer, $buflen, array('socket' => $socket));
		} else {
			$handler = call_user_func_array($this->handler, array(&$buffer, &$buflen, array('socket' => $socket)));
		}

		if (!($handler instanceof ProtocolHandler)) {
			$this->log->error("Invalid handler returned by handler factory. Socket will be closed.");
			$this->close($socket);
			return TRUE; /* Error is *not* with the socket, so log it and attempt to continue. */
		}
		$resourceId = $socket->getResourceId();
		$this->children[$resourceId] = array(
			$socket,
			&$buffer,
			&$buflen,
			$handler
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

			/* If we overrun the receive buffer, close the connection. */
			if ($length > self::BUF_MAX_LEN) {
				$this->log->warning("Maximum buffer length per read cycle exceeded on socket {resid}", array('resid' => $resourceId));
				return FALSE;
			}
		} while ($len == self::RCV_MAX_LEN);

		/* 0-length read on its own means the socket has been closed. */
		if ($len === 0 && $length === 0) {
			return FALSE;
		}

		list(/*not needed*/, /*not needed*/, /*not needed*/, $handler) = $this->children[$resourceId];

		return $handler->read($buffer, $length);
	}

	protected function send(Socket &$socket) {
		$resourceId = $socket->getResourceId();

		if (!isset($this->children[$resourceId])) {
			return FALSE;
		}

		list($socket, $buffer, $buflen, $handler) = $this->children[$resourceId];

		if (isset($buflen)) {
			$len = &$buflen;
		} else {
			$len = strlen($buffer); // maybe mb_strlen($buffer, 'pass'); ?
		}

		/* Clear the write buffer and return now if there's nothing more to write. */
		if ($len) {
			$sent = $socket->send($buffer, $len);
			if ($sent === FALSE) {
				return FALSE;
			}
			$buffer = substr($buffer, $sent); // maybe mb_substr($buffer, $sent, $len, 'pass'); ?
			$len -= $sent;
			$this->children[$resourceId][1] = $buffer;

			if (empty($buffer)) {
				return $handler->write();
			}
		}

		return TRUE;
	}

	/**
	 * Close a socket, and destroy any internal resources associated with the socket.
	 *
	 * @param Socket &$socket
	 */
	protected function close(Socket &$socket) {
		$resourceId = $socket->getResourceId();

		if (!isset($this->children[$resourceId])) {
			$socket->close();
			return;
		}

		list($socket, $buffer, $buflen, $handler) = $this->children[$resourceId];
		$socket->close();
		$handler->close();
		unset($this->children[$resourceId]);
	}
}
